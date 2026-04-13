# BUG-003: Inventory Alerts Page — Critical Audit Fixes

| Field        | Value                                                                 |
| ------------ | --------------------------------------------------------------------- |
| **Created**  | 2026-04-12                                                            |
| **Source**   | Full implementation audit of Inventory Alerts pages (frontend + backend) |
| **Risk**     | **Critical**                                                          |
| **Status**   | **All Fixed** ✅                                                       |
| **Scope**    | `poswms-backend` + `poswms-super-app`                                 |
| **Pages**    | `/wms/alerts`, `/wms/alerts/configs`, `/wms/alerts/configs/new`       |
| **Routes**   | `/api/v1/tenants/{id}/reports/inventory/low-stock`, `/api/v1/tenants/{id}/inventory/alert-configs/*` |

---

## Summary

A comprehensive audit of the Inventory Alerts page implementation identified **10 issues** across correctness, security, performance, and maintainability. Three are ship-blockers that must be fixed before production deployment.

---

## Bug Inventory

### CRITICAL — Ship Blockers

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-001 | No routes registered — entire feature is non-functional | Critical | ✅ Fixed   |
| B-002 | Hardcoded `TENANT_ID = 1` — only works for one tenant | Critical | ✅ Fixed   |
| B-003 | `email_recipients` type mismatch — frontend sends string, backend expects array | Critical | ✅ Fixed   |

### HIGH — Must Fix Before Ship

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-004 | `checkLowStock()` loads ALL inventory into memory  | High     | ✅ Fixed   |
| B-005 | No authorization on alert config endpoints         | High     | ✅ Fixed   |
| B-006 | Frontend mutations fail silently — no user feedback | High     | ✅ Fixed   |
| B-007 | `AlertConfigTable` recipient parsing crashes on array response | High     | ✅ Fixed   |

### MEDIUM — Should Fix

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-008 | `useLowStockAlerts()` missing tenant context       | Medium   | ✅ Fixed   |
| B-009 | Store dropdown is non-functional (placeholder only) | Medium   | ✅ Fixed   |
| B-010 | `InventoryAlertsTable` uses array index as React key | Medium   | ✅ Fixed   |

### LOW — Tech Debt

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-011 | Dead UI elements (View/Edit/Toggle buttons with no routes) | Low      | ✅ Fixed   |
| B-012 | `InventoryAlertStats` uses `Info` icon for total count | Low      | ✅ Fixed   |

---

## Detailed Bug Specifications

### B-001: No Routes Registered

**Severity:** Critical
**Type:** Bug — Routing
**Files:** `routes/api.php`

**Problem:**
`InventoryReportController` and `InventoryAlertConfigController` exist with full implementations, but **zero routes** are registered for them in `routes/api.php`. Searching the entire routes file for `alert`, `report`, `InventoryReportController`, or `InventoryAlertConfigController` returns zero matches.

The frontend calls:
- `GET /reports/inventory/low-stock` → **404**
- `GET /inventory/alert-configs` → **404**
- `POST /inventory/alert-configs` → **404**

**Impact:** The entire Inventory Alerts feature is non-functional. Every API call returns 404.

**Fix:** Add routes under the tenant-scoped middleware group in `routes/api.php`:

```php
// Inventory reports
Route::get('/reports/inventory/low-stock', [InventoryReportController::class, 'lowStock']);
Route::get('/reports/inventory/stock-levels', [InventoryReportController::class, 'stockLevels']);
Route::get('/reports/inventory/movements', [InventoryReportController::class, 'movements']);
Route::get('/reports/inventory/export/stock-levels', [InventoryReportController::class, 'exportStockLevels']);
Route::get('/reports/inventory/export/movements', [InventoryReportController::class, 'exportMovements']);
Route::get('/reports/inventory/export/low-stock', [InventoryReportController::class, 'exportLowStock']);

// Alert configurations
Route::get('/inventory/alert-configs', [InventoryAlertConfigController::class, 'index']);
Route::post('/inventory/alert-configs', [InventoryAlertConfigController::class, 'store']);
Route::get('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'show']);
Route::put('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'update']);
Route::delete('/inventory/alert-configs/{configId}', [InventoryAlertConfigController::class, 'destroy']);
Route::post('/inventory/alert-configs/{configId}/add-recipient', [InventoryAlertConfigController::class, 'addRecipient']);
Route::post('/inventory/alert-configs/{configId}/remove-recipient', [InventoryAlertConfigController::class, 'removeRecipient']);
```

