# Super Admin API Module - Development Workflow

**Document Version:** 1.0  
**Created:** March 21, 2026  
**Phase:** Phase 9 - Super Admin Module (New)  
**Priority:** 🔴 CRITICAL  

---

## Overview

This document defines the development workflow for implementing the **Super Admin API Module**, enabling SaaS owners to manage all tenants, view system-wide analytics, and perform cross-tenant operations.

### Why This Module is Critical

As a SaaS platform, the system requires **Super Admin capabilities** for:
- **Tenant Management**: Onboard, suspend, activate, and manage customer businesses
- **System Oversight**: Monitor platform health, usage, and revenue
- **Cross-Tenant Operations**: Impersonate users for support, resolve issues
- **Subscription Management**: Control trials, plans, and billing

---

## Module Scope

### In Scope
✅ Super Admin authentication (separate from tenant auth)  
✅ Tenant CRUD operations (global, not tenant-scoped)  
✅ System-wide dashboard with aggregated metrics  
✅ Tenant subscription and trial management  
✅ Cross-tenant user search and impersonation  
✅ System-wide audit logs  
✅ Global configuration management  

### Out of Scope
❌ Tenant-internal operations (already exist in tenant-scoped routes)  
❌ Frontend admin panel (API-only, frontend is separate)  
❌ Billing/payment gateway integration (future phase)  
❌ Email notification system (uses existing notification infrastructure)  

---

## Development Phases

```
Phase 9.1 → Phase 9.2 → Phase 9.3 → Phase 9.4 → Phase 9.5
```

### Phase 9.1: Super Admin Authentication & Middleware
**Goal:** Establish Super Admin security layer

| Task ID | Task | Description | Effort |
|---------|------|-------------|--------|
| 9.1.1 | Super Admin Guard | Create `auth:sanctum.superadmin` guard | 1h |
| 9.1.2 | Super Admin Middleware | Create `EnsureSuperAdmin` middleware | 1h |
| 9.1.3 | Super Admin User Seeder | Seed default super admin user | 0.5h |
| 9.1.4 | Auth Endpoints | `/api/v1/admin/auth/login`, `/logout`, `/me` | 1.5h |
| 9.1.5 | Auth Tests | Comprehensive authentication tests | 1h |

**Total:** ~5 hours

### Phase 9.2: Tenant Management API
**Goal:** Full CRUD operations for tenants

| Task ID | Task | Description | Effort |
|---------|------|-------------|--------|
| 9.2.1 | TenantController | Admin/TenantController with CRUD methods | 2h |
| 9.2.2 | List Tenants | GET `/api/v1/admin/tenants` with filters/pagination | 1h |
| 9.2.3 | Create Tenant | POST `/api/v1/admin/tenants` | 1h |
| 9.2.4 | View Tenant | GET `/api/v1/admin/tenants/{id}` | 0.5h |
| 9.2.5 | Update Tenant | PUT `/api/v1/admin/tenants/{id}` | 0.5h |
| 9.2.6 | Delete Tenant | DELETE `/api/v1/admin/tenants/{id}` (soft delete) | 0.5h |
| 9.2.7 | Activate/Suspend | POST `/api/v1/admin/tenants/{id}/activate`, `/suspend` | 1h |
| 9.2.8 | Tenant Stats | GET `/api/v1/admin/tenants/{id}/stats` | 1.5h |
| 9.2.9 | Tenant Tests | Comprehensive feature tests (15+ tests) | 2h |

**Total:** ~10 hours

### Phase 9.3: System Dashboard & Analytics
**Goal:** Platform-wide oversight and metrics

| Task ID | Task | Description | Effort |
|---------|------|-------------|--------|
| 9.3.1 | AdminDashboardController | Controller for system-wide metrics | 1h |
| 9.3.2 | Overview Metrics | Total tenants, users, stores, warehouses | 1h |
| 9.3.3 | Revenue Metrics | MRR, ARR, subscription tracking | 1.5h |
| 9.3.4 | Usage Analytics | Orders, products, inventory across tenants | 1h |
| 9.3.5 | Alerts System | Expiring subscriptions, suspended tenants | 1h |
| 9.3.6 | Dashboard Endpoint | GET `/api/v1/admin/dashboard` | 0.5h |
| 9.3.7 | Dashboard Tests | Comprehensive dashboard tests | 1.5h |

**Total:** ~7.5 hours

### Phase 9.4: Advanced Super Admin Features
**Goal:** Power user capabilities

