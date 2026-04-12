# BUG-001: Batches Page — Critical Audit Fixes

| Field        | Value                                                                 |
| ------------ | --------------------------------------------------------------------- |
| **Created**  | 2026-04-12                                                            |
| **Source**   | Full implementation audit of Batches pages (frontend + backend)       |
| **Risk**     | **High**                                                              |
| **Status**   | Fixed                                                                 |
| **Scope**    | `poswms-backend` + `poswms-super-app`                                 |
| **Pages**    | `/wms/batches`, `/wms/batches/:batchId`                               |
| **Routes**   | `/api/v1/admin/pos/batches/*`, `/api/v1/tenants/{id}/batches/*`       |

---

## Summary

A comprehensive audit of the Batches page implementation identified **15 issues** across correctness, security, performance, and maintainability. Six are ship-blockers that must be fixed before production deployment.

---

## Bug Inventory

### CRITICAL — Ship Blockers

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-001 | Export route unreachable (route ordering bug)      | Critical | Fixed  |
| B-002 | Expire batch records wrong quantity in stock move  | Critical | Fixed  |
| B-003 | No DB locking on batch expiry (race condition)     | Critical | Fixed  |
| B-004 | isExpiringSoon() returns true for already-expired  | Critical | Fixed  |
| B-005 | Frontend filters type missing tenant_id            | Critical | Fixed  |

### HIGH — Must Fix Before Ship

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-006 | Stats total_value loads all rows into PHP memory   | High     | Fixed  |
| B-007 | CSV export dumps all tenants with no size cap      | High     | Fixed  |
| B-008 | Search LIKE wildcards not escaped                  | High     | Fixed  |
| B-009 | daysUntilExpiry() hides negative values for expired| High     | Fixed  |

### MEDIUM — Should Fix

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-010 | product_name sort key not in backend allowlist     | Medium   | Fixed  |
| B-011 | Detail page shows "not found" for 500 errors       | Medium   | Fixed  |
| B-012 | Super admin tenant isolation is implicit/fragile   | Medium   | Fixed  |
| B-013 | XSS sanitization in model boot instead of Request  | Medium   | Fixed  |

### LOW — Tech Debt

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-014 | Batch number generation uses non-unique uniqid()   | Low      | Fixed  |
| B-015 | No pagination UI (only Prev/Next buttons)          | Low      | Fixed  |

---

## Detailed Bug Specifications

### B-001: Export Route Unreachable

**Severity:** Critical  
**Type:** Bug — Routing  
**Files:** `routes/api.php` (lines 183-189)

**Problem:**
Laravel matches routes in declaration order. `GET /api/v1/admin/pos/batches/export` matches the `{batchId}` route first, treating `"export"` as an integer ID. `findOrFail("export")` throws `ModelNotFoundException` → 404.

**Current code:**
```php
Route::get('/pos/batches/{batchId}', [BatchManagementController::class, 'show']);
// ...
Route::get('/pos/batches/export', [BatchManagementController::class, 'export']); // NEVER REACHED
```

**Fix:** Move the `export` route **before** the `{batchId}` route.

```php
Route::get('/pos/batches/stats', ...);
Route::get('/pos/batches/expiring', ...);
Route::get('/pos/batches/export', ...);           // ← BEFORE {batchId}
Route::get('/pos/batches/{batchId}', ...);
Route::post('/pos/batches/{batchId}/expire', ...);
```

**Same fix needed for tenant-scoped routes** (lines 394-400).

**Verification:** `curl GET /api/v1/admin/pos/batches/export` should return CSV, not 404.

---

### B-002: Expire Batch Records Wrong Quantity in Stock Movement

**Severity:** Critical  
**Type:** Bug — Data Integrity  
**Files:** `app/Services/FifoService.php` → `expireBatch()`

**Problem:**
When expiring a batch, the stock movement records `quantity = initial_quantity` instead of `remaining_quantity`. If a batch had 1000 units initially but only 200 remaining, the audit trail shows a loss of 1000 units — corrupting inventory reports.

**Current code:**
```php
StockMovement::recordMovement(
    quantity: $batch->initial_quantity,
    quantityBefore: $batch->initial_quantity,
    quantityAfter: 0,
    ...
);
```

**Fix:**
```php
StockMovement::recordMovement(
    quantity: $batch->remaining_quantity,
    quantityBefore: $batch->remaining_quantity,
    quantityAfter: 0,
    ...
);
```

**Verification:** Create batch with 1000 units, consume 800, expire it. Stock movement should show quantity=200, not 1000.

