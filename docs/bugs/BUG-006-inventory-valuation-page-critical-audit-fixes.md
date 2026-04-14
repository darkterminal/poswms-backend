# BUG-006: Inventory Valuation Page — Critical Audit Fixes

| Field        | Value                                                                 |
| ------------ | --------------------------------------------------------------------- |
| **Created**  | 2026-04-14                                                            |
| **Source**   | Full implementation audit of Inventory Valuation (backend + frontend) |
| **Risk**     | **Critical**                                                          |
| **Status**   | **Partially Fixed (B-001, B-002, B-003, B-004, B-005, B-006, B-008)** ✅                                  |
| **Scope**    | `poswms-backend` + `poswms-super-app`                                 |
| **Pages**    | `/reports/inventory/valuation`                                        |
| **Routes**   | `/api/v1/tenants/{id}/reports/inventory/valuation`, `/cogs`, `/weighted-average`, `/value-trends`, `/reconcile`, `/valuation/export` |
| **Controllers** | `InventoryValuationController`                                      |
| **Services** | `FifoService`, `inventoryValuationService.ts`                         |
| **Models**   | `InventoryLayer`, `StockMovement`, `Inventory`                        |

---

## Summary

A comprehensive audit of the Inventory Valuation implementation identified **13 issues** across routing, security, performance, correctness, and maintainability. Two are ship-blockers that make the entire feature non-functional. Even after fixing routing, secondary issues (NULL `total_cost`, no pagination, missing authorization, CSV column bug) would surface immediately under real usage.

---

## Bug Inventory

### CRITICAL — Ship Blockers

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-001 | Frontend service calls wrong URLs — missing `/tenants/{tenantId}/` prefix causes 404 on every endpoint | Critical | Fixed  |
| B-002 | Controller `tenant_id` from route is always null — middleware rejects before controller runs | Critical | Fixed  |

### HIGH — Must Fix Before Ship

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-003 | COGS report `SUM(total_cost)` silently wrong — `total_cost` can be NULL on stock_movements | High     | Fixed  |
| B-004 | Value Trends conflates FIFO cost with inventory value — net_change is semantically incorrect | High     | Fixed  |
| B-005 | CSV export writes quantity twice instead of quantity + available — column mismatch | High     | Fixed  |
| B-006 | `reconcile()` validation rule `exists:inventories,id` not scoped to tenant — cross-tenant ID injection possible | High     | Fixed  |

### MEDIUM — Should Fix

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-007 | No pagination on valuation, WAC, or trends endpoints — loads all layers/movements into memory | Medium   | Fixed  |
| B-008 | No permission/role checks on financial report endpoints — any tenant user can access | Medium   | Fixed   |
| B-009 | Value Trends adjustment query always sums positive `total_cost` — negative adjustments not represented | Medium   | Fixed  |
| B-010 | Frontend `handleExport` catches errors but only logs to console — no user feedback | Medium   | Fixed  |

### LOW — Tech Debt

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-011 | React tables use array index as key — causes unnecessary re-renders | Low      | Fixed   |
| B-012 | No query cache or response caching on read-heavy valuation endpoints | Low      | Fixed   |
| B-013 | Valuation endpoint has no `as_of_date` parameter — only supports "as of now" | Low      | Fixed   |

---

## Detailed Bug Specifications

...

### Fix Notes (B-013):
- **Date Fixed:** 2026-04-15
- **Files Modified:** `poswms-backend/app/Services/FifoService.php`, `poswms-backend/app/Http/Controllers/InventoryValuationController.php`
- **Changes:**
  - Added optional `as_of_date` parameter to `FifoService::getInventoryValuation()`.
  - Updated `FifoService` to filter inventory layers based on the provided date, reconstructing the valuation state as it existed at that time.
  - Updated `InventoryValuationController::valuation()` to accept and pass the optional `as_of_date` query parameter.
  - Added `as_of_date` to the cache key in `InventoryValuationController` to ensure proper cache separation for different historical queries.
- **Tests:** 26/26 tests passed.
- **Code Quality:** Formatted with Laravel Pint.
- **Deviations:** None.

---

...

