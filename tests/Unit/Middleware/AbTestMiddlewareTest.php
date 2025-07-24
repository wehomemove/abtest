<?php

namespace Homemove\AbTesting\Tests\Unit\Middleware;

use Homemove\AbTesting\Middleware\AbTestMiddleware;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AbTestMiddlewareTest extends TestCase
{
    protected AbTestMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AbTestMiddleware();
    }

    /** @test */
    public function it_assigns_variants_to_request_attributes()
    {
        // Create test experiments
        DB::table('ab_experiments')->insert([
            [
                'name' => 'header_test',
                'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'button_test',
                'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $request = Request::create('/test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            // Verify variants are assigned to request attributes
            $this->assertNotNull($req->attributes->get('ab_header_test'));
            $this->assertNotNull($req->attributes->get('ab_button_test'));
            
            $headerVariant = $req->attributes->get('ab_header_test');
            $buttonVariant = $req->attributes->get('ab_button_test');
            
            $this->assertContains($headerVariant, ['control', 'variant_a']);
            $this->assertContains($buttonVariant, ['control', 'variant_b']);
            
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'header_test', 'button_test');

        $this->assertSame($response, $result);
    }

    /** @test */
    public function it_handles_single_experiment()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'single_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            $this->assertEquals('control', $req->attributes->get('ab_single_test'));
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'single_test');

        $this->assertSame($response, $result);
    }

    /** @test */
    public function it_handles_no_experiments()
    {
        $request = Request::create('/test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            // No attributes should be set
            $this->assertEmpty($req->attributes->all());
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    /** @test */
    public function it_tracks_page_views_when_enabled()
    {
        config(['ab-testing.tracking.enabled' => true]);

        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'tracking_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test-page', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Test Browser 1.0'
        ]);
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'tracking_test');

        // Verify page view was tracked
        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'event_name' => 'page_view',
            'variant' => 'control',
        ]);

        $event = DB::table('ab_events')->where('experiment_id', $experimentId)->first();
        $properties = json_decode($event->properties, true);
        $this->assertEquals('/test-page', $properties['url']);
        $this->assertEquals('Test Browser 1.0', $properties['user_agent']);
    }

    /** @test */
    public function it_does_not_track_page_views_when_disabled()
    {
        config(['ab-testing.tracking.enabled' => false]);

        DB::table('ab_experiments')->insert([
            'name' => 'no_tracking_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test-page');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'no_tracking_test');

        // Verify no page view was tracked
        $this->assertDatabaseMissing('ab_events', [
            'event_name' => 'page_view',
        ]);
    }

    /** @test */
    public function it_tracks_multiple_experiments()
    {
        config(['ab-testing.tracking.enabled' => true]);

        $exp1Id = DB::table('ab_experiments')->insertGetId([
            'name' => 'multi_test_1',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exp2Id = DB::table('ab_experiments')->insertGetId([
            'name' => 'multi_test_2',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/multi-test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'multi_test_1', 'multi_test_2');

        // Verify both experiments were tracked
        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $exp1Id,
            'event_name' => 'page_view',
        ]);

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $exp2Id,
            'event_name' => 'page_view',
        ]);
    }

    /** @test */
    public function it_handles_inactive_experiments()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'inactive_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            // Should still set attribute, but with 'control' variant
            $this->assertEquals('control', $req->attributes->get('ab_inactive_test'));
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'inactive_test');

        $this->assertSame($response, $result);
    }

    /** @test */
    public function it_handles_nonexistent_experiments()
    {
        $request = Request::create('/test');
        $response = new Response('Test response');

        $next = function ($req) use ($response) {
            // Should still set attribute with 'control' variant
            $this->assertEquals('control', $req->attributes->get('ab_nonexistent'));
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'nonexistent');

        $this->assertSame($response, $result);
    }

    /** @test */
    public function it_maintains_consistent_variants_for_same_user()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'consistency_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/test');
        $response = new Response('Test response');

        $variant1 = null;
        $variant2 = null;

        // First request
        $next1 = function ($req) use ($response, &$variant1) {
            $variant1 = $req->attributes->get('ab_consistency_test');
            return $response;
        };

        $this->middleware->handle($request, $next1, 'consistency_test');

        // Second request (should get same variant)
        $next2 = function ($req) use ($response, &$variant2) {
            $variant2 = $req->attributes->get('ab_consistency_test');
            return $response;
        };

        $this->middleware->handle($request, $next2, 'consistency_test');

        $this->assertEquals($variant1, $variant2);
    }
}