| Task ID | Task | Description | Effort |
|---------|------|-------------|--------|
| 9.4.1 | User Search | GET `/api/v1/admin/users` with filters | 1h |
| 9.4.2 | Impersonation | POST `/api/v1/admin/users/{id}/impersonate` | 1.5h |
| 9.4.3 | Subscription Mgmt | Update trial/subscription dates | 1h |
| 9.4.4 | System Audit Logs | GET `/api/v1/admin/audit-logs` (global) | 1.5h |
| 9.4.5 | System Config | GET/PUT `/api/v1/admin/settings` | 1h |
| 9.4.6 | Advanced Tests | Tests for all advanced features | 2h |

**Total:** ~8 hours

### Phase 9.5: Documentation & Polish
**Goal:** Production-ready module

| Task ID | Task | Description | Effort |
|---------|------|-------------|--------|
| 9.5.1 | API Documentation | OpenAPI specs for Super Admin endpoints | 1h |
| 9.5.2 | Postman Collection | Export collection for testing | 0.5h |
| 9.5.3 | Integration Tests | End-to-end workflow tests | 1.5h |
| 9.5.4 | Code Review | Review, refactor, apply Pint | 1h |
| 9.5.5 | Module Tests | Run full test suite, ensure no conflicts | 1h |

**Total:** ~5 hours

---

## Total Estimated Effort

| Phase | Tasks | Estimated Hours |
|-------|-------|-----------------|
| 9.1 | Super Admin Auth | 5h |
| 9.2 | Tenant Management | 10h |
| 9.3 | System Dashboard | 7.5h |
| 9.4 | Advanced Features | 8h |
| 9.5 | Documentation | 5h |
| **TOTAL** | **25 tasks** | **35.5h** |

---

## API Endpoint Specification

### Super Admin Authentication
```
POST   /api/v1/admin/auth/login              # Super admin login
POST   /api/v1/admin/auth/logout             # Logout
GET    /api/v1/admin/auth/me                 # Get current admin info
```

### Tenant Management
```
GET    /api/v1/admin/tenants                 # List all tenants (paginated)
POST   /api/v1/admin/tenants                 # Create new tenant
GET    /api/v1/admin/tenants/{id}            # View tenant details
PUT    /api/v1/admin/tenants/{id}            # Update tenant
DELETE /api/v1/admin/tenants/{id}            # Soft delete tenant
POST   /api/v1/admin/tenants/{id}/activate   # Activate tenant
POST   /api/v1/admin/tenants/{id}/suspend    # Suspend tenant
GET    /api/v1/admin/tenants/{id}/stats      # Tenant statistics
```

### System Dashboard
```
GET    /api/v1/admin/dashboard               # System overview metrics
GET    /api/v1/admin/dashboard/revenue       # Revenue-specific metrics
GET    /api/v1/admin/dashboard/usage         # Usage statistics
GET    /api/v1/admin/dashboard/alerts        # System alerts
```

### User Management
```
GET    /api/v1/admin/users                   # Search all users
GET    /api/v1/admin/users/{id}              # View user details
POST   /api/v1/admin/users/{id}/impersonate  # Generate impersonation token
POST   /api/v1/admin/users/{id}/reset-password  # Force password reset
```

### System Configuration
```
GET    /api/v1/admin/settings                # Get system settings
PUT    /api/v1/admin/settings                # Update system settings
GET    /api/v1/admin/audit-logs              # Global audit logs
```

---

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── Auth/
│   │   │   │   └── LoginController.php         # Super admin auth
│   │   │   ├── TenantController.php            # Tenant CRUD
│   │   │   ├── DashboardController.php         # System dashboard
│   │   │   └── UserController.php              # User management
│   │   └── Controller.php
│   └── Middleware/
│   │   ├── EnsureSuperAdmin.php                # Super admin guard
│   │   └── TenantScoped.php                    # Existing (no changes)
│   └── Requests/
│   │   └── Admin/
│   │       ├── CreateTenantRequest.php         # Validation
│   │       ├── UpdateTenantRequest.php
│   │       └── ImpersonateUserRequest.php
├── Models/
│   │   ├── Tenant.php                          # Existing (add scopes)
│   │   └── User.php                            # Existing (add scopes)
├── Services/
│   │   ├── TenantStatsService.php              # Calculate tenant stats
│   │   ├── DashboardMetricsService.php         # System metrics
│   │   └── ImpersonationService.php            # Token generation
├── Http/
│   └── Resources/
│       └── Admin/
│           ├── TenantResource.php              # JSON resource
│           ├── TenantCollection.php
│           ├── UserResource.php
│           └── DashboardResource.php
└── Providers/
    └── AppServiceProvider.php                  # Register services