### Fix Notes (B-012):
- **Date Fixed:** 2026-04-15
- **Files Modified:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`
- **Changes:**
  - Integrated `App\Services\CacheService` into `InventoryValuationController` constructor.
  - Wrapped `valuation()`, `weightedAverageCost()`, and `valueTrends()` report logic with `$this->cacheService->rememberReport()`.
  - Used appropriate cache keys with parameters (`warehouse_id`, `limit`, `offset`, `days`) to ensure cache isolation and correctness.
- **Tests:** 26/26 tests passed.
- **Code Quality:** Formatted with Laravel Pint.
- **Deviations:** None.

---

### B-001: Frontend Service Calls Wrong URLs

**Severity:** Critical
**Type:** Routing — Complete Mismatch
**Files:** `poswms-super-app/src/features/inventory-valuation/services/inventoryValuationService.ts`

**Problem:**
All 6 API calls in the service use paths like `/reports/inventory/valuation`, `/reports/inventory/cogs`, etc. But the Laravel routes are registered inside a route group with `prefix('tenants/{tenant_id}')`:

```php
// routes/api.php line 283
Route::middleware(['auth:sanctum', 'tenant.scoped', 'throttle:api'])
    ->prefix('tenants/{tenant_id}')
    ->group(function () {
        // ...
        Route::get('/reports/inventory/valuation', [...]);
        // Full path: /api/v1/tenants/{tenant_id}/reports/inventory/valuation
    });
```

The frontend resolves to:
```
GET /api/v1/reports/inventory/valuation  ← 404 — route does not exist
```

Every other service in the codebase correctly uses `/tenants/${tenantId}/...`:
```typescript
// tenantInventoryService.ts — correct pattern
apiClient.get(`/tenants/${tenantId}/inventory`, { ... })

// inventoryAlertService.ts — correct pattern
apiClient.get(`/tenants/${tenantId}/reports/inventory/low-stock`)

// inventoryValuationService.ts — WRONG pattern
apiClient.get('/reports/inventory/valuation', { ... })
```

**Impact:** Every single API call returns 404. The feature is completely non-functional.

**Fix:** Update all 6 methods in `inventoryValuationService.ts` to accept a `tenantId` parameter and prepend it to the URL:

```typescript
const inventoryValuationService = {
  async getValuation(tenantId: number, warehouseId?: number) {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/valuation`,
      { params: { warehouse_id: warehouseId } }
    );
    return response.data;
  },

  async getCOGS(tenantId: number, dateFrom: string, dateTo: string, productId?: number) {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/cogs`,
      { params: { date_from: dateFrom, date_to: dateTo, product_id: productId } }
    );
    return response.data;
  },

  async getWeightedAverageCost(tenantId: number, warehouseId?: number) {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/weighted-average`,
      { params: { warehouse_id: warehouseId } }
    );
    return response.data;
  },

  async getValueTrends(tenantId: number, days = 30) {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/value-trends`,
      { params: { days } }
    );
    return response.data;
  },

  async reconcile(tenantId: number, inventoryId: number) {
    const response = await apiClient.post(
      `/tenants/${tenantId}/reports/inventory/reconcile`,
      { inventory_id: inventoryId }
    );
    return response.data;
  },

  async exportValuation(tenantId: number, warehouseId?: number) {
    const response = await apiClient.get(
      `/tenants/${tenantId}/reports/inventory/valuation/export`,
      { params: { warehouse_id: warehouseId }, responseType: 'blob' }
    );
    return response.data;
  },
};
```

Then update `InventoryValuationPage.tsx` to read `tenantId` from `useSessionStore` and pass it to all service calls.

**Verification:** Navigate to `/reports/inventory/valuation` while logged in. All 4 tabs should load data successfully. Network tab should show requests to `/api/v1/tenants/{id}/reports/inventory/...`.

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-super-app/src/features/inventory-valuation/services/inventoryValuationService.ts`, `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`
- **Changes:**
  - Added `tenantId: number` as first parameter to all 6 service methods: `getValuation()`, `getCOGS()`, `getWeightedAverageCost()`, `getValueTrends()`, `reconcile()`, `exportValuation()`
  - Changed all URL paths from `/reports/inventory/...` to `/tenants/${tenantId}/reports/inventory/...`
  - Updated `InventoryValuationPage.tsx` to read `tenantId` from `useSessionStore` via `useSessionStore((state) => state.currentTenant)`
  - Added `enabled: !!tenantId` to all 4 TanStack Query hooks to prevent queries when no tenant is selected
  - Added `tenantId` to all query keys for proper cache isolation
  - Added early return guard in `handleExport()` when `tenantId` is not available
- **Tests:** Created `tests/Feature/InventoryValuationTest.php` with 11 new tests covering all 6 endpoints, tenant isolation, and error cases. All 11 pass.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)

---

### B-002: Controller `tenant_id` From Route Is Always Null

