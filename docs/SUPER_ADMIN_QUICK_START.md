# Super Admin Module - Quick Start Guide

## Overview

This guide helps you quickly start implementing the Super Admin API module following the established development workflow.

---

## Quick Commands

### Start Development Session
```bash
composer session:start
```

### Create Controllers
```bash
php artisan make:controller Admin/Auth/LoginController
php artisan make:controller Admin/TenantController --resource
php artisan make:controller Admin/DashboardController
php artisan make:controller Admin/UserController
```

### Create Form Requests
```bash
php artisan make:request Admin/CreateTenantRequest
php artisan make:request Admin/UpdateTenantRequest
php artisan make:request Admin/ImpersonateUserRequest
```

### Create Resources
```bash
php artisan make:resource Admin/TenantResource
php artisan make:resource Admin/TenantCollection
php artisan make:resource Admin/UserResource
php artisan make:resource Admin/DashboardResource
```

### Create Tests
```bash
php artisan make:test Admin/AuthTest
php artisan make:test Admin/TenantManagementTest
php artisan make:test Admin/DashboardTest
php artisan make:test Admin/ImpersonationTest
```

### Run Tests
```bash
# Run specific test
php artisan test --compact tests/Feature/Admin/AuthTest.php

# Run all admin tests
php artisan test --compact --filter=Admin

# Run with filter
php artisan test --compact --filter=TenantManagement
```

### Code Formatting
```bash
vendor/bin/pint --format agent
```

### End Session
```bash
composer session:end
```

---

## File Checklist

### Phase 9.1: Authentication
- [ ] `config/auth.php` - Add super_admin guard
- [ ] `app/Http/Middleware/EnsureSuperAdmin.php`
- [ ] `app/Models/User.php` - Add isSuperAdmin() method
- [ ] `app/Http/Controllers/Admin/Auth/LoginController.php`
- [ ] `database/seeders/SuperAdminSeeder.php`
- [ ] `tests/Feature/Admin/AuthTest.php`
- [ ] Update `routes/api.php`

### Phase 9.2: Tenant Management
- [ ] `app/Http/Controllers/Admin/TenantController.php`
- [ ] `app/Http/Requests/Admin/CreateTenantRequest.php`
- [ ] `app/Http/Requests/Admin/UpdateTenantRequest.php`
- [ ] `app/Http/Resources/Admin/TenantResource.php`
- [ ] `app/Http/Resources/Admin/TenantCollection.php`
- [ ] `app/Services/TenantStatsService.php`
- [ ] `tests/Feature/Admin/TenantManagementTest.php`

### Phase 9.3: Dashboard
- [ ] `app/Http/Controllers/Admin/DashboardController.php`
- [ ] `app/Services/DashboardMetricsService.php`
- [ ] `app/Http/Resources/Admin/DashboardResource.php`
- [ ] `tests/Feature/Admin/DashboardTest.php`

### Phase 9.4: Advanced Features
- [ ] `app/Http/Controllers/Admin/UserController.php`
- [ ] `app/Services/ImpersonationService.php`
- [ ] `app/Http/Resources/Admin/UserResource.php`
- [ ] `tests/Feature/Admin/ImpersonationTest.php`

---

## API Routes Template

```php
// Super Admin Routes
Route::prefix('admin')->middleware(['throttle:api-admin'])->group(function () {
    // Public auth routes
    Route::middleware(['throttle:auth'])->group(function () {
        Route::post('/auth/login', [Admin\Auth\LoginController::class, 'login']);
    });
    
    // Protected admin routes
    Route::middleware(['auth:sanctum:super_admin', 'super.admin'])->group(function () {
        // Auth
        Route::post('/auth/logout', [Admin\Auth\LoginController::class, 'logout']);
        Route::get('/auth/me', [Admin\Auth\LoginController::class, 'me']);
        
        // Dashboard
        Route::get('/dashboard', [Admin\DashboardController::class, 'index']);
        
        // Tenant Management
        Route::apiResource('tenants', Admin\TenantController::class);
        Route::post('/tenants/{tenant}/activate', [Admin\TenantController::class, 'activate']);
        Route::post('/tenants/{tenant}/suspend', [Admin\TenantController::class, 'suspend']);
        Route::get('/tenants/{tenant}/stats', [Admin\TenantController::class, 'stats']);
        
        // User Management
        Route::get('/users', [Admin\UserController::class, 'index']);
        Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate']);
    });
});
```