---

### B-003: No Database Locking on Batch Expiry

**Severity:** Critical  
**Type:** Bug — Race Condition  
**Files:** `app/Http/Controllers/BatchManagementController.php` → `expire()`

**Problem:**
Two concurrent requests can both read `status !== 'expired'`, both call `expireBatch()`, and the stock movement gets recorded twice — double-counting the inventory loss.

**Current code:**
```php
$batch = $query->findOrFail($batchId);  // No lock
```

**Fix:**
```php
$batch = $query->lockForUpdate()->findOrFail($batchId);
```

**Verification:** Send two concurrent POST requests to expire the same batch. Only one should succeed; the other should get a 422 "already expired" response.

---

### B-004: isExpiringSoon() Returns True for Already-Expired Batches

**Severity:** Critical  
**Type:** Bug — Logic Error  
**Files:** `app/Models/InventoryBatch.php` → `isExpiringSoon()`

**Problem:**
`diffInDays()` returns the **absolute** difference. A batch that expired 200 days ago returns `200`, which is `<= 365`, so `isExpiringSoon(365)` returns `true` for long-expired batches.

**Current code:**
```php
public function isExpiringSoon(int $days = 30): bool
{
    if ($this->expiry_date === null) {
        return false;
    }
    return $this->expiry_date->diffInDays(now()) <= $days;
}
```

**Fix:**
```php
public function isExpiringSoon(int $days = 30): bool
{
    if ($this->expiry_date === null) {
        return false;
    }

    $daysUntil = now()->diffInDays($this->expiry_date, false);

    return $daysUntil > 0 && $daysUntil <= $days;
}
```

The `false` second parameter makes `diffInDays` return negative values for past dates.

**Verification:** A batch with `expiry_date` 5 days ago should return `false` for `isExpiringSoon(30)`.

---

### B-005: Frontend Filters Type Missing tenant_id

**Severity:** Critical  
**Type:** Bug — Type Safety  
**Files:** `poswms-super-app/src/features/inventory-batches/services/inventoryBatchService.ts`

**Problem:**
The `InventoryBatchFilters` interface is missing `tenant_id`, but `InventoryBatchFiltersComponent` sets `filters.tenant_id`. TypeScript silently allows this but it's a latent type error.

**Current interface:**
```typescript
export interface InventoryBatchFilters {
  page?: number;
  per_page?: number;
  product_id?: number;
  warehouse_id?: number;
  status?: string;
  expiry_status?: string;
  days_until_expiry?: number;
  search?: string;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  // tenant_id is MISSING
}
```

**Fix:** Add `tenant_id?: number;` to the interface.

---

### B-006: Stats total_value Loads All Rows Into PHP Memory

**Severity:** High  
**Type:** Performance  
**Files:** `app/Http/Controllers/BatchManagementController.php` → `stats()`

**Problem:**
```php
$totalValue = (clone $query)->where('status', 'active')
    ->get()  // Loads ALL active batches into memory
    ->sum(fn($b) => $b->remaining_quantity * $b->unit_cost);
```

With 100K+ active batches, this allocates hundreds of MB.

**Fix:**
```php
$totalValue = (clone $query)->where('status', 'active')
    ->selectRaw('SUM(remaining_quantity * unit_cost) as total')
    ->value('total') ?? 0;
```

---

### B-007: CSV Export Dumps All Tenants With No Size Cap

**Severity:** High  
**Type:** Security / Performance  
**Files:** `app/Http/Controllers/BatchManagementController.php` → `export()`

**Problem:**
Super admin export has no tenant filter and no pagination — it calls `->get()` on the entire `inventory_batches` table. A single request can dump gigabytes of CSV.

**Fix:**
1. Add a `tenant_id` query parameter for super admin exports
2. Add pagination or a max row limit (e.g., 10,000 rows)
3. Consider async export with email notification for large datasets

---

### B-008: Search LIKE Wildcards Not Escaped

**Severity:** High  
**Type:** Security — Injection  
**Files:** `app/Http/Controllers/BatchManagementController.php` → `index()`, `export()`

**Problem:**
```php
$search = $validated['search'];
$query->where('batch_number', 'like', "%{$search}%");
```

If a user searches for `%%` or `__`, the LIKE pattern matches everything. A search for `test%_` has unintended wildcard behavior.

**Fix:** Escape LIKE special characters:
```php
$search = str_replace(['%', '_'], ['\%', '\_'], $validated['search']);
$query->where('batch_number', 'like', "%{$search}%");
```

---