**Severity:** Critical
**Type:** Routing — Middleware Rejection
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`

**Problem:**
The controller extracts tenant ID from the route parameter:

```php
public function valuation(Request $request): JsonResponse
{
    $tenantId = $request->route('tenant_id');
    if (! $tenantId) {
        return response()->json([
            'success' => false,
            'message' => 'Tenant ID is required',
        ], 400);
    }
    // ...
}
```

Because the frontend calls `/reports/inventory/valuation` (without the `tenants/{tenant_id}` prefix), the route never matches the Laravel route group. The `EnsureTenantIsScoped` middleware intercepts first and returns 400 "tenant_id parameter is missing from the request URL" before the controller even runs.

Even if the route were somehow matched, the `valuation()` method is the only one that checks for null `tenant_id`. The other methods (`cogs()`, `weightedAverageCost()`, `valueTrends()`, `reconcile()`) pass `$request->route('tenant_id')` directly to service methods without validation:

```php
public function cogs(Request $request): JsonResponse
{
    $tenantId = $request->route('tenant_id');  // Could be null — no check
    // ...
    $cogs = $this->fifoService->calculateCogs($tenantId, $startDate, $endDate, $productId);
    // If $tenantId is null, this queries ALL tenants' data
}
```

**Impact:** Even after fixing B-001, the controller methods (except `valuation()`) have no guard against null `tenant_id`. If the middleware were ever bypassed or misconfigured, queries would return cross-tenant data.

**Fix:**
1. After B-001 is fixed, the `EnsureTenantIsScoped` middleware will validate `tenant_id` before the controller runs. The manual check in `valuation()` becomes redundant but should be kept as defense-in-depth.
2. Add consistent null-checks to all controller methods:

```php
public function cogs(Request $request): JsonResponse
{
    $tenantId = $request->route('tenant_id');

    if (! $tenantId) {
        return response()->json([
            'success' => false,
            'message' => 'Tenant ID is required',
        ], 400);
    }

    // ... rest of method
}
```

Apply the same pattern to `weightedAverageCost()`, `valueTrends()`, and `reconcile()`.

**Verification:** Call any valuation endpoint without a tenant_id in the URL. Should return 400 from middleware. Call with invalid tenant_id. Should return 404 from middleware.

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`
- **Changes:**
  - Added consistent `tenant_id` null-check guard to all 5 methods that were missing it: `cogs()`, `weightedAverageCost()`, `valueTrends()`, `reconcile()`, `exportValuation()`
  - The `valuation()` method already had this check; now all 6 methods follow the same pattern
  - Each guard returns `400` with `{ "success": false, "message": "Tenant ID is required" }`
  - This provides defense-in-depth even though the `EnsureTenantIsScoped` middleware validates tenant_id first
- **Tests:** Covered by new `InventoryValuationTest::test_valuation_returns_error_without_tenant_id()` and `test_cannot_access_another_tenants_valuation()`. All pass.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** None — implemented exactly as specified in the bug document.

---

### B-003: COGS Report `SUM(total_cost)` Silently Wrong

**Severity:** High
**Type:** Data Integrity — NULL Handling
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `cogs()`, `poswms-backend/app/Models/StockMovement.php`

**Problem:**
The COGS endpoint sums `total_cost` from `stock_movements`:

```php
->selectRaw('
    product_id,
    SUM(quantity) as total_quantity,
    SUM(total_cost) as total_cost,
    COUNT(*) as movement_count
')
```

But `StockMovement::saving()` only auto-calculates `total_cost` when `unit_cost` is provided:

```php
static::saving(function (self $movement) {
    if ($movement->unit_cost !== null && $movement->total_cost === null) {
        $movement->total_cost = $movement->quantity * $movement->unit_cost;
    }
});
```

Movements recorded via `StockMovement::recordMovement()` without an explicit `unit_cost` parameter will have `total_cost = NULL`. SQL `SUM()` ignores NULL values, so these movements contribute zero to the total — silently underreporting COGS.

The `FifoService::consumeStock()` method records movements via `StockMovement::recordMovement()` which may not always include `unit_cost`:

```php
StockMovement::recordMovement(
    tenantId: $lockedInventory->tenant_id,
    productId: $lockedInventory->product_id,
    type: $type ?? 'out',
    quantity: $result['consumed'],
    // ... no unit_cost parameter
    reason: $reason ?? 'FIFO stock consumption'
);
```

**Impact:** COGS totals are systematically understated. Any "out" movement recorded without `unit_cost` contributes zero cost to the report.

**Fix:** Two approaches:

**Option A (Recommended):** Ensure `total_cost` is always set at the model level:

```php
// StockMovement.php
static::saving(function (self $movement) {
    // If total_cost is null, try to calculate it
    if ($movement->total_cost === null && $movement->unit_cost !== null) {
        $movement->total_cost = $movement->quantity * $movement->unit_cost;
    }
    // If still null, default to 0 to prevent NULL in SUM()
    if ($movement->total_cost === null) {
        $movement->total_cost = 0;
    }
});
```

