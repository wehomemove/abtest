<?php

use Illuminate\Support\Facades\Route;
use Homemove\AbTesting\Http\Controllers\DashboardController;
use Homemove\AbTesting\Http\Controllers\TestController;

Route::prefix('ab-testing')
    ->name('ab-testing.')
    ->middleware(config('ab-testing.routes.dashboard_middleware', ['web']))
    ->group(function () {
        Route::prefix('dashboard')
            ->name('dashboard.')
            ->group(function () {
                Route::get('/', [DashboardController::class, 'index'])->name('index');
                Route::get('/create', [DashboardController::class, 'create'])->name('create');
                Route::post('/', [DashboardController::class, 'store'])->name('store');
                Route::get('/{experiment}', [DashboardController::class, 'show'])->name('show');
                Route::get('/{experiment}/edit', [DashboardController::class, 'edit'])->name('edit');
                Route::put('/{experiment}', [DashboardController::class, 'update'])->name('update');
                Route::delete('/{experiment}', [DashboardController::class, 'destroy'])->name('destroy');
                Route::patch('/{experiment}/toggle', [DashboardController::class, 'toggleStatus'])->name('toggle');
                Route::post('/{experiment}/primary-metric', [DashboardController::class, 'setPrimaryMetric'])->name('primary-metric');
                Route::post('/{experiment}/accept', [DashboardController::class, 'accept'])->name('accept');
                Route::post('/{experiment}/reopen', [DashboardController::class, 'reopen'])->name('reopen');
            });

        // Test page
        Route::get('/test', [TestController::class, 'index'])->name('test');

        // Debug routes
        Route::post('/clear-session', function () {
            $sessionKey = config('ab-testing.session_key', 'ab_user_id');

            // Clear session
            session()->forget($sessionKey);
            session()->save();

            // Clear A/B testing cookie
            setcookie($sessionKey, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => config('ab-testing.cookie.secure') ?? request()->isSecure(),
                'httponly' => true,
                'samesite' => config('ab-testing.cookie.same_site', 'Lax'),
            ]);

            return response()->json(['success' => true]);
        })->name('clear-session');
    });