### B-009: daysUntilExpiry() Hides Negative Values for Expired Batches

**Severity:** High  
**Type:** UX / Data Loss  
**Files:** `app/Models/InventoryBatch.php` → `daysUntilExpiry()`

**Problem:**
```php
return max(0, $this->expiry_date->diffInDays(now()));
```

Expired batches return `0` instead of `-5` (5 days overdue). The frontend cannot distinguish "expiring today" from "expired 30 days ago."

**Fix:**
```php
public function daysUntilExpiry(): ?int
{
    if ($this->expiry_date === null) {
        return null;
    }

    return (int) now()->diffInDays($this->expiry_date, false);
}
```

Update frontend to handle negative values (e.g., show "Expired 5 days ago").

---

### B-010: product_name Sort Key Not in Backend Allowlist

**Severity:** Medium  
**Type:** Bug — UX  
**Files:** `poswms-super-app/src/features/inventory-batches/components/InventoryBatchesTable.tsx`, `app/Http/Controllers/BatchManagementController.php`

**Problem:**
Frontend sends `sort_by=product_name` but backend only accepts: `received_date, expiry_date, remaining_quantity, unit_cost, created_at`. Validation rejects it, frontend silently falls back to `created_at`.

**Fix (option A — backend):** Add `product_name` to allowlist and implement `orderBy` via `whereHas('product', fn($q) => $q->orderBy('name', $dir))`.

**Fix (option B — frontend):** Remove the sortable header for Product column.

---

### B-011: Detail Page Shows "Not Found" for 500 Errors

**Severity:** Medium  
**Type:** UX  
**Files:** `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchDetailPage.tsx`

**Problem:**
```tsx
if (!data?.data?.batch) {
  return <div>Batch not found</div>;
}
```

