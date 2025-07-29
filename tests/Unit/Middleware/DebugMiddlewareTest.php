<?php

namespace Homemove\AbTesting\Tests\Unit\Middleware;

use Homemove\AbTesting\Middleware\DebugMiddleware;
use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Mockery;

class DebugMiddlewareTest extends TestCase
{
    protected DebugMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new DebugMiddleware();
    }

    /** @test */
    public function it_passes_through_when_debug_disabled()
    {
        config(['app.debug' => false]);

        $request = Request::create('/test');
        $response = new Response('<html><body>Test</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
        $this->assertEquals('<html><body>Test</body></html>', $result->getContent());
    }

    /** @test */
    public function it_passes_through_non_html_responses()
    {
        config(['app.debug' => true]);

        $request = Request::create('/api/test');
        $response = new Response('{"test": true}');
        $response->headers->set('Content-Type', 'application/json');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
        $this->assertEquals('{"test": true}', $result->getContent());
    }

    /** @test */
    public function it_passes_through_when_no_experiments_active()
    {
        config(['app.debug' => true]);

        $mockService = Mockery::mock(AbTestService::class);
        $mockService->shouldReceive('getDebugExperiments')->andReturn([]);
        $mockService->shouldReceive('getDebugUserInfo')->andReturn([
            'user_id' => 'test_user',
            'source' => 'cookie',
            'cookie_exists' => true,
            'session_exists' => false,
            'session_started' => true
        ]);

        $this->app->instance('ab-testing', $mockService);

        $request = Request::create('/test');
        $response = new Response('<html><body>No experiments</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        Log::shouldReceive('info')->once();

        $result = $this->middleware->handle($request, $next);

        $this->assertEquals('<html><body>No experiments</body></html>', $result->getContent());
    }

    /** @test */
    public function it_passes_through_when_no_body_tag()
    {
        config(['app.debug' => true]);

        $mockService = Mockery::mock(AbTestService::class);
        $mockService->shouldReceive('getDebugExperiments')->andReturn(['test_exp' => ['variant' => 'control', 'calls' => 1]]);
        $mockService->shouldReceive('getDebugUserInfo')->andReturn([
            'user_id' => 'test_user',
            'source' => 'cookie',
            'cookie_exists' => true,
            'session_exists' => false,
            'session_started' => true
        ]);

        $this->app->instance('ab-testing', $mockService);

        $request = Request::create('/test');
        $response = new Response('<html><head><title>Test</title></head></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        Log::shouldReceive('info')->once();

        $result = $this->middleware->handle($request, $next);

        $this->assertEquals('<html><head><title>Test</title></head></html>', $result->getContent());
    }

    /** @test */
    public function it_injects_debug_panel_and_javascript_when_conditions_met()
    {
        config(['app.debug' => true]);

        $mockService = Mockery::mock(AbTestService::class);
        $mockService->shouldReceive('getDebugExperiments')->andReturn([
            'test_experiment' => ['variant' => 'control', 'calls' => 2]
        ]);
        $mockService->shouldReceive('getDebugUserInfo')->andReturn([
            'user_id' => 'test_user_123',
            'source' => 'cookie',
            'cookie_exists' => true,
            'session_exists' => false,
            'session_started' => true
        ]);

        $this->app->instance('ab-testing', $mockService);

        // Create test experiment for the debug view
        $this->artisan('migrate');
        \Illuminate\Support\Facades\DB::table('ab_experiments')->insert([
            'name' => 'test_experiment',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test');
        $response = new Response('<html><body><h1>Test Page</h1></body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        Log::shouldReceive('info')->twice(); // Once for middleware, once for successful injection

        $result = $this->middleware->handle($request, $next);

        $content = $result->getContent();
        
        // Should contain the original content
        $this->assertStringContainsString('<h1>Test Page</h1>', $content);
        
        // Should contain injected JavaScript
        $this->assertStringContainsString('window.abtrack', $content);
        $this->assertStringContainsString('window.abvariant', $content);
        
        // Should contain debug panel
        $this->assertStringContainsString('ab-test-debug', $content);
        $this->assertStringContainsString('test_experiment', $content);
    }

    /** @test */
    public function it_handles_view_render_errors_gracefully()
    {
        config(['app.debug' => true]);

        $mockService = Mockery::mock(AbTestService::class);
        $mockService->shouldReceive('getDebugExperiments')->andReturn([
            'invalid_experiment' => ['variant' => 'control', 'calls' => 1]
        ]);
        $mockService->shouldReceive('getDebugUserInfo')->andReturn([
            'user_id' => 'test_user',
            'source' => 'session',
            'cookie_exists' => false,
            'session_exists' => true,
            'session_started' => true
        ]);

        $this->app->instance('ab-testing', $mockService);

        $request = Request::create('/test');
        $response = new Response('<html><body>Test</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once()->with('AB Debug: Failed to render debug view', Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        // Should return original response when view fails to render
        $this->assertEquals('<html><body>Test</body></html>', $result->getContent());
    }

    /** @test */
    public function it_generates_csrf_token_in_javascript()
    {
        config(['app.debug' => true]);

        $mockService = Mockery::mock(AbTestService::class);
        $mockService->shouldReceive('getDebugExperiments')->andReturn([
            'csrf_test' => ['variant' => 'control', 'calls' => 1]
        ]);
        $mockService->shouldReceive('getDebugUserInfo')->andReturn([
            'user_id' => 'test_user',
            'source' => 'cookie',
            'cookie_exists' => true,
            'session_exists' => false,
            'session_started' => true
        ]);

        $this->app->instance('ab-testing', $mockService);

        // Create test experiment
        \Illuminate\Support\Facades\DB::table('ab_experiments')->insert([
            'name' => 'csrf_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test');
        $response = new Response('<html><body>Test</body></html>');
        $response->headers->set('Content-Type', 'text/html');

        $next = function ($req) use ($response) {
            return $response;
        };

        Log::shouldReceive('info')->twice();

        $result = $this->middleware->handle($request, $next);

        $content = $result->getContent();
        
        // Should contain CSRF token in the JavaScript
        $this->assertStringContainsString("'X-CSRF-TOKEN':", $content);
    }

    /** @test */
    public function it_handles_responses_without_getcontent_method()
    {
        config(['app.debug' => true]);

        $request = Request::create('/test');
        
        // Create a response object without getContent method
        $response = new class {
            public function headers() {
                return new class {
                    public function get($key, $default = null) {
                        return 'text/html';
                    }
                };
            }
        };

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }
}