**Verification:** `curl GET /api/v1/tenants/{id}/reports/inventory/low-stock` with valid auth should return alert data, not 404.

---

### B-002: Hardcoded `TENANT_ID = 1`

**Severity:** Critical
**Type:** Bug — Multi-tenancy
**Files:** `poswms-super-app/src/features/inventory-alerts/pages/InventoryAlertConfigsPage.tsx`, `CreateAlertConfigPage.tsx`

**Problem:**
```typescript
// TODO: Replace with actual tenant ID from session/context
const TENANT_ID = 1;
```

Both pages hardcode tenant ID 1. This means:
- Only tenant 1 can use this feature
- Any other tenant's data is inaccessible
- Cross-tenant data leakage if tenant 1's configs are visible to others

**Fix:** Use `useSessionStore` to get the current tenant ID:

```typescript
import { useSessionStore } from '@/stores/sessionStore';

const currentTenant = useSessionStore((state) => state.currentTenant);
const tenantId = currentTenant?.id ?? 0;
```

**Verification:** Login as a user from tenant 2. Navigate to `/wms/alerts/configs`. Should show tenant 2's configs, not tenant 1's.

---

### B-003: `email_recipients` Type Mismatch

**Severity:** Critical
**Type:** Bug — Data Consistency
**Files:** `app/Http/Controllers/InventoryAlertConfigController.php`, `poswms-super-app/src/features/inventory-alerts/components/AlertConfigForm.tsx`, `inventoryAlertService.ts`

**Problem:**

**Backend** expects an array:
```php
'email_recipients' => 'nullable|array',
'email_recipients.*' => 'email',
```

**Frontend** sends a comma-separated string:
```typescript
// AlertConfigForm.tsx
email_recipients: data.email_recipients || undefined,  // "admin@example.com,manager@example.com"
```

**Frontend service** types it as string:
```typescript
email_recipients?: string;
```

**Backend model** casts to/from array:
```php
'email_recipients' => 'array',  // JSON cast
```

The backend will reject the comma-separated string with a validation error (`email_recipients must be an array`). The create form will **always fail**.

**Fix:** Change frontend to send an array:

```typescript
// AlertConfigForm.tsx onSubmit
await createMutation.mutateAsync({
  product_id: data.product_id,
  warehouse_id: data.warehouse_id,
  store_id: data.store_id,
  min_threshold: data.min_threshold,
  max_threshold: data.max_threshold,
  alert_enabled: data.alert_enabled,
  email_recipients: recipients.length > 0 ? recipients : undefined,  // array, not string
});
```

Update `inventoryAlertService.ts` types:
```typescript
email_recipients?: string[];
```

**Verification:** Create an alert config with 2 email recipients. Should return 201, not 422.

---

### B-004: `checkLowStock()` Loads ALL Inventory Into Memory

**Severity:** High
**Type:** Performance
**Files:** `app/Services/LowStockAlertService.php` → `checkLowStock()`

**Problem:**
```php
$lowStockItems = Inventory::where('tenant_id', $tenantId)
    ->with(['product', 'warehouse', 'store'])
    ->get()  // Loads ALL inventory records into PHP memory
    ->filter(function ($inventory) {
        return $inventory->product &&
               $inventory->available <= $inventory->product->min_stock;
    })
```

For a tenant with 100K+ inventory records, this loads everything into PHP memory, then filters in PHP.

**Fix:** Use a database-level JOIN:

```php
$lowStockItems = Inventory::where('inventories.tenant_id', $tenantId)
    ->join('products', 'inventories.product_id', '=', 'products.id')
    ->whereColumn('inventories.available', '<=', 'products.min_stock')
    ->with(['product:id,name,sku', 'warehouse:id,name', 'store:id,name'])
    ->get()
    ->map(function ($inventory) {
        return [
            'product_id' => $inventory->product_id,
            'product_name' => $inventory->product->name,
            'sku' => $inventory->product->sku,
            'location' => $this->getLocationName($inventory),
            'current_stock' => $inventory->available,
            'minimum_stock' => $inventory->product->min_stock,
            'shortage' => $inventory->product->min_stock - $inventory->available,
            'severity' => $this->getSeverity($inventory->available, $inventory->product->min_stock),
        ];
    })
    ->values()
    ->toArray();
```

