<?php

use App\Http\Controllers\Api\V1\Public\AccountOrderController;
use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\Public\MenuController;
use App\Http\Controllers\Api\V1\Public\OrderController;
use App\Http\Controllers\Api\V1\Public\OrderLookupController;
use Illuminate\Support\Facades\Route;

// ===========================================================================
// Public (storefront) API — Phase 3. Append-only; keep all public routes in
// this delimited group. Admin routes must NOT be added here.
// ===========================================================================

Route::prefix('v1')->group(function () {
    // --- Menu (guest accessible) ---
    Route::get('/menu/today', [MenuController::class, 'today'])->name('menu.today');

    // --- Order placement (guest + signed-in, rate-limited) ---
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

    // --- Order lookup (guest) ---
    Route::get('/orders/{orderNumber}', [OrderLookupController::class, 'show'])
        ->name('orders.show');

    // --- Auth (Sanctum) ---
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/account/orders', [AccountOrderController::class, 'index'])->name('account.orders');
    });
});
