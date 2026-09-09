<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class DenyAllMiddleware
{
    public function handle($request, \Closure $next)
    {
        abort(403);
    }
}

class RouteMiddlewareConfigTest extends TestCase
{
    protected function usesCustomDashboardMiddleware($app): void
    {
        $app['config']->set('ab-testing.routes.dashboard_middleware', ['web', DenyAllMiddleware::class]);
    }

    protected function usesStatsMiddlewareOverride($app): void
    {
        $app['config']->set('ab-testing.routes.dashboard_middleware', ['web', DenyAllMiddleware::class]);
        $app['config']->set('ab-testing.routes.stats_middleware', ['api']);
    }

    protected function usesDisabledRoutes($app): void
    {
        $app['config']->set('ab-testing.routes.enabled', false);
    }

    /** @test */
    public function stats_routes_inherit_dashboard_middleware_by_default()
    {
        $stats = Route::getRoutes()->getByName('ab-testing.api.experiment.stats');
        $results = Route::getRoutes()->getByName('ab-testing.api.results');

        $this->assertContains('web', $stats->middleware());
        $this->assertContains('web', $results->middleware());
    }

    /** @test */
    public function tracking_routes_keep_api_middleware_by_default()
    {
        $track = Route::getRoutes()->getByName('ab-testing.api.track');
        $variant = Route::getRoutes()->getByName('ab-testing.api.variant');

        $this->assertContains('api', $track->middleware());
        $this->assertContains('api', $variant->middleware());
    }

    /** @test */
    #[DefineEnvironment('usesCustomDashboardMiddleware')]
    public function configured_dashboard_middleware_gates_dashboard_and_stats_but_not_tracking()
    {
        $dashboard = Route::getRoutes()->getByName('ab-testing.dashboard.index');
        $stats = Route::getRoutes()->getByName('ab-testing.api.experiment.stats');
        $track = Route::getRoutes()->getByName('ab-testing.api.track');

        $this->assertContains(DenyAllMiddleware::class, $dashboard->middleware());
        $this->assertContains(DenyAllMiddleware::class, $stats->middleware());
        $this->assertNotContains(DenyAllMiddleware::class, $track->middleware());

        $this->getJson('/api/ab-testing/experiments/1/stats')->assertStatus(403);
        $this->assertNotEquals(403, $this->postJson('/api/ab-testing/track', [])->status());
    }

    /** @test */
    #[DefineEnvironment('usesStatsMiddlewareOverride')]
    public function stats_middleware_override_beats_dashboard_middleware()
    {
        $stats = Route::getRoutes()->getByName('ab-testing.api.experiment.stats');

        $this->assertContains('api', $stats->middleware());
        $this->assertNotContains(DenyAllMiddleware::class, $stats->middleware());
    }

    /** @test */
    #[DefineEnvironment('usesDisabledRoutes')]
    public function disabled_routes_config_registers_no_package_routes()
    {
        $this->assertFalse(Route::has('ab-testing.dashboard.index'));
        $this->assertFalse(Route::has('ab-testing.api.track'));
    }
}