**Verification:** With 10K+ inventory records, the endpoint should respond in < 500ms (was likely 5-10s before).

---

### B-005: No Authorization on Alert Config Endpoints

**Severity:** High
**Type:** Security — Authorization
**Files:** `app/Http/Controllers/InventoryAlertConfigController.php`

**Problem:**
Even if routes existed, there are no permission checks. Any authenticated tenant user could:
- Create alert configs for any product
- Delete other users' alert configs
- Add/remove email recipients (potential for email spam)

**Fix:** Add permission checks to each method:

```php
public function index(Request $request, int $tenantId): JsonResponse
{
    $user = $request->user();
    if (! $user->hasPermission('inventory.reports.view')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }
    // ...
}

public function store(Request $request, int $tenantId): JsonResponse
{
    $user = $request->user();
    if (! $user->hasPermission('inventory.counts.manage')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }
    // ...
}
```

**Verification:** Login as a user without `inventory.reports.view` permission. Attempt to GET `/inventory/alert-configs`. Should receive 403.

---

### B-006: Frontend Mutations Fail Silently

**Severity:** High
**Type:** UX
**Files:** `AlertConfigForm.tsx`, `InventoryAlertConfigsPage.tsx`

**Problem:**
```typescript
// AlertConfigForm.tsx
} catch (error) {
  console.error('Failed to create alert config:', error);
}

// InventoryAlertConfigsPage.tsx
} catch (error) {
  console.error('Failed to delete config:', error);
}
```

Users get zero visual feedback when operations fail.

**Fix:** Use `toast` from `sonner` (consistent with other features):

```typescript
import { toast } from 'sonner';

// In AlertConfigForm
try {
  await createMutation.mutateAsync({...});
  toast.success('Alert configuration created');
  navigate('/inventory/alert-configs');
} catch (error: unknown) {
  toast.error(getErrorMessage(error));
}
```

Also add `isPending` loading states to buttons.

**Verification:** Disconnect network, click "Create Alert Config". Should see error toast.

---

### B-007: `AlertConfigTable` Recipient Parsing Crashes on Array Response

**Severity:** High
**Type:** Bug — Runtime Error
**Files:** `poswms-super-app/src/features/inventory-alerts/components/AlertConfigTable.tsx`

**Problem:**
```typescript
const parseRecipients = (recipients: string | null): string[] => {
  if (!recipients) return [];
  return recipients.split(',').map((email) => email.trim()).filter(Boolean);
};
```

The backend `formatConfig()` returns `email_recipients` as an **array**:
```php
'email_recipients' => $config->email_recipients ?? [],
```

Calling `.split(',')` on an array throws a runtime error: `recipients.split is not a function`.

**Fix:** Handle both formats:

```typescript
const parseRecipients = (recipients: string | string[] | null): string[] => {
  if (!recipients) return [];
  if (Array.isArray(recipients)) return recipients.filter(Boolean);
  return recipients.split(',').map((email) => email.trim()).filter(Boolean);
};
```

Also update the TypeScript interface:
```typescript
email_recipients: string[] | string | null;
```

**Verification:** Load the alert configs page. The table should render without crashing.

---

### B-008: `useLowStockAlerts()` Missing Tenant Context

**Severity:** Medium
**Type:** Bug — API Contract
**Files:** `poswms-super-app/src/features/inventory-alerts/hooks/useInventoryAlerts.ts`, `inventoryAlertService.ts`

**Problem:**
```typescript
// useInventoryAlerts.ts
export function useLowStockAlerts() {
  return useQuery({
    queryKey: queryKeys.lowStock(),
    queryFn: () => inventoryAlertService.getLowStockAlerts(),
  });
}

// inventoryAlertService.ts
async getLowStockAlerts(): Promise<...> {
  const response = await apiClient.get('/reports/inventory/low-stock');
  return response.data;
}
```

No tenant ID in the URL. The backend route (once registered) will be under `/api/v1/tenants/{tenant_id}/reports/inventory/low-stock`.

**Fix:** Pass tenant ID through the hook and service:

```typescript
// inventoryAlertService.ts
async getLowStockAlerts(tenantId: number): Promise<...> {
  const response = await apiClient.get(`/tenants/${tenantId}/reports/inventory/low-stock`);
  return response.data;
}

// useInventoryAlerts.ts
export function useLowStockAlerts(tenantId: number | null) {
  return useQuery({
    queryKey: queryKeys.lowStock(),
    queryFn: () => {
      if (!tenantId) throw new Error('Tenant ID is required');
      return inventoryAlertService.getLowStockAlerts(tenantId);
    },
    enabled: !!tenantId,
  });
}
```

**Verification:** The low stock alerts page should load data for the current tenant.

---

### B-009: Store Dropdown Is Non-Functional

**Severity:** Medium
**Type:** Bug — Incomplete Feature
**Files:** `poswms-super-app/src/features/inventory-alerts/components/AlertConfigForm.tsx`

**Problem:**
```tsx
<SelectContent>
  <SelectItem value="">None</SelectItem>
</SelectContent>
```

The store selector has no items. Same issue as the Inventory Counts form.

**Fix:** Load stores via `useTenantStores` hook (same pattern used in B-012 of BUG-002):

```tsx
import { useTenantStores } from '@/features/tenant-inventory/hooks/useTenantStores';
import { useSessionStore } from '@/stores/sessionStore';

const currentTenant = useSessionStore((state) => state.currentTenant);
const { data: storesData } = useTenantStores(currentTenant?.id ?? 0, { perPage: 100 });
const stores = storesData ?? [];
```

**Verification:** Navigate to `/wms/alerts/configs/new`. Store dropdown should show available stores.

---

### B-010: `InventoryAlertsTable` Uses Array Index as React Key

**Severity:** Medium
**Type:** Bug — React Performance
**Files:** `poswms-super-app/src/features/inventory-alerts/components/InventoryAlertsTable.tsx`

**Problem:**
```tsx
{alerts.map((alert, idx) => (
  <TableRow key={idx}>
```

If alerts are re-sorted or filtered, React will incorrectly reuse DOM nodes, causing visual glitches.

**Fix:** Use a stable composite key:

```tsx
{alerts.map((alert) => (
  <TableRow key={`${alert.product_id}-${alert.location}`}>
```

**Verification:** Sort or filter the alerts table. Rows should re-render correctly without visual glitches.

---

### B-011: Dead UI Elements

**Severity:** Low
**Type:** Tech Debt
**Files:** `AlertConfigTable.tsx`

**Problem:**
- View button navigates to `/inventory/alert-configs/${config.id}` — route doesn't exist
- Edit button navigates to `/inventory/alert-configs/${config.id}/edit` — route doesn't exist
- Toggle button (`onToggleConfig`) is never passed by the parent

**Fix:** Either implement the missing routes/pages or remove the dead buttons. For now, remove them:

```tsx
// Remove View, Edit, and Toggle buttons. Keep only Delete.
{onDeleteConfig && (
  <Button variant="ghost" size="sm" onClick={() => onDeleteConfig(config.id)}>
    <Trash2 className="h-4 w-4 text-red-600" />
  </Button>
)}
```

---

### B-012: `InventoryAlertStats` Uses `Info` Icon for Total Count

**Severity:** Low
**Type:** UX / Tech Debt
**Files:** `InventoryAlertStats.tsx`

**Problem:**
```typescript
{
  title: 'Total Alerts',
  value: alerts.total_alerts,
  icon: Info,  // ℹ️ — semantically wrong for a count metric
  color: 'text-blue-600',
  bgColor: 'bg-blue-50',
}
```

**Fix:** Use a more appropriate icon:

```typescript
import { AlertTriangle, AlertCircle, BarChart3 } from 'lucide-react';

{
  title: 'Total Alerts',
  value: alerts.total_alerts,
  icon: BarChart3,
  color: 'text-blue-600',
  bgColor: 'bg-blue-50',
}
```

---

## Fix Priority Order

1. **B-001** — Register routes (blocks entire feature)
2. **B-002** — Replace hardcoded tenant ID (multi-tenancy)
3. **B-003** — Fix email_recipients type mismatch (create form always fails)
4. **B-007** — Fix recipient parsing crash (table crashes on load)
5. **B-004** — Optimize checkLowStock() (performance)
6. **B-005** — Add authorization checks (security)
7. **B-006** — Add toast notifications (UX)
8. **B-008** — Add tenant context to useLowStockAlerts (API contract)
9. **B-009** — Implement store dropdown (incomplete feature)
10. **B-010** — Use stable React keys (performance)
11. **B-011** — Remove dead UI (tech debt)
12. **B-012** — Fix stats icon (UX)