This catches both 404 (batch doesn't exist) and 500 (server error). Users see "Batch not found" for infrastructure failures.

**Fix:** Check `error` from `useQuery` and show appropriate message:
```tsx
if (error) {
  return <ErrorMessage error={error} />;
}
if (!data?.data?.batch) {
  return <div>Batch not found</div>;
}
```

---

### B-012: Super Admin Tenant Isolation Is Implicit/Fragile

**Severity:** Medium  
**Type:** Architecture  
**Files:** `app/Http/Controllers/BatchManagementController.php`, `app/Models/Scopes/TenantScope.php`

**Problem:**
Super admin queries work because `TenantScope` returns early when no tenant context exists. This is fragile — if any middleware sets `current_tenant_id` on admin requests, all super admin queries break silently.

**Fix:** Explicitly disable the tenant scope for super admin queries:
```php
$query = InventoryBatch::withoutGlobalScope(TenantScope::class);
```

---

### B-013: XSS Sanitization in Model Boot Instead of Form Request

**Severity:** Medium  
**Type:** Architecture  
**Files:** `app/Models/InventoryBatch.php` → `boot()`

**Problem:**
XSS sanitization (`strip_tags`) belongs in validation/Form Request, not in model boot. Model boot runs on every save (including internal service calls), which can silently corrupt data that was already sanitized.

**Fix:** Move sanitization to a Form Request class. Remove from model boot.

---

### B-014: Batch Number Generation Uses Non-Unique uniqid()

**Severity:** Low  
**Type:** Tech Debt  
**Files:** `app/Services/FifoService.php` → `generateBatchNumber()`

**Problem:**
```php
$random = strtoupper(substr(uniqid(), -6));
```

`uniqid()` is not cryptographically secure and has collision risk under high concurrency.

**Fix:**
```php
$random = strtoupper(bin2hex(random_bytes(3)));
```

---

### B-015: No Pagination UI (Only Prev/Next Buttons)

**Severity:** Low  
**Type:** UX / Tech Debt  
**Files:** `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchesPage.tsx`

**Problem:**
Users can only navigate one page at a time. With 500 batches at 20/page, that's 25 clicks to reach the last page.

**Fix:** Add page number buttons (show current ± 2 pages, first, last, ellipsis).

---

## Fix Priority Order

1. **B-001** — Route ordering (blocks export entirely)
2. **B-002** — Wrong stock movement quantity (data corruption)
3. **B-003** — Race condition on expiry (data corruption)
4. **B-004** — isExpiringSoon logic error (incorrect alerts)
5. **B-005** — Missing TypeScript type (type safety)
6. **B-006** — Stats memory usage (performance)
7. **B-008** — LIKE wildcard injection (security)
8. **B-009** — daysUntilExpiry hides info (UX)
9. **B-007** — CSV export size cap (performance/security)
10. **B-010** — Sort key mismatch (UX)
11. **B-011** — Error message accuracy (UX)
12. **B-012** — Explicit tenant scope bypass (architecture)
13. **B-013** — Move sanitization to Form Request (architecture)
14. **B-014** — Batch number uniqueness (tech debt)
15. **B-015** — Pagination UI (UX)

---

## Testing Requirements

Each fix must be accompanied by:

1. **Backend Feature Test** — `tests/Feature/BatchManagementTest.php` (new file)
   - Test index with all filter combinations
   - Test stats aggregation
   - Test show with layers
   - Test expire with locking (concurrent requests)
   - Test export route accessibility
   - Test search with LIKE special characters

2. **Run existing tests** — Ensure no regressions:
   ```bash
   php artisan test --compact tests/Feature/FifoInventoryTest.php
   php artisan test --compact tests/Feature/StockAdjustmentTest.php
   ```

3. **Frontend verification** — Manual testing via browser:
   - Navigate to `/wms/batches`
   - Apply all filters
   - Sort by each column
   - Click export CSV
   - Navigate to batch detail page
   - Expire a batch
   - Verify error states

---

## Files Modified (Summary)

| File | Bugs Fixed |
|------|------------|
| `routes/api.php` | B-001 |
| `app/Http/Controllers/BatchManagementController.php` | B-002, B-003, B-006, B-007, B-008, B-012 |
| `app/Services/FifoService.php` | B-002, B-014 |
| `app/Models/InventoryBatch.php` | B-004, B-009, B-013 |
| `poswms-super-app/src/features/inventory-batches/services/inventoryBatchService.ts` | B-005 |
| `poswms-super-app/src/features/inventory-batches/components/InventoryBatchesTable.tsx` | B-010 |
| `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchDetailPage.tsx` | B-011 |
| `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchesPage.tsx` | B-015 |
| `tests/Feature/BatchManagementTest.php` | **NEW** — test coverage for all fixes |

---

## Fix Notes

**Fixed:** 2026-04-12  
**Tests:** 18 new tests in `BatchManagementTest.php` — all passing  
**Regression tests:** `FifoInventoryTest` (22 pass), `StockAdjustmentTest` (22 pass) — all passing

### Deviations from Plan

- **B-002 (expireBatch quantity):** Captured `remaining_quantity` into a local variable `$remainingQty` *before* zeroing it out, because the model's `saving` hook clamps negative values and the `save()` in `expireBatch` zeroes it before the stock movement is recorded. Using a local variable ensures the correct value is passed to `StockMovement::recordMovement()`.

- **B-010 (product_name sort):** Used `DB::raw()` with a correlated subquery instead of `whereHas()->orderBy()` to avoid eager loading overhead and keep the query efficient. The subquery `(SELECT name FROM products WHERE products.id = inventory_batches.product_id)` is indexed via the FK.

- **B-013 (XSS sanitization):** Moved sanitization to `FifoService::createBatch()` rather than creating a Form Request, because batches are created programmatically via the service layer (not directly from user input in a form). The model boot still validates quantities and status whitelist — those are data integrity guards, not XSS concerns.

- **B-014 (batch number format):** Test was updated to create batches via `FifoService::createBatch()` instead of the factory, because the factory overrides `batch_number` with its own format (`BATCH-` + `uniqid()`). The factory format was left unchanged to avoid breaking existing tests.

### Files Modified

| File | Bugs Fixed |
|------|------------|
| `routes/api.php` | B-001 (admin + tenant route reordering) |
| `app/Http/Controllers/BatchManagementController.php` | B-003 (lockForUpdate), B-006 (DB aggregate), B-007 (tenant filter + 10K cap), B-008 (LIKE escape), B-010 (product_name sort), B-012 (withoutGlobalScope) |
| `app/Services/FifoService.php` | B-002 (remaining_qty capture), B-013 (XSS in createBatch), B-014 (random_bytes) |
| `app/Models/InventoryBatch.php` | B-004 (signed diff), B-009 (negative days), B-013 (removed XSS from boot) |
| `poswms-super-app/src/features/inventory-batches/services/inventoryBatchService.ts` | B-005 (tenant_id type) |
| `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchDetailPage.tsx` | B-011 (error handling) |
| `poswms-super-app/src/features/inventory-batches/pages/InventoryBatchesPage.tsx` | B-015 (pagination UI) |
| `tests/Feature/BatchManagementTest.php` | **NEW** — 18 tests covering all fixes |