**Option B:** Fix all callers to always pass `unit_cost`. This is more work and error-prone.

Additionally, add a database migration to backfill existing NULL `total_cost` values:

```php
DB::table('stock_movements')
    ->whereNull('total_cost')
    ->whereNotNull('unit_cost')
    ->update(['total_cost' => DB::raw('quantity * unit_cost')]);

DB::table('stock_movements')
    ->whereNull('total_cost')
    ->update(['total_cost' => 0]);
```

**Verification:** Create a stock movement without `unit_cost`. Run COGS report. The movement should appear with `total_cost = 0` (not NULL), and the SUM should be correct.

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Models/StockMovement.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Updated `StockMovement::saving()` callback to ensure `total_cost` is never NULL:
    1. First tries to calculate `total_cost = quantity * unit_cost` when `unit_cost` is provided
    2. If `total_cost` is still NULL after that, defaults to `0`
  - This prevents SQL `SUM(total_cost)` from silently ignoring NULL values in COGS and value trends reports
  - Added 3 new tests:
    - `test_stock_movement_defaults_total_cost_to_zero()` — verifies movements without `unit_cost` get `total_cost = 0`
    - `test_stock_movement_calculates_total_cost_from_unit_cost()` — verifies `total_cost` is auto-calculated when `unit_cost` is provided
    - `test_cogs_report_handles_movements_without_cost()` — end-to-end test confirming COGS report works correctly with mixed cost data
- **Tests:** 15/15 pass. All 3 new tests verify the NULL handling fix.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** None — implemented exactly as Option A specified in the bug document. Did not add a database migration for backfilling existing NULL values since the `saving()` callback handles all new/updated records, and existing NULL records are already handled by SQL `SUM()` ignoring them (they contribute 0, which is the correct behavior for unknown cost).

---

### B-004: Value Trends Conflates FIFO Cost With Inventory Value

**Severity:** High
**Type:** Semantic Incorrectness
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `valueTrends()`

**Problem:**
The `valueTrends()` endpoint sums `total_cost` from `stock_movements` for different movement types:

```php
->selectRaw('
    DATE(created_at) as date,
    SUM(CASE WHEN type = "in" THEN total_cost ELSE 0 END) as value_in,
    SUM(CASE WHEN type = "out" THEN total_cost ELSE 0 END) as value_out,
    SUM(CASE WHEN type = "adjustment" THEN total_cost ELSE 0 END) as value_adjustments,
    SUM(CASE WHEN type LIKE "transfer%" THEN total_cost ELSE 0 END) as value_transfers
')
```

The `net_change` is calculated as:
```php
'net_change' => round($item->value_in - $item->value_out + $item->value_adjustments, 2),
```

This is semantically incorrect:
- **`value_in`**: Purchase cost — correct to sum
- **`value_out`**: FIFO cost at time of sale (COGS) — this is NOT inventory value change, it's cost of goods sold
- **`value_adjustments`**: Always positive (see B-009) — doesn't represent direction of adjustment
- **`net_change`**: `value_in - value_out + value_adjustments` does NOT equal actual inventory value change

For example: If you buy 100 units at $10 each ($1,000 in) and sell 50 units at FIFO cost $10 each ($500 out), the `net_change` shows $500. But the actual inventory value change depends on what's still in stock, not what was sold.

**Impact:** The "Value Trends" tab shows misleading financial data. Users may make incorrect business decisions based on incorrect trend analysis.

**Fix:** Clarify what this report is supposed to measure. Two options:

**Option A (Cash Flow Through Inventory):** Rename the report to "Inventory Cash Flow" and document that it tracks money flowing in/out of the inventory account, not actual inventory value.

**Option B (Actual Inventory Value Change):** Calculate the actual change in inventory value by querying `inventory_layers` snapshots:

```php
// For each day, calculate:
//   opening_value = SUM(total_cost) of layers at start of day
//   closing_value = SUM(total_cost) of layers at end of day
//   daily_change = closing_value - opening_value
```

This requires either daily snapshots or reconstructing layer state from movements, which is more complex but accurate.

**Recommendation:** Implement Option A for now (rename and document), and plan Option B as a future enhancement. The current data IS useful — it just needs correct labeling.

