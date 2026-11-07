<?php

use App\Http\Controllers\Api\V1\Admin\ExtraController;
use App\Http\Controllers\Api\V1\Admin\MealController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Public\AccountOrderController;
use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\Public\MenuController;
use App\Http\Controllers\Api\V1\Public\OrderController as PublicOrderController;
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
    Route::post('/orders', [PublicOrderController::class, 'store'])->name('orders.store');

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

// ===========================================================================
// Admin API — Phase 4 (owner only). Append-only; owned by the admin worker.
// ===========================================================================

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'role:owner'])
    ->group(function (): void {
        // T4.1 — daily meal & extras management
        Route::post('/meals', [MealController::class, 'store']);
        Route::put('/meals/{date}', [MealController::class, 'update']);
        Route::delete('/meals/{date}', [MealController::class, 'destroy']);

        Route::get('/meals/today/extras', [ExtraController::class, 'index']);
        Route::post('/meals/today/extras', [ExtraController::class, 'store']);
        Route::get('/extras/{extra}', [ExtraController::class, 'show']);
        Route::put('/extras/{extra}', [ExtraController::class, 'update']);
        Route::delete('/extras/{extra}', [ExtraController::class, 'destroy']);

        // T4.2 — settings & cutoffs
        Route::get('/settings', [SettingController::class, 'show']);
        Route::put('/settings', [SettingController::class, 'update']);

        // T4.3 — orders dashboard
        Route::get('/orders', [OrderController::class, 'index']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    });