---

## Middleware Registration

Add to `app/Bootstrap/App.php` or `app/Http/Kernel.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
    ]);
})
```

---

## Testing Checklist

### Authentication Tests
- [ ] Super admin can login
- [ ] Invalid credentials return error
- [ ] Non-admin cannot access admin routes
- [ ] Auth me returns correct data
- [ ] Logout invalidates token

### Tenant Management Tests
- [ ] List tenants with pagination
- [ ] Filter tenants by status
- [ ] Search tenants by name
- [ ] Create tenant
- [ ] Update tenant
- [ ] Soft delete tenant
- [ ] Activate suspended tenant
- [ ] Suspend active tenant
- [ ] Get tenant statistics

### Dashboard Tests
- [ ] Dashboard returns overview metrics
- [ ] Dashboard returns revenue metrics
- [ ] Dashboard returns usage metrics
- [ ] Dashboard returns alerts
- [ ] Metrics are accurate

### Impersonation Tests
- [ ] Super admin can impersonate user
- [ ] Impersonation token is valid
- [ ] Non-admin cannot impersonate
- [ ] Impersonation logs audit entry

---

## Development Workflow

1. **Before Starting:**
   ```bash
   composer session:start
   # Update docs/progress.json - set task to in_progress
   # Update docs/PROGRESS_TRACKER.md - mark task as 🔄
   ```

2. **During Development:**
   - Write tests first (TDD)
   - Implement feature
   - Run tests frequently
   - Apply Pint formatting

3. **After Completion:**
   ```bash
   vendor/bin/pint --format agent
   php artisan test --compact --filter=YourTest
   composer session:end
   # Update docs/progress.json - set task to completed
   # Update docs/PROGRESS_TRACKER.md - mark task as ✅
   # Complete session log in docs/session-logs/session-026.md
   ```

---

## Default Credentials (Development)

```
Email: superadmin@poswms.local
Password: SuperAdmin123!

IMPORTANT: Change after first login!
```

Run seeder:
```bash
php artisan db:seed --class=SuperAdminSeeder
```

---

## Key Implementation Notes

### Security
- Super Admin uses separate guard (`auth:sanctum:super_admin`)
- All admin routes require `EnsureSuperAdmin` middleware
- Stricter rate limiting (200/min vs 100/min)
- All actions logged to audit_logs

### Data Protection
- Tenants use soft deletes (data retention)
- No cascade deletes (preserve referential integrity)
- Impersonation tokens short-lived (15 minutes)

### Performance
- Use eager loading for relationships
- Cache dashboard metrics (5 minutes)
- Paginate all list endpoints (default 15 items)

---

## Troubleshooting

### Issue: 403 Forbidden on admin routes
**Solution:** Ensure user has `role = 'super_admin'` and is authenticated with correct guard

### Issue: Guard not found
**Solution:** Check `config/auth.php` has `super_admin` guard defined

### Issue: Middleware not applied
**Solution:** Verify middleware alias registered in `bootstrap/app.php`

### Issue: Tests failing with authentication
**Solution:** Use `Sanctum::actingAs()` with correct guard in tests

---

## Next Steps

1. ✅ Review `SUPER_ADMIN_WORKFLOW.md` for detailed specifications
2. ✅ Check `progress.json` for task status
3. ✅ Open `session-026.md` for session tracking
4. 🚀 Start with Phase 9.1: Super Admin Authentication

---

**Related Documents:**
- `SUPER_ADMIN_WORKFLOW.md` - Complete development workflow
- `DEVELOPMENT_ROADMAP.md` - Overall project roadmap
- `API_DESIGN.md` - API design principles
- `TRACKING_GUIDE.md` - Progress tracking guide
