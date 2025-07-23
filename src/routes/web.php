<?php

use Illuminate\Support\Facades\Route;
use Homemove\AbTesting\Http\Controllers\DashboardController;

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
        
        // Debug routes
        Route::post('/clear-session', function () {
            session()->forget('ab_user_id');
            session()->save();
            return response()->json(['success' => true]);
        })->name('clear-session');
    });