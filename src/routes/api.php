<?php

use Illuminate\Support\Facades\Route;
use Homemove\AbTesting\Http\Controllers\ApiController;

// Open tracking endpoints: pages call these from every visitor's browser.
Route::prefix('api/ab-testing')
    ->name('ab-testing.api.')
    ->middleware(config('ab-testing.routes.api_middleware', ['api']))
    ->group(function () {
        Route::post('/track', [ApiController::class, 'track'])->name('track');
        Route::post('/variant', [ApiController::class, 'getVariant'])->name('variant');
        Route::get('/variant/{experiment}', [ApiController::class, 'getVariantByExperiment'])->name('variant.get');
        Route::post('/register-debug', [ApiController::class, 'registerDebugExperiment'])->name('register-debug');
    });

// Read-only results endpoints: these feed the dashboard's polls and expose
// experiment stats, so they share the dashboard's gate unless overridden.
Route::prefix('api/ab-testing')
    ->name('ab-testing.api.')
    ->middleware(config('ab-testing.routes.stats_middleware') ?? config('ab-testing.routes.dashboard_middleware', ['web']))
    ->group(function () {
        Route::get('/results/{experiment}', [ApiController::class, 'getResults'])->name('results');
        Route::get('/experiments/{experiment}/stats', [ApiController::class, 'getExperimentStats'])->name('experiment.stats');
        Route::get('/experiments/{experiment}/recent-activity', [ApiController::class, 'getRecentActivity'])->name('experiment.activity');
        Route::get('/experiments/{experiment}/chart-data', [ApiController::class, 'getChartData'])->name('experiment.chart-data');
    });