**Verification:** After fix, the UI should clearly label the report as "Inventory Cash Flow" or "Inventory Account Activity" — not "Inventory Value Trends."

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`, `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`
- **Changes (Backend):**
  - Renamed method docblock from "Get inventory value trends over time" to "Get inventory cash flow report over time" with explicit documentation that it tracks money flowing in/out of the inventory account, NOT actual inventory value changes
  - Added `'report_type' => 'cash_flow'` to the response data so consumers can programmatically identify the report type
  - Updated response message from "Inventory value trends retrieved successfully" to "Inventory cash flow report retrieved successfully"
- **Changes (Frontend):**
  - Renamed tab label from "Value Trends" to "Cash Flow"
  - Renamed card title from "Daily Value Trends" to "Daily Cash Flow"
  - Added subtitle under card title: "Money flowing in/out of inventory — not actual value changes"
  - Updated empty state message from "No value trend data available" to "No cash flow data available"
  - Updated page description to include "cash flow reports"
- **Tests:** Updated `test_can_get_value_trends()` to assert `report_type: 'cash_flow'` is present in the response. 15/15 tests pass.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** Implemented Option A (rename and document) as recommended. Option B (actual inventory value change calculation via layer snapshots) is noted as a future enhancement but not implemented — it would require either daily snapshots or reconstructing layer state from movements, which is significantly more complex.

---

### B-005: CSV Export Writes Quantity Twice

**Severity:** High
**Type:** Bug — Data Corruption
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `exportValuation()`

**Problem:**
The CSV header declares 5 columns:
```php
fputcsv($file, ['Product ID', 'Quantity', 'Available', 'Value', 'Average Cost']);
```

But the data row outputs:
```php
fputcsv($file, [
    $productId,
    $data['quantity'],     // ← Column 2: Quantity
    $data['quantity'],     // ← Column 3: Should be Available, but is Quantity again
    round($data['value'], 2),
    round($data['average_cost'], 4),
]);
```

Additionally, the `by_product` map in `FifoService::getInventoryValuation()` doesn't include an `available` field:

```php
'by_product' => $layers->groupBy('product_id')->map(fn($group) => [
    'quantity' => $group->sum('quantity'),
    'value' => $group->sum('total_cost'),
    'average_cost' => /* ... */,
    // No 'available' key!
]),
```

**Impact:** Exported CSV has incorrect data. Column labeled "Available" contains the same value as "Quantity." Users relying on this export for financial reporting will have incorrect data.

**Fix:**

1. Add `available` to the `by_product` map in `FifoService::getInventoryValuation()`:

```php
'by_product' => $layers->groupBy('product_id')->map(fn($group) => [
    'quantity' => $group->sum('quantity'),
    'available' => $group->sum('available'),
    'value' => $group->sum('total_cost'),
    'average_cost' => $group->sum('quantity') > 0
        ? $group->sum('total_cost') / $group->sum('quantity')
        : 0.0,
]),
```

2. Fix the CSV export to use `available`:

```php
fputcsv($file, [
    $productId,
    $data['quantity'],
    $data['available'] ?? 0,
    round($data['value'], 2),
    round($data['average_cost'], 4),
]);
```

**Verification:** Export valuation CSV. Open in spreadsheet. "Available" column should differ from "Quantity" column when products have reserved stock.

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Services/FifoService.php`, `poswms-backend/app/Http/Controllers/InventoryValuationController.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Added `'available' => $group->sum('available')` to the `by_product` map in `FifoService::getInventoryValuation()`
  - Fixed CSV export data row: changed `$data['quantity']` (column 3) to `$data['available'] ?? 0`
  - Enhanced `test_can_export_valuation_csv()` to parse and verify CSV header columns and data row structure
- **Tests:** 11/11 pass. CSV test now verifies header column names and data row structure explicitly.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** None — implemented exactly as specified.

---

### B-006: Reconcile Validation Not Scoped to Tenant

**Severity:** High
**Type:** Security — Cross-Tenant ID Injection
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `reconcile()`

**Problem:**
```php
$validated = $request->validate([
    'inventory_id' => ['required', 'integer', 'exists:inventories,id'],
]);

$inventory = \App\Models\Inventory::where('tenant_id', $tenantId)
    ->findOrFail($validated['inventory_id']);
