<?php

use App\Http\Controllers\Api\V1\Admin\ExtraController;
use App\Http\Controllers\Api\V1\Admin\MealController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use Illuminate\Support\Facades\Route;

// ==== PUBLIC (STOREFRONT) ROUTES — owned by the Phase 3 storefront worker. Do not edit below. ====
// ==== END PUBLIC ROUTES ====

// ==== ADMIN ROUTES — owned by the Phase 4 admin worker. Append only. ====
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
// ==== END ADMIN ROUTES ====
