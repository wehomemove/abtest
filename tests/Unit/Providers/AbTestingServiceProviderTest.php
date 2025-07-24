<?php

namespace Homemove\AbTesting\Tests\Unit\Providers;

use Homemove\AbTesting\Providers\AbTestingServiceProvider;
use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Middleware\AbTestMiddleware;
use Homemove\AbTesting\Middleware\DebugMiddleware;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

class AbTestingServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_ab_testing_service()
    {
        $this->assertTrue($this->app->bound('ab-testing'));
        $this->assertInstanceOf(AbTestService::class, $this->app->make('ab-testing'));
    }

    /** @test */
    public function it_registers_service_as_singleton()
    {
        $service1 = $this->app->make('ab-testing');
        $service2 = $this->app->make('ab-testing');

        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_merges_config()
    {
        // The service provider should merge the package config
        $this->assertNotNull(config('ab-testing'));
    }

    /** @test */
    public function it_loads_migrations()
    {
        // Test that migrations are loaded by checking if tables can be created
        $this->artisan('migrate');
        
        $this->assertTrue(\Schema::hasTable('ab_experiments'));
        $this->assertTrue(\Schema::hasTable('ab_user_assignments'));
        $this->assertTrue(\Schema::hasTable('ab_events'));
    }

    /** @test */
    public function it_loads_views()
    {
        // Test that views are available
        $this->assertTrue(view()->exists('ab-testing::debug'));
        $this->assertTrue(view()->exists('ab-testing::dashboard.index'));
    }

    /** @test */
    public function it_loads_routes()
    {
        // Test that routes are registered
        $routes = Route::getRoutes();
        
        $routeNames = [];
        foreach ($routes as $route) {
            if ($route->getName()) {
                $routeNames[] = $route->getName();
            }
        }

        $this->assertContains('ab-testing.dashboard.index', $routeNames);
        $this->assertContains('ab-testing.clear-session', $routeNames);
    }

    /** @test */
    public function it_registers_blade_directives()
    {
        // Test @variant directive
        $result = Blade::compileString('@variant("test_exp", "control")');
        $this->assertStringContains('ab-testing', $result);
        $this->assertStringContains('isVariant', $result);

        // Test @endvariant directive
        $result = Blade::compileString('@endvariant');
        $this->assertStringContains('endif', $result);

        // Test @abtrack directive
        $result = Blade::compileString('@abtrack("test_exp", null, "click")');
        $this->assertStringContains('ab-testing', $result);
        $this->assertStringContains('track', $result);
    }

    /** @test */
    public function it_registers_middleware_alias()
    {
        $router = $this->app['router'];
        
        // Check that the middleware alias is registered
        $middlewares = $router->getMiddleware();
        $this->assertArrayHasKey('ab-test', $middlewares);
        $this->assertEquals(AbTestMiddleware::class, $middlewares['ab-test']);
    }

    /** @test */
    public function it_registers_debug_middleware_when_debug_enabled()
    {
        config(['app.debug' => true]);
        
        // Re-instantiate the service provider to test the debug condition
        $provider = new AbTestingServiceProvider($this->app);
        $provider->boot();

        $router = $this->app['router'];
        $webMiddleware = $router->getMiddlewareGroups()['web'] ?? [];
        
        $this->assertContains(DebugMiddleware::class, $webMiddleware);
    }

    /** @test */
    public function it_does_not_register_debug_middleware_when_debug_disabled()
    {
        config(['app.debug' => false]);
        
        // Create a fresh app instance for this test
        $app = $this->createApplication();
        $app['config']->set('app.debug', false);
        
        $provider = new AbTestingServiceProvider($app);
        $provider->boot();

        $router = $app['router'];
        $webMiddleware = $router->getMiddlewareGroups()['web'] ?? [];
        
        $this->assertNotContains(DebugMiddleware::class, $webMiddleware);
    }

    /** @test */
    public function it_can_publish_config()
    {
        // Test that config can be published
        $this->artisan('vendor:publish', [
            '--provider' => AbTestingServiceProvider::class,
            '--tag' => 'config'
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_can_publish_migrations()
    {
        // Test that migrations can be published
        $this->artisan('vendor:publish', [
            '--provider' => AbTestingServiceProvider::class,
            '--tag' => 'migrations'
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_can_publish_assets()
    {
        // Test that assets can be published
        $this->artisan('vendor:publish', [
            '--provider' => AbTestingServiceProvider::class,
            '--tag' => 'assets'
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_publishes_default_resources()
    {
        // Test that default publish works (config + migrations)
        $this->artisan('vendor:publish', [
            '--provider' => AbTestingServiceProvider::class
        ])->assertExitCode(0);
    }

    /** @test */
    public function blade_variant_directive_compiles_correctly()
    {
        $compiled = Blade::compileString('@variant("header_test", "blue")Content@endvariant');
        
        // Should contain the PHP code for checking variant
        $this->assertStringContains("app('ab-testing')->isVariant", $compiled);
        $this->assertStringContains('header_test', $compiled);
        $this->assertStringContains('blue', $compiled);
        $this->assertStringContains('Content', $compiled);
        $this->assertStringContains('endif', $compiled);
    }

    /** @test */
    public function blade_abtrack_directive_compiles_correctly()
    {
        $compiled = Blade::compileString('@abtrack("test_exp", "user123", "conversion", ["value" => 100])');
        
        // Should contain the PHP code for tracking
        $this->assertStringContains("app('ab-testing')->track", $compiled);
        $this->assertStringContains('test_exp', $compiled);
        $this->assertStringContains('user123', $compiled);
        $this->assertStringContains('conversion', $compiled);
    }

    /** @test */
    public function it_handles_nested_variant_directives()
    {
        $template = '
        @variant("header_test", "blue")
            <h1 style="color: blue">Blue Header</h1>
            @variant("button_test", "large")
                <button class="large">Large Button</button>
            @endvariant
        @endvariant
        ';

        $compiled = Blade::compileString($template);
        
        // Should handle nested directives correctly
        $this->assertStringContains('header_test', $compiled);
        $this->assertStringContains('button_test', $compiled);
        $this->assertStringContains('Blue Header', $compiled);
        $this->assertStringContains('Large Button', $compiled);
    }

    /** @test */
    public function it_loads_api_routes()
    {
        $routes = Route::getRoutes();
        
        $apiRoutes = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/ab-testing')) {
                $apiRoutes[] = $uri;
            }
        }

        $this->assertContains('api/ab-testing/track', $apiRoutes);
        $this->assertContains('api/ab-testing/variant', $apiRoutes);
        $this->assertContains('api/ab-testing/results/{experiment}', $apiRoutes);
    }

    /** @test */
    public function it_sets_up_environment_correctly()
    {
        // Test that our test environment is set up correctly
        $this->assertEquals('testing', config('database.default'));
        $this->assertEquals('array', config('cache.default'));
        $this->assertEquals('array', config('session.driver'));
    }
}