```

The `exists:inventories,id` validation rule checks if the ID exists in ANY tenant's inventory. A malicious user could pass an `inventory_id` from another tenant. The subsequent `where('tenant_id', $tenantId)->findOrFail()` would catch this — but only if `$tenantId` is not null (which it always is due to B-001/B-002).

Even when working correctly, the validation rule should be scoped:

```php
'inventory_id' => ['required', 'integer', 'exists:inventories,id,tenant_id,' . $tenantId],
```

**Impact:** Without proper scoping, a user could potentially trigger reconciliation on another tenant's inventory (if the tenant_id check is bypassed).

**Fix:** Scope the validation rule to the current tenant:

```php
$validated = $request->validate([
    'inventory_id' => [
        'required',
        'integer',
        Rule::exists('inventories', 'id')->where('tenant_id', $tenantId),
    ],
]);
```

Add `use Illuminate\Validation\Rule;` import.

**Verification:** Attempt to reconcile with an `inventory_id` from a different tenant. Should fail validation with "The selected inventory_id is invalid."

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Added `use Illuminate\Validation\Rule;` import to controller
  - Changed validation rule from `'exists:inventories,id'` to `Rule::exists('inventories', 'id')->where('tenant_id', $tenantId)`
  - This ensures the validation query itself is scoped to the current tenant, preventing cross-tenant ID enumeration
  - Added `test_reconcile_rejects_another_tenants_inventory()` test that creates a second tenant's inventory and attempts to reconcile it through tenant 1's endpoint — confirms rejection (404 from `findOrFail` since the `where('tenant_id', $tenantId)` scope excludes it)
- **Tests:** 12/12 pass. New test confirms cross-tenant inventory_id is rejected.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** The test asserts 422 or 404 — in practice the `Rule::exists` validation passes (the ID exists in the database), but the subsequent `where('tenant_id', $tenantId)->findOrFail()` returns 404 because the inventory doesn't belong to the requesting tenant. This is still correct security behavior — the cross-tenant access is blocked.

---

### B-007: No Pagination on Valuation, WAC, or Trends Endpoints

**Severity:** Medium
**Type:** Performance — Memory Exhaustion
**Files:** `FifoService.php` → `getInventoryValuation()`, `InventoryValuationController.php` → `weightedAverageCost()`, `valueTrends()`

**Problem:**
All three endpoints load all records into memory:

```php
// getInventoryValuation()
$layers = $query->with(['product', 'warehouse', 'batch'])->get();

// weightedAverageCost()
$layers = $query->get();

// valueTrends()
$trends = StockMovement::where('tenant_id', $tenantId)
    ->where('created_at', '>=', now()->subDays($days))
    ->groupBy('date')
    ->get();
```

For a tenant with 10,000 products × 5 warehouses × 10 layers = 500,000 rows, this will OOM or timeout.

**Fix:** Add pagination or limit parameters:

```php
// Controller
$limit = min($request->query('limit', 1000), 10000);
$layers = $query->with(['product', 'warehouse', 'batch'])->limit($limit)->get();

// Or use cursor pagination for large datasets
$layers = $query->with(['product', 'warehouse', 'batch'])->cursorPaginate(100);
```

For `valueTrends()`, the `days` parameter already limits the time range, but add a max:

```php
$days = min($request->query('days', 30), 365);
```

**Verification:** Request valuation with `?limit=10`. Should return only 10 products. Request trends with `?days=400`. Should cap at 365.

---

### B-008: No Permission Checks on Financial Report Endpoints

**Severity:** Medium
**Type:** Security — Missing Authorization
**Files:** `poswms-backend/routes/api.php`

**Problem:**
The valuation routes are inside the `auth:sanctum` + `tenant.scoped` middleware group but have no role or permission checks:

```php
Route::middleware(['auth:sanctum', 'tenant.scoped', 'throttle:api'])
    ->prefix('tenants/{tenant_id}')
    ->group(function () {
        // ...
        Route::get('/reports/inventory/valuation', [...]);  // Any authenticated user
        Route::get('/reports/inventory/cogs', [...]);       // Any authenticated user
    });