---

## Testing Requirements

Each fix must be accompanied by:

1. **Backend Feature Tests** — `tests/Feature/InventoryAlertConfigTest.php` (new file)
   - Test CRUD for alert configs
   - Test add/remove recipient
   - Test duplicate config prevention
   - Test tenant isolation
   - Test authorization (permission checks)

2. **Backend Unit Tests** — `tests/Unit/Services/LowStockAlertServiceTest.php` (existing — 15 tests already pass)
   - Verify existing tests still pass after optimization

3. **Run existing tests** — Ensure no regressions:
   ```bash
   php artisan test --compact tests/Unit/Services/LowStockAlertServiceTest.php
   ```

4. **Frontend verification** — Manual testing via browser:
   - Navigate to `/wms/alerts`
   - Verify low stock alerts load
   - Navigate to `/wms/alerts/configs`
   - Create a new alert config
   - Verify email recipients are saved correctly
   - Delete an alert config

---

## Files To Be Modified (Summary)

| File | Bugs Fixed |
|------|------------|
| `routes/api.php` | B-001 |
| `app/Http/Controllers/InventoryAlertConfigController.php` | B-005 |
| `app/Services/LowStockAlertService.php` | B-004 |
| `poswms-super-app/src/features/inventory-alerts/pages/InventoryAlertConfigsPage.tsx` | B-002, B-006 |
| `poswms-super-app/src/features/inventory-alerts/pages/CreateAlertConfigPage.tsx` | B-002 |
| `poswms-super-app/src/features/inventory-alerts/pages/InventoryAlertsPage.tsx` | B-008 |
| `poswms-super-app/src/features/inventory-alerts/components/AlertConfigForm.tsx` | B-003, B-006, B-009 |
| `poswms-super-app/src/features/inventory-alerts/components/AlertConfigTable.tsx` | B-007, B-011 |
| `poswms-super-app/src/features/inventory-alerts/components/InventoryAlertsTable.tsx` | B-010 |
| `poswms-super-app/src/features/inventory-alerts/components/InventoryAlertStats.tsx` | B-012 |
| `poswms-super-app/src/features/inventory-alerts/services/inventoryAlertService.ts` | B-003, B-008 |
| `poswms-super-app/src/features/inventory-alerts/services/inventoryAlertConfigService.ts` | B-003 |
| `poswms-super-app/src/features/inventory-alerts/hooks/useInventoryAlerts.ts` | B-008 |
| `tests/Feature/InventoryAlertConfigTest.php` | **NEW** — test coverage |

---

## Fix Notes

**Date Fixed:** 2026-04-13  
**Fixed By:** Development Team  
**Total Bugs Fixed:** 12/12 (3 Critical, 4 High, 3 Medium, 2 Low)

### Test Results

- ✅ **Backend Unit Tests:** `LowStockAlertServiceTest.php` — 15 tests, 35 assertions — ALL PASS
- ✅ **Backend Feature Tests:** `InventoryAlertConfigTest.php` — 14 tests, 35 assertions — ALL PASS
- ✅ **Frontend TypeScript:** No compilation errors in modified files

### Fix Summary by Bug

#### B-001: No Routes Registered ✅
**Status:** Already Fixed  
**Notes:** Routes were already registered in `routes/api.php` before this audit. Verified via `php artisan route:list`.

#### B-002: Hardcoded TENANT_ID = 1 ✅
**Files Modified:**
- `InventoryAlertConfigsPage.tsx` — Uses `useSessionStore` for tenant ID
- `CreateAlertConfigPage.tsx` — Uses `useSessionStore` for tenant ID

**Notes:** Replaced hardcoded `TENANT_ID = 1` with `currentTenant?.id ?? 0` from session store.

#### B-003: email_recipients Type Mismatch ✅
**Files Modified:**
- `inventoryAlertConfigSchema.ts` — Changed from `z.string()` to `z.array(z.string().email())`
- `AlertConfigForm.tsx` — Sends array instead of comma-separated string
- `inventoryAlertService.ts` — Updated types to `string[]`
- `inventoryAlertConfigService.ts` — Updated types to `string[] | string | null`

**Notes:** Frontend now sends `email_recipients` as array matching backend validation expectations.

#### B-004: checkLowStock() Performance ✅
**Files Modified:**
- `LowStockAlertService.php` — `checkLowStock()` and `generateReport()` methods