routes/
└── api.php                                     # Add admin routes

database/
├── migrations/
│   └── [existing - no new migrations needed]
└── seeders/
    └── SuperAdminSeeder.php                    # Seed default admin

tests/
├── Feature/
│   └── Admin/
│       ├── AuthTest.php
│       ├── TenantManagementTest.php
│       ├── DashboardTest.php
│       ├── UserManagementTest.php
│       └── ImpersonationTest.php
└── Unit/
    └── Services/
        ├── TenantStatsServiceTest.php
        ├── DashboardMetricsServiceTest.php
        └── ImpersonationServiceTest.php
```

---

## Development Workflow Steps

### Before Starting (Session Prep)

1. **Update Progress Tracking:**
   ```bash
   composer session:start
   ```

2. **Update `docs/progress.json`:**
   - Add Phase 9 with all tasks
   - Set task 9.1.1 status to `in_progress`

3. **Update `docs/PROGRESS_TRACKER.md`:**
   - Add Phase 9 table
   - Mark first task as in progress

4. **Create Session Log:**
   ```bash
   cp docs/SESSION_LOG_TEMPLATE.md docs/session-logs/session-026.md
   ```

### During Development

1. **Follow Laravel Conventions:**
   ```bash
   # Create controllers
   php artisan make:controller Admin/TenantController --resource
   php artisan make:controller Admin/DashboardController
   php artisan make:controller Admin/Auth/LoginController
   
   # Create Form Requests
   php artisan make:request Admin/CreateTenantRequest
   php artisan make:request Admin/UpdateTenantRequest
   
   # Create Services
   # (No artisan command, create manually in app/Services/)
   
   # Create Resources
   php artisan make:resource Admin/TenantResource
   php artisan make:resource Admin/TenantCollection
   ```

2. **Write Tests First (TDD):**
   ```bash
   php artisan make:test Admin/TenantManagementTest
   php artisan make:test Admin/DashboardTest
   ```

3. **Run Tests Frequently:**
   ```bash
   # Run specific test
   php artisan test --compact tests/Feature/Admin/TenantManagementTest.php
   
   # Run all admin tests
   php artisan test --compact --filter=Admin
   ```

4. **Apply Code Formatting:**
   ```bash
   vendor/bin/pint --format agent
   ```

### After Each Session

1. **Update Session Log:**
   - Document work completed
   - List files created/modified
   - Record test results
   - Note issues and solutions

2. **Update `docs/progress.json`:**
   - Mark completed tasks
   - Update `timeSpent` and `completedAt`
   - Add notes

3. **Update `docs/PROGRESS_TRACKER.md`:**
   - Update task status to ✅
   - Update phase progress percentage
   - Check off deliverables

4. **End Session:**
   ```bash
   composer session:end
   ```

---

## Implementation Details

### Task 9.1.1: Super Admin Guard

**File:** `config/auth.php`

```php
'guards' => [
    // ... existing guards
    'super_admin' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

**File:** `app/Http/Middleware/EnsureSuperAdmin.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Super Admin access required',
                ],
            ], 403);
        }

        return $next($request);
    }
}
```

**Update User Model:**

```php
public function isSuperAdmin(): bool
{
    return $this->role === 'super_admin';
}
```

---

### Task 9.2.1: TenantController

**File:** `app/Http/Controllers/Admin/TenantController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\Admin\TenantResource;
use App\Http\Resources\Admin\TenantCollection;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function index(CreateTenantRequest $request): TenantCollection
    {
        $query = Tenant::query();
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('company_name', 'like', "%{$request->search}%");
            });
        }
        
        $tenants = $query->paginate($request->input('per_page', 15));
        
        return new TenantCollection($tenants);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::create($request->validated());
        
        return response()->json([
            'success' => true,
            'data' => new TenantResource($tenant),
            'message' => 'Tenant created successfully',
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new TenantResource($tenant),
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->update($request->validated());
        
        return response()->json([
            'success' => true,
            'data' => new TenantResource($tenant),
            'message' => 'Tenant updated successfully',
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully',
        ]);
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'active']);
        
        return response()->json([
            'success' => true,
            'data' => new TenantResource($tenant),
            'message' => 'Tenant activated successfully',
        ]);
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'suspended']);
        
        return response()->json([
            'success' => true,
            'data' => new TenantResource($tenant),
            'message' => 'Tenant suspended successfully',
        ]);
    }

    public function stats(Tenant $tenant): JsonResponse
    {
        $stats = app(\App\Services\TenantStatsService::class)->getStats($tenant);
        
        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
```

---

### Task 9.3.1: DashboardMetricsService

**File:** `app/Services/DashboardMetricsService.php`

```php
<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function getOverview(): array
    {
        return [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'tenants_on_trial' => Tenant::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'expiring_subscriptions' => Tenant::whereNotNull('subscription_ends_at')
                ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
                ->count(),
        ];
    }

    public function getRevenueMetrics(): array
    {
        // Calculate MRR (Monthly Recurring Revenue)
        $mrr = Tenant::where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->count() * 99; // Assuming $99/month base plan
        
        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'currency' => 'USD',
        ];
    }

    public function getUsageMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'total_stores' => Store::count(),
            'total_warehouses' => Warehouse::count(),
            'total_products' => Product::count(),
            'total_orders_today' => Order::whereDate('created_at', today())->count(),
        ];
    }

    public function getAlerts(): array
    {
        $alerts = [];
        
        // Expiring subscriptions
        $expiring = Tenant::whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
            ->get();
        
        foreach ($expiring as $tenant) {
            $alerts[] = [
                'type' => 'subscription_expiring',
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'expires_at' => $tenant->subscription_ends_at->toIso8601String(),
            ];
        }
        
        // Suspended tenants
        $suspended = Tenant::where('status', 'suspended')->get();
        
        foreach ($suspended as $tenant) {
            $alerts[] = [
                'type' => 'suspended',
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'reason' => 'Account suspended',
            ];
        }
        
        return $alerts;
    }
}
```

---

## Routes Configuration

**File:** `routes/api.php`

```php
// Super Admin Routes (outside tenant scope)
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
        Route::get('/dashboard/revenue', [Admin\DashboardController::class, 'revenue']);
        Route::get('/dashboard/usage', [Admin\DashboardController::class, 'usage']);
        Route::get('/dashboard/alerts', [Admin\DashboardController::class, 'alerts']);
        
        // Tenant Management
        Route::apiResource('tenants', Admin\TenantController::class);
        Route::post('/tenants/{tenant}/activate', [Admin\TenantController::class, 'activate']);
        Route::post('/tenants/{tenant}/suspend', [Admin\TenantController::class, 'suspend']);
        Route::get('/tenants/{tenant}/stats', [Admin\TenantController::class, 'stats']);
        
        // User Management
        Route::get('/users', [Admin\UserController::class, 'index']);
        Route::get('/users/{user}', [Admin\UserController::class, 'show']);
        Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate']);
        Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword']);
        
        // System Configuration
        Route::get('/settings', [Admin\SettingsController::class, 'show']);
        Route::put('/settings', [Admin\SettingsController::class, 'update']);
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index']);
    });
});
```

---

## Testing Strategy

### Authentication Tests

```php
// tests/Feature/Admin/AuthTest.php
public function test_super_admin_can_login(): void
public function test_invalid_credentials_return_error(): void
public function test_non_admin_cannot_access_admin_routes(): void
public function test_admin_auth_me_returns_correct_data(): void
```

### Tenant Management Tests

```php
// tests/Feature/Admin/TenantManagementTest.php
public function test_list_tenants_with_pagination(): void
public function test_filter_tenants_by_status(): void
public function test_search_tenants_by_name(): void
public function test_create_tenant(): void
public function test_update_tenant(): void
public function test_soft_delete_tenant(): void
public function test_activate_suspended_tenant(): void
public function test_suspend_active_tenant(): void
public function test_get_tenant_statistics(): void
```

### Dashboard Tests

```php
// tests/Feature/Admin/DashboardTest.php
public function test_dashboard_returns_overview_metrics(): void
public function test_dashboard_returns_revenue_metrics(): void
public function test_dashboard_returns_usage_metrics(): void
public function test_dashboard_returns_alerts(): void
public function test_dashboard_metrics_are_accurate(): void
```

### Impersonation Tests

```php
// tests/Feature/Admin/ImpersonationTest.php
public function test_super_admin_can_impersonate_user(): void
public function test_impersonation_token_is_valid(): void
public function test_non_admin_cannot_impersonate(): void
public function test_impersonation_logs_audit_entry(): void
```

---

## Security Considerations

### Authentication
- Super Admin uses **separate guard** (`auth:sanctum:super_admin`)
- **Stricter rate limiting** (200/min vs 100/min for regular API)
- **No tenant scoping** - Super Admin operates at system level

### Authorization
- All admin routes require `EnsureSuperAdmin` middleware
- User model must have `isSuperAdmin()` method
- Role stored in database (`role = 'super_admin'`)

### Audit Logging
- All Super Admin actions logged to `audit_logs` table
- Include: IP address, user agent, request URL, changes made
- Logs accessible via `/api/v1/admin/audit-logs`

### Data Protection
- **Soft deletes** for tenants (data retention)
- **No cascade deletes** - preserve referential integrity
- **Impersonation tokens** short-lived (15 minutes)

---

## Migration Path

### Database Changes

**No new migrations required** - leveraging existing schema:
- `tenants` table already has `status`, `trial_ends_at`, `subscription_ends_at`
- `users` table already has `role` column
- `audit_logs` table already exists for logging

### Seeder Required

**File:** `database/seeders/SuperAdminSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create system tenant (optional)
        $systemTenant = Tenant::firstOrCreate(
            ['slug' => 'system'],
            [
                'name' => 'System',
                'company_name' => 'POS WMS System',
                'email' => 'system@poswms.local',
                'status' => 'active',
            ]
        );
        
        // Create super admin user
        User::firstOrCreate(
            ['email' => 'superadmin@poswms.local'],
            [
                'tenant_id' => $systemTenant->id,
                'name' => 'Super Admin',
                'email' => 'superadmin@poswms.local',
                'password' => Hash::make('SuperAdmin123!'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
        
        $this->command->info('Super Admin user created:');
        $this->command->info('Email: superadmin@poswms.local');
        $this->command->info('Password: SuperAdmin123!');
        $this->command->warn('Please change the password after first login!');
    }
}
```

**Run Seeder:**
```bash
php artisan db:seed --class=SuperAdminSeeder
```

---

## Progress Tracking Integration

### Update `docs/progress.json`

Add Phase 9 structure:

```json
{
  "phase9": {
    "id": 9,
    "name": "Super Admin Module",
    "priority": "CRITICAL",
    "status": "pending",
    "estimatedHours": 35.5,
    "timeSpent": 0,
    "tasks": {
      "9.1.1": {
        "id": "9.1.1",
        "name": "Super Admin Guard",
        "status": "pending",
        "estimatedHours": 1
      },
      "9.1.2": {
        "id": "9.1.2",
        "name": "Super Admin Middleware",
        "status": "pending",
        "estimatedHours": 1
      }
      // ... all other tasks
    }
  }
}
```

### Update `docs/PROGRESS_TRACKER.md`

Add Phase 9 table following existing format.

---

## Acceptance Criteria

### Phase 9.1 (Auth) - Done When:
- [ ] Super admin can login with separate endpoint
- [ ] Non-admin users receive 403 on admin routes
- [ ] Auth me endpoint returns admin info
- [ ] All 4 auth tests passing

### Phase 9.2 (Tenant CRUD) - Done When:
- [ ] All 8 tenant endpoints implemented
- [ ] Pagination and filtering working
- [ ] Soft deletes implemented
- [ ] Stats endpoint returns accurate data
- [ ] All 15+ tenant tests passing

### Phase 9.3 (Dashboard) - Done When:
- [ ] Dashboard returns overview, revenue, usage metrics
- [ ] Alerts system working
- [ ] Metrics are accurate (verified by tests)
- [ ] All 6 dashboard tests passing

### Phase 9.4 (Advanced) - Done When:
- [ ] User search with filters working
- [ ] Impersonation generates valid token
- [ ] Subscription management working
- [ ] Global audit logs accessible
- [ ] All 10+ advanced tests passing

### Phase 9.5 (Documentation) - Done When:
- [ ] OpenAPI specs updated
- [ ] Postman collection exported
- [ ] Integration tests passing
- [ ] Code formatted with Pint
- [ ] Full test suite passing (no regressions)

---

## Next Steps

1. **Review and approve this workflow document**
2. **Update progress tracking files** (`progress.json`, `PROGRESS_TRACKER.md`)
3. **Create session log** (`session-026.md`)
4. **Begin Phase 9.1** - Super Admin Authentication
5. **Follow workflow steps** for each subsequent phase

---

**Document Maintainer:** Development Team  
**Review Cycle:** End of each phase  
**Related Documents:**
- `DEVELOPMENT_ROADMAP.md` - Overall project roadmap
- `PROGRESS_TRACKER.md` - Progress dashboard
- `TRACKING_GUIDE.md` - How to use tracking system
- `API_DESIGN.md` - API design principles
- `AGENTS.md` - Laravel Boost guidelines
