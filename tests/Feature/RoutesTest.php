<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RoutesTest extends TestCase
{
    /** @test */
    public function it_registers_dashboard_routes()
    {
        $this->assertTrue(Route::has('ab-testing.dashboard.index'));
        $this->assertTrue(Route::has('ab-testing.dashboard.create'));
        $this->assertTrue(Route::has('ab-testing.dashboard.store'));
        $this->assertTrue(Route::has('ab-testing.dashboard.show'));
        $this->assertTrue(Route::has('ab-testing.dashboard.edit'));
        $this->assertTrue(Route::has('ab-testing.dashboard.update'));
        $this->assertTrue(Route::has('ab-testing.dashboard.destroy'));
        $this->assertTrue(Route::has('ab-testing.dashboard.toggle'));
    }

    /** @test */
    public function it_registers_api_routes()
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
    public function it_registers_debug_routes()
    {
        $this->assertTrue(Route::has('ab-testing.clear-session'));
    }

    /** @test */
    public function dashboard_routes_use_correct_methods()
    {
        $routes = Route::getRoutes();
        
        // Find dashboard routes and check their methods
        foreach ($routes as $route) {
            $name = $route->getName();
            if (!$name || !str_starts_with($name, 'ab-testing.dashboard.')) {
                continue;
            }

            switch ($name) {
                case 'ab-testing.dashboard.index':
                case 'ab-testing.dashboard.show':
                case 'ab-testing.dashboard.create':
                case 'ab-testing.dashboard.edit':
                    $this->assertContains('GET', $route->methods());
                    break;
                case 'ab-testing.dashboard.store':
                    $this->assertContains('POST', $route->methods());
                    break;
                case 'ab-testing.dashboard.update':
                    $this->assertContains('PUT', $route->methods());
                    break;
                case 'ab-testing.dashboard.destroy':
                    $this->assertContains('DELETE', $route->methods());
                    break;
                case 'ab-testing.dashboard.toggle':
                    $this->assertContains('PATCH', $route->methods());
                    break;
            }
        }
    }

    /** @test */
    public function api_routes_use_correct_methods()
    {
        $routes = Route::getRoutes();
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            
            if ($uri === 'api/ab-testing/track') {
                $this->assertContains('POST', $route->methods());
            }
            
            if ($uri === 'api/ab-testing/variant') {
                $this->assertContains('POST', $route->methods());
            }
            
            if ($uri === 'api/ab-testing/results/{experiment}') {
                $this->assertContains('GET', $route->methods());
            }
        }
    }

    /** @test */
    public function clear_session_route_uses_post_method()
    {
        $route = Route::getRoutes()->getByName('ab-testing.clear-session');
        
        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    /** @test */
    public function dashboard_routes_have_web_middleware()
    {
        $route = Route::getRoutes()->getByName('ab-testing.dashboard.index');
        
        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
    }

    /** @test */
    public function routes_are_properly_namespaced()
    {
        // Dashboard routes should be under /ab-testing/dashboard
        $indexRoute = Route::getRoutes()->getByName('ab-testing.dashboard.index');
        $this->assertEquals('ab-testing/dashboard', $indexRoute->uri());

        $createRoute = Route::getRoutes()->getByName('ab-testing.dashboard.create');
        $this->assertEquals('ab-testing/dashboard/create', $createRoute->uri());

        // Clear session should be under /ab-testing
        $clearRoute = Route::getRoutes()->getByName('ab-testing.clear-session');
        $this->assertEquals('ab-testing/clear-session', $clearRoute->uri());
    }

    /** @test */
    public function api_routes_accept_json()
    {
        $response = $this->postJson('/api/ab-testing/track', []);
        // Should get validation error, not 404
        $this->assertNotEquals(404, $response->status());

        $response = $this->postJson('/api/ab-testing/variant', []);
        // Should get validation error, not 404
        $this->assertNotEquals(404, $response->status());

        $response = $this->getJson('/api/ab-testing/results/test');
        // Should get 404 (experiment not found), not route not found
        $this->assertEquals(404, $response->status());
    }
}