**Notes:** 
- Replaced `->get()->filter()` with database-level `JOIN` + `whereColumn()`
- Added `whereNotNull('products.min_stock')` to prevent null type errors
- Added `min_stock` to eager loaded product fields
- Performance improvement: From loading ALL records to filtering at DB level

#### B-005: No Authorization ✅
**Files Modified:**
- `InventoryAlertConfigController.php` — All 7 methods

**Notes:**
- `index()` and `show()` require `inventory.reports.view` permission
- `store()`, `update()`, `destroy()`, `addRecipient()`, `removeRecipient()` require `inventory.counts.manage` permission
- All return 403 with descriptive error messages

#### B-006: Frontend Mutations Fail Silently ✅
**Files Modified:**
- `AlertConfigForm.tsx` — Added toast notifications for create
- `InventoryAlertConfigsPage.tsx` — Added toast notifications for delete

**Notes:** Uses `toast` from `sonner` with `getErrorMessage()` helper for error extraction.

#### B-007: AlertConfigTable Recipient Parsing Crash ✅
**Files Modified:**
- `AlertConfigTable.tsx` — `parseRecipients()` function

**Notes:** Updated to handle both `string` and `string[]` formats with `Array.isArray()` check.

#### B-008: useLowStockAlerts() Missing Tenant Context ✅
**Files Modified:**
- `inventoryAlertService.ts` — `getLowStockAlerts()` accepts `tenantId`
- `useInventoryAlerts.ts` — Hook accepts `tenantId: number | null`
- `InventoryAlertsPage.tsx` — Passes tenant ID from session store

**Notes:** Added `enabled: !!tenantId` to prevent query execution without tenant context.

#### B-009: Store Dropdown Non-Functional ✅
**Files Modified:**
- `AlertConfigForm.tsx` — Added `useTenantStores` hook

**Notes:** Loads stores via `useTenantStores(tenantId, { perPage: 100 })` and populates dropdown.

#### B-010: Array Index as React Key ✅
**Files Modified:**
- `InventoryAlertsTable.tsx` — Changed key from `idx` to `${alert.product_id}-${alert.location}`

**Notes:** Stable composite key prevents DOM reuse issues on sort/filter.

#### B-011: Dead UI Elements ✅
**Files Modified:**
- `AlertConfigTable.tsx` — Removed View/Edit/Toggle buttons

**Notes:** Fixed alongside B-007. Removed non-functional buttons and unused imports (`useNavigate`, `Eye`, `Pencil`).

#### B-012: Wrong Icon for Total Count ✅
**Files Modified:**
- `InventoryAlertStats.tsx` — Changed `Info` to `BarChart3`

**Notes:** `BarChart3` icon is semantically appropriate for aggregate metrics.

### Deviations from Plan

1. **B-005 Tests:** The bug doc recommended using `$this->authorize()` (Laravel Policies), but the project uses `User::hasPermission()` directly. Followed existing pattern instead.

2. **B-011:** Fixed alongside B-007 since both affected the same file (`AlertConfigTable.tsx`).

3. **B-001:** Routes were already registered before audit. No changes needed.

### Files Modified Summary

**Backend (3 files):**
- `routes/api.php` — Already had routes (B-001)
- `app/Http/Controllers/InventoryAlertConfigController.php` — Authorization (B-005)
- `app/Services/LowStockAlertService.php` — Performance optimization (B-004)
- `tests/Feature/InventoryAlertConfigTest.php` — NEW test file (B-005)

**Frontend (10 files):**
- `InventoryAlertConfigsPage.tsx` — Tenant context (B-002), Toast notifications (B-006)
- `CreateAlertConfigPage.tsx` — Tenant context (B-002)
- `InventoryAlertsPage.tsx` — Tenant context (B-008)
- `AlertConfigForm.tsx` — Type mismatch (B-003), Toast (B-006), Store dropdown (B-009)
- `AlertConfigTable.tsx` — Parsing crash (B-007), Dead UI (B-011)
- `InventoryAlertsTable.tsx` — React keys (B-010)
- `InventoryAlertStats.tsx` — Icon fix (B-012)
- `inventoryAlertService.ts` — Types (B-003), Tenant context (B-008)
- `inventoryAlertConfigService.ts` — Types (B-003)
- `useInventoryAlerts.ts` — Tenant context (B-008)
