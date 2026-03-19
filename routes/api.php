<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\PricingTierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned to "api" middleware group and the "api/v1" prefix.
|
*/

// Public routes
Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');

// Protected routes (require Sanctum authentication and tenant scoping)
Route::middleware(['auth:sanctum', 'tenant.scoped'])->prefix('tenants/{tenant_id}')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/refresh', [LoginController::class, 'refresh'])->name('auth.refresh');
    Route::get('/auth/me', [LoginController::class, 'me'])->name('auth.me');

    // Admin-only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('/users/{userId}/assign-role', [RoleController::class, 'assignToUser']);
        Route::delete('/users/{userId}/remove-role/{roleId}', [RoleController::class, 'removeFromUser']);
        Route::apiResource('permissions', PermissionController::class);
        Route::apiResource('pricing-tiers', PricingTierController::class);
        Route::apiResource('pricing-rules', PricingRuleController::class);
    });

    // Core entity routes
    Route::apiResource('stores', StoreController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('inventory', InventoryController::class);
    Route::apiResource('orders', OrderController::class);

    // Order actions
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/fulfill', [OrderController::class, 'fulfill']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // Test routes for authorization
    Route::get('/admin-only', fn () => response()->json(['message' => 'Admin access granted']))->middleware('role:admin');
    Route::get('/admin-or-manager', fn () => response()->json(['message' => 'Access granted']))->middleware('role:admin,manager');
    Route::post('/products/create-or-edit', fn () => response()->json(['message' => 'Access granted']))->middleware('permission:products.create,products.edit');
});