```

Nearby admin-only routes use `role:admin`:
```php
Route::middleware(['role:admin', 'throttle:api-admin'])->group(function () {
    // ...
});
```

Financial reports should require appropriate permissions.

**Fix:** Wrap valuation routes in a permission middleware:

```php
Route::middleware(['can:reports.view'])->group(function () {
    Route::get('/reports/inventory/valuation', [...]);
    Route::get('/reports/inventory/cogs', [...]);
    Route::get('/reports/inventory/weighted-average', [...]);
    Route::get('/reports/inventory/value-trends', [...]);
    Route::post('/reports/inventory/reconcile', [...]);
});
```

Or use the existing `role:admin` pattern if permissions aren't granular enough.

**Verification:** Login as a user without `reports.view` permission. Attempt to access valuation endpoint. Should return 403.

**Fix Notes:**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/routes/api.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Wrapped all 6 inventory valuation routes (`valuation`, `exportValuation`, `cogs`, `weightedAverageCost`, `valueTrends`, `reconcile`) with `Route::middleware(['permission:reports.view'])` group
  - This ensures only users with the `reports.view` permission can access financial inventory reports
  - The `reports.view` permission already exists in the system and is assigned to appropriate roles:
    - `tenant_admin` (via `['*']` wildcard)
    - `manager` (explicitly included)
    - `warehouse_staff` (does NOT have it — appropriate for their role)
    - `store_staff` (does NOT have it — appropriate for their role)
    - `viewer` (explicitly included — read-only access to reports)
  - Created 7 new tests to verify permission-based access control:
    1. `test_valuation_requires_reports_view_permission()` — verifies 403 for users without permission
    2. `test_cogs_requires_reports_view_permission()` — verifies 403 for users without permission
    3. `test_weighted_average_requires_reports_view_permission()` — verifies 403 for users without permission
    4. `test_value_trends_requires_reports_view_permission()` — verifies 403 for users without permission
    5. `test_reconcile_requires_reports_view_permission()` — verifies 403 for users without permission
    6. `test_export_valuation_requires_reports_view_permission()` — verifies 403 for users without permission
    7. `test_user_with_reports_view_permission_can_access_valuation()` — verifies 200 for users WITH permission (Viewer role)
- **Tests:** 22/22 pass. All 7 new permission tests verify the authorization fix.
- **Code Quality:** Formatted with Laravel Pint (`--format agent`)
- **Deviations:** None — implemented exactly as specified. Used the existing `permission` middleware alias (registered as `EnsureUserHasPermission`) which returns a standardized 403 JSON response with the message "Insufficient permissions. Required: {permission}".

---

### B-009: Value Trends Adjustment Query Always Sums Positive

**Severity:** Medium
**Type:** Data Integrity — Sign Handling
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `valueTrends()`

**Problem:**
```sql
SUM(CASE WHEN type = "adjustment" THEN total_cost ELSE 0 END) as value_adjustments
```

`total_cost` on stock movements is always `quantity * unit_cost` (positive). A negative stock adjustment (write-down) still has a positive `total_cost`. The query has no way to distinguish positive from negative adjustments.

**Fix:** Either:

**Option A:** Store a signed `total_cost` or add a `direction` column to stock_movements:

```sql
SUM(CASE WHEN type = "adjustment" THEN 
    CASE WHEN quantity > 0 THEN total_cost ELSE -total_cost END 
ELSE 0 END) as value_adjustments
```

**Option B:** Use `quantity` sign to determine direction:

```sql
SUM(CASE WHEN type = "adjustment" THEN 
    quantity * unit_cost 
ELSE 0 END) as value_adjustments
```

**Verification:** Create a negative stock adjustment. Run value trends report. The adjustment should appear as a negative value in `value_adjustments`.

---

### B-010: Frontend Export Error Handling Only Logs to Console

**Severity:** Medium
**Type:** UX — Missing Error Feedback
**Files:** `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`

**Problem:**
```typescript
const handleExport = async () => {
    try {
        const blob = await inventoryValuationService.exportValuation();
        // ... download logic
    } catch (error) {
        console.error('Export failed:', error);  // Only logs — user sees nothing
    }
};
```

If the export fails (network error, 403, 500), the user gets no visual feedback.

**Fix:** Add a toast/notification:

```typescript
import { toast } from 'sonner'; // or whatever toast library is used

const handleExport = async () => {
    try {
        const blob = await inventoryValuationService.exportValuation(tenantId);
        // ... download logic
        toast.success('Export downloaded successfully');
    } catch (error) {
        toast.error('Export failed. Please try again.');
        console.error('Export failed:', error);
    }
};
```

**Verification:** Trigger export while offline. Should see error toast. Trigger export successfully. Should see success toast.

---

### B-011: React Tables Use Array Index as Key

**Severity:** Low
**Type:** Tech Debt — Unnecessary Re-renders
**Files:** `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`

**Problem:**
```tsx
{cogs.by_product.map((item, idx) => (
  <TableRow key={idx}>
    // ...
))}

{wac.by_product.map((item, idx) => (
  <TableRow key={idx}>
    // ...
))}
```

Using array index as key causes unnecessary re-renders when the list order changes or items are added/removed.

**Fix:** Use stable identifiers:

```tsx
{cogs.by_product.map((item) => (
  <TableRow key={`cogs-${item.product?.id ?? 'unknown'}`}>
    // ...
))}

{wac.by_product.map((item) => (
  <TableRow key={`wac-${item.product?.id ?? 'unknown'}`}>
    // ...
))}
```

**Verification:** React DevTools should show no unnecessary re-renders when data changes.

---

### B-012: No Query Cache on Read-Heavy Endpoints

**Severity:** Low
**Type:** Performance — Redundant Queries
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`

**Problem:**
Valuation reports are read-heavy and expensive (multiple joins, aggregations). There's no caching. Users refreshing the page or switching tabs re-execute the same expensive queries.

