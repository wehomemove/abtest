<?php

namespace Homemove\AbTesting\Providers;

use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Middleware\AbTestMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class AbTestingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('ab-testing', function ($app) {
            return new AbTestService();
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/ab-testing.php', 'ab-testing'
        );
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ab-testing');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        
        $this->publishes([
            __DIR__.'/../config/ab-testing.php' => config_path('ab-testing.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Only publish config and migrations by default (no views)
        $this->publishes([
            __DIR__.'/../config/ab-testing.php' => config_path('ab-testing.php'),
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ]);

        $this->registerBladeDirectives();
        $this->registerMiddleware();
        $this->registerDebugInjection();
    }

    protected function registerBladeDirectives()
    {
        Blade::directive('variant', function ($expression) {
            return "<?php if(app('ab-testing')->isVariant({$expression})): ?>";
        });

        Blade::directive('endvariant', function () {
            return "<?php endif; ?>";
        });

        Blade::directive('abtrack', function ($expression) {
            return "<?php app('ab-testing')->track({$expression}); ?>";
        });
    }

    protected function registerMiddleware()
    {
        $this->app['router']->aliasMiddleware('ab-test', AbTestMiddleware::class);
    }

    protected function registerDebugInjection()
    {
        if (!config('app.debug')) {
            return;
        }

        // Register a terminating callback to inject debug UI
        $this->app->terminating(function () {
            $service = app('ab-testing');
            $experiments = $service->getDebugExperiments();
            
            if (empty($experiments)) {
                return;
            }

            // Only inject for web responses with HTML content
            $response = app('Illuminate\Http\Response');
            if (!$response || !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                return;
            }

            $debugHtml = view('ab-testing::debug', compact('experiments'))->render();
            
            // Inject before closing body tag
            $content = $response->getContent();
            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', $debugHtml . '</body>', $content);
                $response->setContent($content);
            }
        });
    }
}