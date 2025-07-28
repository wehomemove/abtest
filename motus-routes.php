<?php

/**
 * Add these routes to your Motus project's routes/web.php file:
 */

use App\Http\Controllers\AbTestController;

// A/B Testing test routes
Route::get('/ab-test', [AbTestController::class, 'testPage'])->name('ab-test.page');
Route::post('/ab-test/api-test', [AbTestController::class, 'apiTest'])->name('ab-test.api');