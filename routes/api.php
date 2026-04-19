<?php

use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminInventoryReportController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminWarehouseController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PosProductController;
use App\Http\Controllers\Admin\ProductPriceLevelController;
use App\Http\Controllers\Admin\ReportTemplateController;
use App\Http\Controllers\Admin\SavedReportController;
use App\Http\Controllers\Admin\ScheduledReportController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SystemDashboardController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\SuperAdminAuthController;
use App\Http\Controllers\BatchManagementController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAlertConfigController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\InventoryValuationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PriceCalculationController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\PricingTierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseZoneController;
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

// Health check endpoint (no authentication required)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'data' => [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
        ],
    ]);
})->name('api.health');

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

    // Profile Management
    Route::put('/auth/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    Route::put('/auth/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

    // Tenant Management
    Route::get('/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('admin.tenants.show');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('admin.tenants.destroy');
    Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('admin.tenants.activate');
    Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('admin.tenants.suspend');
    Route::get('/tenants/{tenant}/stats', [TenantController::class, 'stats'])->name('admin.tenants.stats');

    // Tenant Subscription Management
    Route::post('/tenants/{tenant}/trial', [TenantController::class, 'updateTrial'])->name('admin.tenants.trial.update');
    Route::post('/tenants/{tenant}/trial/extend', [TenantController::class, 'extendTrial'])->name('admin.tenants.trial.extend');
    Route::post('/tenants/{tenant}/subscription', [TenantController::class, 'updateSubscription'])->name('admin.tenants.subscription.update');
    Route::post('/tenants/{tenant}/subscription/extend', [TenantController::class, 'extendSubscription'])->name('admin.tenants.subscription.extend');
    Route::post('/tenants/{tenant}/subscription/cancel', [TenantController::class, 'cancelSubscription'])->name('admin.tenants.subscription.cancel');
    Route::post('/tenants/{tenant}/convert-to-paid', [TenantController::class, 'convertToPaid'])->name('admin.tenants.convertToPaid');

    // Subscription Analytics & Management
    Route::get('/subscriptions/stats', [SubscriptionController::class, 'stats'])->name('admin.subscriptions.stats');
    Route::get('/subscriptions/expiring', [SubscriptionController::class, 'expiringSoon'])->name('admin.subscriptions.expiring');
    Route::get('/subscriptions/{tenant}/history', [SubscriptionController::class, 'history'])->name('admin.subscriptions.history');
    Route::get('/subscriptions/revenue', [SubscriptionController::class, 'revenue'])->name('admin.subscriptions.revenue');

    // System Dashboard
    Route::get('/dashboard', [SystemDashboardController::class, 'overview'])->name('admin.dashboard.overview');
    Route::get('/dashboard/revenue', [SystemDashboardController::class, 'revenue'])->name('admin.dashboard.revenue');
    Route::get('/dashboard/usage', [SystemDashboardController::class, 'usage'])->name('admin.dashboard.usage');
    Route::get('/dashboard/alerts', [SystemDashboardController::class, 'alerts'])->name('admin.dashboard.alerts');
    Route::get('/dashboard/tenant-trends', [SystemDashboardController::class, 'tenantTrends'])->name('admin.dashboard.tenant-trends');
    Route::get('/dashboard/top-products', [SystemDashboardController::class, 'topProducts'])->name('admin.dashboard.top-products');
    Route::get('/dashboard/customer-analytics', [SystemDashboardController::class, 'customerAnalytics'])->name('admin.dashboard.customer-analytics');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/users/stop-impersonating', [UserController::class, 'stopImpersonating'])->name('admin.users.stopImpersonating');
    Route::get('/users/{user}/impersonation-sessions', [UserController::class, 'impersonationSessions'])->name('admin.users.impersonationSessions');
    Route::post('/users/{user}/revoke-impersonation', [UserController::class, 'revokeImpersonationTokens'])->name('admin.users.revokeImpersonation');

    // POS Products Management
    Route::get('/pos/products', [PosProductController::class, 'index'])->name('admin.pos.products.index');
    Route::get('/pos/products/stats', [PosProductController::class, 'stats'])->name('admin.pos.products.stats');
    Route::get('/pos/products/export', [PosProductController::class, 'export'])->name('admin.pos.products.export');
    Route::post('/pos/products', [PosProductController::class, 'store'])->name('admin.pos.products.store');
    Route::get('/pos/products/{product}', [PosProductController::class, 'show'])->name('admin.pos.products.show');
    Route::put('/pos/products/{product}', [PosProductController::class, 'update'])->name('admin.pos.products.update');
    Route::delete('/pos/products/{product}', [PosProductController::class, 'destroy'])->name('admin.pos.products.destroy');
    Route::post('/pos/products/{product}/toggle-status', [PosProductController::class, 'toggleStatus'])->name('admin.pos.products.toggle-status');
    Route::get('/pos/products/{product}/stock-movements', [PosProductController::class, 'stockMovements'])->name('admin.pos.products.stock-movements');
    Route::get('/pos/products/{product}/orders', [PosProductController::class, 'orders'])->name('admin.pos.products.orders');
    Route::get('/pos/categories', [PosProductController::class, 'categories'])->name('admin.pos.categories');

    // Product Price Levels Management
    Route::get('/pos/products/{product}/price-levels', [ProductPriceLevelController::class, 'index'])->name('admin.pos.products.price-levels.index');
    Route::post('/pos/products/{product}/price-levels', [ProductPriceLevelController::class, 'store'])->name('admin.pos.products.price-levels.store');
    Route::put('/pos/products/{product}/price-levels/{level}', [ProductPriceLevelController::class, 'update'])->name('admin.pos.products.price-levels.update');
    Route::delete('/pos/products/{product}/price-levels/{level}', [ProductPriceLevelController::class, 'destroy'])->name('admin.pos.products.price-levels.destroy');
    Route::post('/pos/products/{product}/price-levels/bulk-update', [ProductPriceLevelController::class, 'bulkUpdate'])->name('admin.pos.products.price-levels.bulk-update');

    // POS Orders Management
    Route::get('/pos/orders', [AdminOrderController::class, 'index'])->name('admin.pos.orders.index');
    Route::get('/pos/orders/stats', [AdminOrderController::class, 'stats'])->name('admin.pos.orders.stats');
    Route::get('/pos/orders/export', [AdminOrderController::class, 'export'])->name('admin.pos.orders.export');
    Route::get('/pos/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.pos.orders.show');
    Route::post('/pos/orders/{order}/confirm', [AdminOrderController::class, 'confirm'])->name('admin.pos.orders.confirm');
    Route::post('/pos/orders/{order}/fulfill', [AdminOrderController::class, 'fulfill'])->name('admin.pos.orders.fulfill');
    Route::post('/pos/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('admin.pos.orders.cancel');

    // WMS Warehouse Management
    Route::get('/wms/warehouses', [AdminWarehouseController::class, 'index'])->name('admin.wms.warehouses.index');
    Route::get('/wms/warehouses/stats', [AdminWarehouseController::class, 'stats'])->name('admin.wms.warehouses.stats');
    Route::post('/wms/warehouses', [AdminWarehouseController::class, 'store'])->name('admin.wms.warehouses.store');
    Route::get('/wms/warehouses/export', [AdminWarehouseController::class, 'export'])->name('admin.wms.warehouses.export');
    Route::get('/wms/warehouses/{warehouse}', [AdminWarehouseController::class, 'show'])->name('admin.wms.warehouses.show');
    Route::put('/wms/warehouses/{warehouse}', [AdminWarehouseController::class, 'update'])->name('admin.wms.warehouses.update');
    Route::post('/wms/warehouses/{warehouse}/toggle-status', [AdminWarehouseController::class, 'toggleStatus'])->name('admin.wms.warehouses.toggle-status');
    Route::delete('/wms/warehouses/{warehouse}', [AdminWarehouseController::class, 'destroy'])->name('admin.wms.warehouses.destroy');

    // POS Store Management
    Route::get('/pos/stores', [AdminStoreController::class, 'index'])->name('admin.pos.stores.index');

    // POS Inventory Management
    Route::get('/pos/inventory', [AdminInventoryController::class, 'index'])->name('admin.pos.inventory.index');
    Route::get('/pos/inventory/stats', [AdminInventoryController::class, 'stats'])->name('admin.pos.inventory.stats');
    Route::get('/pos/inventory/export', [AdminInventoryController::class, 'export'])->name('admin.pos.inventory.export');
    Route::get('/pos/inventory/{inventory}', [AdminInventoryController::class, 'show'])->name('admin.pos.inventory.show');

    // Inventory Reports
    Route::get('/reports/inventory/stock-levels', [AdminInventoryReportController::class, 'stockLevels'])->name('admin.reports.inventory.stock-levels');
    Route::get('/reports/inventory/export/stock-levels', [AdminInventoryReportController::class, 'exportStockLevels'])->name('admin.reports.inventory.export.stock-levels');

    // Stock movements (cross-tenant)
    Route::get('/pos/movements', [StockMovementController::class, 'index'])->name('admin.pos.movements.index');
    Route::get('/pos/movements/stats', [StockMovementController::class, 'stats'])->name('admin.pos.movements.stats');
    Route::get('/pos/movements/export', [StockMovementController::class, 'export'])->name('admin.pos.movements.export');
    Route::get('/pos/movements/{movementId}', [StockMovementController::class, 'show'])->name('admin.pos.movements.show');

    // Batch management (cross-tenant)
    Route::get('/pos/batches', [BatchManagementController::class, 'index'])->name('admin.pos.batches.index');
    Route::get('/pos/batches/stats', [BatchManagementController::class, 'stats'])->name('admin.pos.batches.stats');
    Route::get('/pos/batches/expiring', [BatchManagementController::class, 'expiringBatches'])->name('admin.pos.batches.expiring');
    Route::get('/pos/batches/export', [BatchManagementController::class, 'export'])->name('admin.pos.batches.export');
    Route::get('/pos/batches/{batchId}', [BatchManagementController::class, 'show'])->name('admin.pos.batches.show');
    Route::post('/pos/batches/{batchId}/expire', [BatchManagementController::class, 'expire'])->name('admin.pos.batches.expire');

    // Role Management (Super Admin - Global View)
    Route::get('/roles/all', [RoleController::class, 'globalIndex'])->name('admin.roles.global-index');
    Route::get('/roles/{role}', [RoleController::class, 'globalShow'])->name('admin.roles.global-show');
    Route::post('/roles', [RoleController::class, 'globalStore'])->name('admin.roles.global-store');
    Route::put('/roles/{role}', [RoleController::class, 'globalUpdate'])->name('admin.roles.global-update');
    Route::delete('/roles/{role}', [RoleController::class, 'globalDestroy'])->name('admin.roles.global-destroy');

    // Permissions Management (Super Admin - Global View)
    Route::get('/permissions', [PermissionController::class, 'globalIndex'])->name('admin.permissions.global-index');

    // Global Audit Logs (Super Admin only)
    Route::get('/audit-logs', [AuditLogController::class, 'globalIndex'])->name('admin.audit-logs.index');
    Route::get('/audit-logs/summary', [AuditLogController::class, 'globalSummary'])->name('admin.audit-logs.summary');
    Route::get('/audit-logs/by-user/{userId}', [AuditLogController::class, 'byUser'])->name('admin.audit-logs.byUser');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');

    // Notification Management (Super Admin)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('admin.notifications.unreadCount');
    Route::get('/notifications/stats', [NotificationController::class, 'stats'])->name('admin.notifications.stats');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('admin.notifications.show');
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('admin.notifications.markAsRead');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('admin.notifications.markAllAsRead');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('admin.notifications.destroy');
    Route::post('/notifications/bulk-delete', [NotificationController::class, 'bulkDestroy'])->name('admin.notifications.bulkDestroy');

    // System Configuration
    Route::get('/settings', [SystemSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('/settings', [SystemSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/settings/clear-cache', [SystemSettingsController::class, 'clearCache'])->name('admin.settings.clearCache');
    Route::get('/settings/health', [SystemSettingsController::class, 'health'])->name('admin.settings.health');
    Route::post('/settings/run-command', [SystemSettingsController::class, 'runCommand'])->name('admin.settings.runCommand');

    // Currency Management
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('admin.currencies.index');
    Route::get('/currencies/rates', [CurrencyController::class, 'rates'])->name('admin.currencies.rates');
    Route::post('/currencies/rates', [CurrencyController::class, 'updateRate'])->name('admin.currencies.rates.update');
    Route::delete('/currencies/rates/{rateId}', [CurrencyController::class, 'deleteRate'])->name('admin.currencies.rates.delete');
    Route::post('/currencies/sync-rates', [CurrencyController::class, 'syncRates'])->name('admin.currencies.syncRates');
    Route::post('/currencies/convert', [CurrencyController::class, 'convert'])->name('admin.currencies.convert');
    Route::get('/tenants/{tenant}/currency', [CurrencyController::class, 'tenantCurrency'])->name('admin.tenants.currency');
    Route::put('/tenants/{tenant}/currency', [CurrencyController::class, 'updateTenantCurrency'])->name('admin.tenants.currency.update');

    // Report Templates Management
    Route::get('/reports/templates/types', [ReportTemplateController::class, 'types'])->name('admin.reports.templates.types');
    Route::get('/reports/templates', [ReportTemplateController::class, 'index'])->name('admin.reports.templates.index');
    Route::post('/reports/templates', [ReportTemplateController::class, 'store'])->name('admin.reports.templates.store');
    Route::get('/reports/templates/{template}', [ReportTemplateController::class, 'show'])->name('admin.reports.templates.show');
    Route::put('/reports/templates/{template}', [ReportTemplateController::class, 'update'])->name('admin.reports.templates.update');
    Route::delete('/reports/templates/{template}', [ReportTemplateController::class, 'destroy'])->name('admin.reports.templates.destroy');
    Route::post('/reports/templates/{template}/duplicate', [ReportTemplateController::class, 'duplicate'])->name('admin.reports.templates.duplicate');

    // Saved Reports Management
    Route::get('/reports/saved', [SavedReportController::class, 'index'])->name('admin.reports.saved.index');
    Route::post('/reports/saved', [SavedReportController::class, 'store'])->name('admin.reports.saved.store');
    Route::get('/reports/saved/stats', [SavedReportController::class, 'stats'])->name('admin.reports.saved.stats');
    Route::get('/reports/saved/{saved_report}', [SavedReportController::class, 'show'])->name('admin.reports.saved.show');
    Route::put('/reports/saved/{saved_report}', [SavedReportController::class, 'update'])->name('admin.reports.saved.update');
    Route::delete('/reports/saved/{saved_report}', [SavedReportController::class, 'destroy'])->name('admin.reports.saved.destroy');
    Route::get('/reports/saved/{saved_report}/download', [SavedReportController::class, 'download'])->name('admin.reports.saved.download');

    // Scheduled Reports Management
    Route::get('/reports/schedules', [ScheduledReportController::class, 'index'])->name('admin.reports.schedules.index');
    Route::post('/reports/schedules', [ScheduledReportController::class, 'store'])->name('admin.reports.schedules.store');
    Route::get('/reports/schedules/{scheduledReport}', [ScheduledReportController::class, 'show'])->name('admin.reports.schedules.show');
    Route::put('/reports/schedules/{scheduledReport}', [ScheduledReportController::class, 'update'])->name('admin.reports.schedules.update');
    Route::delete('/reports/schedules/{scheduledReport}', [ScheduledReportController::class, 'destroy'])->name('admin.reports.schedules.destroy');
    Route::post('/reports/schedules/{scheduledReport}/run', [ScheduledReportController::class, 'run'])->name('admin.reports.schedules.run');
    Route::get('/reports/schedules/{scheduledReport}/history', [ScheduledReportController::class, 'history'])->name('admin.reports.schedules.history');

    // Analytics Endpoints
    Route::get('/analytics/sales/trend', [AnalyticsController::class, 'salesTrend'])->name('admin.analytics.sales.trend');
    Route::get('/analytics/orders/status-distribution', [AnalyticsController::class, 'orderStatusDistribution'])->name('admin.analytics.orders.status');
    Route::get('/analytics/inventory/level-distribution', [AnalyticsController::class, 'inventoryLevelDistribution'])->name('admin.analytics.inventory.levels');
    Route::get('/analytics/products/top', [AnalyticsController::class, 'topProducts'])->name('admin.analytics.products.top');
    Route::get('/analytics/customers/segments', [AnalyticsController::class, 'customerSegments'])->name('admin.analytics.customers.segments');
    Route::get('/analytics/tenants/comparison', [AnalyticsController::class, 'tenantComparison'])->name('admin.analytics.tenants.comparison');
    Route::get('/analytics/activity/heatmap', [AnalyticsController::class, 'activityHeatmap'])->name('admin.analytics.activity.heatmap');
    Route::get('/analytics/inventory/by-warehouse', [AnalyticsController::class, 'inventoryByWarehouse'])->name('admin.analytics.inventory.warehouse');
    Route::get('/analytics/revenue/recurring', [AnalyticsController::class, 'recurringRevenue'])->name('admin.analytics.revenue.recurring');

    // API Key Management
    Route::get('/tenants/{tenant}/api-keys', [ApiKeyController::class, 'index'])->name('admin.api-keys.index');
    Route::post('/tenants/{tenant}/api-keys', [ApiKeyController::class, 'store'])->name('admin.api-keys.store');
    Route::get('/tenants/{tenant}/api-keys/stats', [ApiKeyController::class, 'stats'])->name('admin.api-keys.stats');
    Route::get('/tenants/{tenant}/api-keys/{apiKey}', [ApiKeyController::class, 'show'])->name('admin.api-keys.show');
    Route::put('/tenants/{tenant}/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->name('admin.api-keys.update');
    Route::delete('/tenants/{tenant}/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('admin.api-keys.destroy');
    Route::post('/tenants/{tenant}/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->name('admin.api-keys.regenerate');
});

// Protected routes (require Sanctum authentication and tenant scoping)
Route::middleware(['auth:sanctum', 'tenant.scoped', 'throttle:api'])->prefix('tenants/{tenant_id}')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/refresh', [LoginController::class, 'refresh'])->name('auth.refresh');
    Route::get('/auth/me', [LoginController::class, 'me'])->name('auth.me');

    // Profile Management
    Route::put('/auth/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/auth/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

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
        // Note: Webhook test endpoint has stricter rate limiting (throttle:api-webhook-test)
        Route::apiResource('webhooks', WebhookController::class);
        Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])
            ->middleware('throttle:api-webhook-test');
        Route::get('/webhooks/{webhook}/attempts', [WebhookController::class, 'deliveryAttempts']);
        Route::post('/webhooks/{webhook}/retry', [WebhookController::class, 'retry']);

        // Inventory report exports (admin only)
        // These endpoints are resource-heavy and have stricter rate limiting (throttle:api-exports)
        Route::get('/reports/inventory/export/stock-levels', [InventoryReportController::class, 'exportStockLevels'])
            ->middleware('throttle:api-exports');
        Route::get('/reports/inventory/export/low-stock', [InventoryReportController::class, 'exportLowStock'])
            ->middleware('throttle:api-exports');

        // Sales report exports (admin only)
        // These endpoints are resource-heavy and have stricter rate limiting (throttle:api-exports)
        Route::get('/reports/sales/export/revenue', [SalesReportController::class, 'exportRevenue'])
            ->middleware('throttle:api-exports');
        Route::get('/reports/sales/export/orders-by-period', [SalesReportController::class, 'exportOrdersByPeriod'])
            ->middleware('throttle:api-exports');
        Route::get('/reports/sales/export/top-products', [SalesReportController::class, 'exportTopProducts'])
            ->middleware('throttle:api-exports');
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

    // Stock adjustments
    Route::post('/inventory/adjust', [StockAdjustmentController::class, 'adjust']);
    Route::get('/inventory/{inventoryId}/adjustments', [StockAdjustmentController::class, 'history']);

    // Stock movements
    Route::get('/movements', [StockMovementController::class, 'index']);
    Route::get('/movements/stats', [StockMovementController::class, 'stats']);
    Route::get('/movements/export', [StockMovementController::class, 'export']);
    Route::get('/movements/{movementId}', [StockMovementController::class, 'show']);

    // Batch management
    Route::get('/batches', [BatchManagementController::class, 'index']);
    Route::get('/batches/stats', [BatchManagementController::class, 'stats']);
    Route::get('/batches/expiring', [BatchManagementController::class, 'expiringBatches']);
    Route::get('/batches/export', [BatchManagementController::class, 'export']);
    Route::get('/batches/{batchId}', [BatchManagementController::class, 'show']);
    Route::post('/batches/{batchId}/expire', [BatchManagementController::class, 'expire']);

    // Inventory counts
    Route::get('/counts', [InventoryCountController::class, 'index']);
    Route::post('/counts', [InventoryCountController::class, 'store']);
    Route::get('/counts/{countId}', [InventoryCountController::class, 'show']);
    Route::post('/counts/{countId}/start', [InventoryCountController::class, 'start']);
    Route::post('/counts/{countId}/items/{itemId}', [InventoryCountController::class, 'recordItem']);
    Route::post('/counts/{countId}/complete', [InventoryCountController::class, 'complete']);
    Route::post('/counts/{countId}/approve', [InventoryCountController::class, 'approve']);
    Route::post('/counts/{countId}/cancel', [InventoryCountController::class, 'cancel']);
    Route::delete('/counts/{countId}', [InventoryCountController::class, 'destroy']);

    // Inventory valuation (requires reports.view permission)
    Route::middleware(['permission:reports.view'])->group(function () {
        Route::get('/reports/inventory/valuation', [InventoryValuationController::class, 'valuation']);
        Route::get('/reports/inventory/valuation/export', [InventoryValuationController::class, 'exportValuation']);
        Route::get('/reports/inventory/cogs', [InventoryValuationController::class, 'cogs']);
        Route::get('/reports/inventory/weighted-average', [InventoryValuationController::class, 'weightedAverageCost']);
        Route::get('/reports/inventory/value-trends', [InventoryValuationController::class, 'valueTrends']);
        Route::post('/reports/inventory/reconcile', [InventoryValuationController::class, 'reconcile']);
    });

    // Inventory reports
    Route::get('/reports/inventory/low-stock', [InventoryReportController::class, 'lowStock']);
    Route::get('/reports/inventory', [InventoryReportController::class, 'report']);
    Route::get('/reports/inventory/stock-levels', [InventoryReportController::class, 'stockLevels']);

    // Inventory alert configurations
    Route::get('/inventory/alert-configs', [InventoryAlertConfigController::class, 'index']);
    Route::post('/inventory/alert-configs', [InventoryAlertConfigController::class, 'store']);
    Route::get('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'show']);
    Route::put('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'update']);
    Route::delete('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'destroy']);
    Route::post('/inventory/alert-configs/{configId}/add-recipient', [InventoryAlertConfigController::class, 'addRecipient']);
    Route::post('/inventory/alert-configs/{configId}/remove-recipient', [InventoryAlertConfigController::class, 'removeRecipient']);

    // Warehouse zones
    Route::get('/warehouses/{warehouseId}/zones', [WarehouseZoneController::class, 'index']);
    Route::post('/warehouses/{warehouseId}/zones', [WarehouseZoneController::class, 'store']);
    Route::get('/warehouses/{warehouseId}/zones/stats', [WarehouseZoneController::class, 'stats']);
    Route::get('/warehouses/{warehouseId}/zones/{zoneId}', [WarehouseZoneController::class, 'show']);
    Route::put('/warehouses/{warehouseId}/zones/{zoneId}', [WarehouseZoneController::class, 'update']);
    Route::delete('/warehouses/{warehouseId}/zones/{zoneId}', [WarehouseZoneController::class, 'destroy']);

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