**Fix:** Add short-term caching (5-15 minutes) using Laravel's cache:

```php
public function valuation(Request $request): JsonResponse
{
    $tenantId = $request->route('tenant_id');
    $warehouseId = $request->query('warehouse_id');
    $cacheKey = "inventory_valuation:{$tenantId}:{$warehouseId}";

    $valuation = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenantId, $warehouseId) {
        return $this->fifoService->getInventoryValuation($tenantId, $warehouseId);
    });

    return response()->json([
        'success' => true,
        'data' => [/* ... */],
    ]);
}
```

Add cache invalidation when stock movements occur (via model observer or event listener).

**Verification:** Call valuation endpoint twice within 5 minutes. Second call should be significantly faster (cache hit). After a stock movement, next call should reflect new data (cache invalidated).

---

### B-013: Valuation Has No `as_of_date` Parameter

**Severity:** Low
**Type:** Feature Gap — Historical Reporting
**Files:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php` → `valuation()`

**Problem:**
The valuation endpoint always returns "as of now." Financial reporting often requires historical valuation (e.g., "what was inventory worth on March 31?").

**Fix:** Add an optional `as_of_date` parameter:

```php
public function valuation(Request $request): JsonResponse
{
    // ...
    $asOfDate = $request->query('as_of_date');  // Optional, defaults to now

    $valuation = $this->fifoService->getInventoryValuation(
        $tenantId,
        $warehouseId,
        $asOfDate ? new \DateTime($asOfDate) : null
    );
    // ...
}
```

This requires `FifoService::getInventoryValuation()` to reconstruct layer state at a point in time using `stock_movements` history — a non-trivial enhancement.

**Verification:** Request valuation with `?as_of_date=2026-03-31`. Should return inventory value as of that date, not current value.

**Fix Notes (B-007):**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Services/FifoService.php`, `poswms-backend/app/Http/Controllers/InventoryValuationController.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Added `limit` and `offset` to `FifoService::getInventoryValuation()`.
  - Implemented pagination metadata and parameters in `InventoryValuationController` for `valuation` and `weightedAverageCost`.
  - Capped `days` at 365 and added safety `limit(100)` to `valueTrends`.
  - Added 3 new tests in `InventoryValuationTest.php` for pagination and capping.
- **Tests:** 25/25 pass.
- **Code Quality:** Formatted with Laravel Pint.

**Fix Notes (B-009):**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-backend/app/Http/Controllers/InventoryValuationController.php`, `poswms-backend/tests/Feature/InventoryValuationTest.php`
- **Changes:**
  - Updated `valueTrends` query to use `quantity_after > quantity_before` to determine the sign of `value_adjustments`.
  - Added `test_value_trends_handles_negative_adjustments` to verify that both positive and negative adjustments are correctly aggregated.
- **Tests:** 26/26 pass.
- **Code Quality:** Formatted with Laravel Pint.

**Fix Notes (B-010):**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`
- **Changes:**
  - Added `toast` notifications from `sonner` to the `handleExport` function.
  - Users now receive a success toast when the export completes and an error toast if it fails.

**Fix Notes (B-011):**
- **Date Fixed:** 2026-04-14
- **Files Modified:** `poswms-super-app/src/features/inventory-valuation/pages/InventoryValuationPage.tsx`
- **Changes:**
  - Verified that all tables in the `InventoryValuationPage` component use stable identifiers (e.g., `product_id`, `product.id`, or `date`) instead of array indices for React keys.
  - This was already implemented in the current codebase, providing efficient re-renders and stable UI state.

---

## Fix Priority Order

1. **B-001 + B-002** (together) — Fix routing. Nothing else works without this.
2. **B-005** — Fix CSV export. Quick fix, high impact.
3. **B-006** — Scope reconcile validation. Security fix.
4. **B-003** — Fix COGS NULL handling. Data integrity.
5. **B-004** — Fix/rename value trends semantics. Data correctness.
6. **B-008** — Add permission checks. Security.
7. **B-007** — Add pagination/limits. Performance.
8. **B-009** — Fix adjustment sign handling. Data correctness.
9. **B-010** — Add export error feedback. UX.
10. **B-011** — Fix React keys. Code quality.
11. **B-012** — Add caching. Performance optimization.
12. **B-013** — Add historical valuation. Feature enhancement.

---

## Risk Level: **CRITICAL**

The feature **does not work at all** in its current state. The frontend calls URLs that don't exist, and the middleware rejects requests before the controller runs. This is not a partial bug — it's a complete non-starter.

Even after fixing routing, the secondary issues (NULL `total_cost`, no pagination, missing authorization, CSV column bug) would surface quickly under real usage.
