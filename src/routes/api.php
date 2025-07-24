<?php

use Illuminate\Support\Facades\Route;
use Homemove\AbTesting\Http\Controllers\ApiController;

Route::prefix('api/ab-testing')
    ->name('ab-testing.api.')
    ->middleware(['api'])
    ->group(function () {
        Route::post('/track', [ApiController::class, 'track'])->name('track');
        Route::post('/variant', [ApiController::class, 'getVariant'])->name('variant');
        Route::post('/register-debug', [ApiController::class, 'registerDebugExperiment'])->name('register-debug');
        Route::get('/experiments/{experiment}/results', [ApiController::class, 'getResults'])->name('results');
    });