<?php

use Illuminate\Support\Facades\Route;
use Homemove\AbTesting\Http\Controllers\DashboardController;
use Homemove\AbTesting\Http\Controllers\TestController;

Route::prefix('ab-testing')
    ->name('ab-testing.')
    ->middleware(['web'])
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
            });
        
        // Test page
        Route::get('/test', [TestController::class, 'index'])->name('test');
        
        // Debug routes
        Route::post('/clear-session', function () {
            // Clear session
            session()->forget('ab_user_id');
            session()->save();
            
            // Clear A/B testing cookie
            setcookie('ab_user_id', '', time() - 3600, '/', '', false, true);
            
            return response()->json(['success' => true]);
        })->name('clear-session');
    });