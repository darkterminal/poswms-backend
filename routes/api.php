<?php

use App\Http\Controllers\Admin\SystemDashboardController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SuperAdminAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PriceCalculationController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\PricingTierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WebhookController;
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

// Public routes with auth rate limiting
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');

    // Super Admin login (separate endpoint)
    Route::post('/admin/auth/login', [SuperAdminAuthController::class, 'login'])->name('admin.auth.login');
});

// Super Admin routes (require Sanctum authentication and super admin role)
Route::middleware(['auth:sanctum', 'superadmin', 'throttle:api-admin'])->prefix('admin')->group(function () {
    // Super Admin Auth routes
    Route::post('/auth/logout', [SuperAdminAuthController::class, 'logout'])->name('admin.auth.logout');
    Route::get('/auth/me', [SuperAdminAuthController::class, 'me'])->name('admin.auth.me');

    // Tenant Management
    Route::get('/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('admin.tenants.show');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('admin.tenants.destroy');
    Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('admin.tenants.activate');
    Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('admin.tenants.suspend');
    Route::get('/tenants/{tenant}/stats', [TenantController::class, 'stats'])->name('admin.tenants.stats');

    // System Dashboard
    Route::get('/dashboard', [SystemDashboardController::class, 'overview'])->name('admin.dashboard.overview');
    Route::get('/dashboard/revenue', [SystemDashboardController::class, 'revenue'])->name('admin.dashboard.revenue');
    Route::get('/dashboard/usage', [SystemDashboardController::class, 'usage'])->name('admin.dashboard.usage');
    Route::get('/dashboard/alerts', [SystemDashboardController::class, 'alerts'])->name('admin.dashboard.alerts');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/users/stop-impersonating', [UserController::class, 'stopImpersonating'])->name('admin.users.stopImpersonating');
    Route::get('/users/{user}/impersonation-sessions', [UserController::class, 'impersonationSessions'])->name('admin.users.impersonationSessions');
    Route::post('/users/{user}/revoke-impersonation', [UserController::class, 'revokeImpersonationTokens'])->name('admin.users.revokeImpersonation');

    // Global Audit Logs (Super Admin only)
    Route::get('/audit-logs', [AuditLogController::class, 'globalIndex'])->name('admin.audit-logs.index');
    Route::get('/audit-logs/summary', [AuditLogController::class, 'globalSummary'])->name('admin.audit-logs.summary');
    Route::get('/audit-logs/by-user/{userId}', [AuditLogController::class, 'byUser'])->name('admin.audit-logs.byUser');
});

// Protected routes (require Sanctum authentication and tenant scoping)
Route::middleware(['auth:sanctum', 'tenant.scoped', 'throttle:api'])->prefix('tenants/{tenant_id}')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/refresh', [LoginController::class, 'refresh'])->name('auth.refresh');
    Route::get('/auth/me', [LoginController::class, 'me'])->name('auth.me');

    // Admin-only routes with higher rate limits
    Route::middleware(['role:admin', 'throttle:api-admin'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('/users/{userId}/assign-role', [RoleController::class, 'assignToUser']);
        Route::delete('/users/{userId}/remove-role/{roleId}', [RoleController::class, 'removeFromUser']);
        Route::apiResource('permissions', PermissionController::class);
        Route::apiResource('pricing-tiers', PricingTierController::class);
        Route::apiResource('pricing-rules', PricingRuleController::class);

        // Audit log routes (admin only)
        Route::get('/audit-logs/summary', [AuditLogController::class, 'summary']);
        Route::get('/audit-logs/by-user/{userId}', [AuditLogController::class, 'byUser']);
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);

        // Webhook routes (admin only)
        Route::apiResource('webhooks', WebhookController::class);
        Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test']);
        Route::get('/webhooks/{webhook}/attempts', [WebhookController::class, 'deliveryAttempts']);
        Route::post('/webhooks/{webhook}/retry', [WebhookController::class, 'retry']);

        // Inventory report exports (admin only)
        Route::get('/reports/inventory/export/stock-levels', [InventoryReportController::class, 'exportStockLevels']);
        Route::get('/reports/inventory/export/movements', [InventoryReportController::class, 'exportMovements']);
        Route::get('/reports/inventory/export/low-stock', [InventoryReportController::class, 'exportLowStock']);

        // Sales report exports (admin only)
        Route::get('/reports/sales/export/revenue', [SalesReportController::class, 'exportRevenue']);
        Route::get('/reports/sales/export/orders-by-period', [SalesReportController::class, 'exportOrdersByPeriod']);
        Route::get('/reports/sales/export/top-products', [SalesReportController::class, 'exportTopProducts']);
    });

    // Core entity routes (manually defined to avoid route model binding conflicts)
    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/{storeId}', [StoreController::class, 'show']);
    Route::put('/stores/{storeId}', [StoreController::class, 'update']);
    Route::delete('/stores/{storeId}', [StoreController::class, 'destroy']);

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::post('/warehouses', [WarehouseController::class, 'store']);
    Route::get('/warehouses/{warehouseId}', [WarehouseController::class, 'show']);
    Route::put('/warehouses/{warehouseId}', [WarehouseController::class, 'update']);
    Route::delete('/warehouses/{warehouseId}', [WarehouseController::class, 'destroy']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{categoryId}', [CategoryController::class, 'show']);
    Route::put('/categories/{categoryId}', [CategoryController::class, 'update']);
    Route::delete('/categories/{categoryId}', [CategoryController::class, 'destroy']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{productId}', [ProductController::class, 'show']);
    Route::put('/products/{productId}', [ProductController::class, 'update']);
    Route::delete('/products/{productId}', [ProductController::class, 'destroy']);

    // Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customerId}', [CustomerController::class, 'show']);
    Route::put('/customers/{customerId}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customerId}', [CustomerController::class, 'destroy']);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::get('/inventory/{inventoryId}', [InventoryController::class, 'show']);
    Route::put('/inventory/{inventoryId}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{inventoryId}', [InventoryController::class, 'destroy']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);
    Route::put('/orders/{orderId}', [OrderController::class, 'update']);
    Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);

    // Inventory actions
    Route::post('/inventory/transfer', [InventoryTransferController::class, 'transfer']);
    Route::get('/inventory/product/{productId}/transferable', [InventoryTransferController::class, 'getTransferableInventory']);

    // Inventory reports
    Route::get('/reports/inventory/low-stock', [InventoryReportController::class, 'lowStock']);
    Route::get('/reports/inventory', [InventoryReportController::class, 'report']);
    Route::get('/reports/inventory/stock-levels', [InventoryReportController::class, 'stockLevels']);
    Route::get('/reports/inventory/movements', [InventoryReportController::class, 'movements']);

    // Sales reports
    Route::get('/reports/sales/revenue', [SalesReportController::class, 'revenue']);
    Route::get('/reports/sales/orders-by-period', [SalesReportController::class, 'ordersByPeriod']);
    Route::get('/reports/sales/top-products', [SalesReportController::class, 'topProducts']);
    Route::get('/reports/sales/dashboard', [SalesReportController::class, 'dashboardMetrics']);

    // Dashboard (unified metrics)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Order actions (using custom parameter name to avoid route model binding conflicts)
    Route::post('/orders/{orderId}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{orderId}/fulfill', [OrderController::class, 'fulfill']);
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel']);

    // Price calculation routes
    Route::post('/prices/calculate', [PriceCalculationController::class, 'calculate']);
    Route::post('/prices/calculate-cart', [PriceCalculationController::class, 'calculateCart']);

    // Test routes for authorization
    Route::get('/admin-only', fn() => response()->json(['message' => 'Admin access granted']))->middleware('role:admin');
    Route::get('/admin-or-manager', fn() => response()->json(['message' => 'Access granted']))->middleware('role:admin,manager');
    Route::post('/products/create-or-edit', fn() => response()->json(['message' => 'Access granted']))->middleware('permission:products.create,products.edit');
});

/*
|--------------------------------------------------------------------------
| API Documentation Routes
|--------------------------------------------------------------------------
|
| These routes serve the Swagger UI and OpenAPI specification.
| They are placed outside the tenant-scoped group to be publicly accessible.
|
*/

Route::get('/docs/openapi.json', function () {
    $openApiPath = base_path('swagger/openapi.yaml');

    if (! file_exists($openApiPath)) {
        return response()->json([
            'error' => 'OpenAPI specification not found',
        ], 404);
    }

    $yamlContent = file_get_contents($openApiPath);

    // Parse YAML to array
    $openApiSpec = Symfony\Component\Yaml\Yaml::parse($yamlContent);

    return response()->json($openApiSpec);
})->name('api.docs.openapi');
