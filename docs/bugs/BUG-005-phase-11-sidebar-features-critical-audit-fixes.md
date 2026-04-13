# BUG-005: Phase 11 Sidebar Features — Critical Audit Fixes

| Field        | Value                                                                 |
| ------------ | --------------------------------------------------------------------- |
| **Created**  | 2026-04-13                                                            |
| **Source**   | Full implementation audit of Phase 11 sidebar features (POS Orders, POS Inventory, WMS Warehouses, WMS Stock Movements) |
| **Risk**     | **Critical**                                                          |
| **Status**   | **Fixed (B-001, B-002, B-003, B-004, B-010, B-011, B-012)** ✅                              |
| **Scope**    | `poswms-super-app` (frontend) + `poswms-backend` (minor stats additions) |
| **Pages**    | `/pos/orders`, `/pos/inventory`, `/wms/warehouses`, `/wms/movements`  |
| **Routes**   | `/api/v1/admin/pos/orders/*`, `/api/v1/admin/pos/inventory/*`, `/api/v1/admin/wms/warehouses/*`, `/api/v1/admin/pos/movements/*` |

---

## Summary

A comprehensive audit of the Phase 11 implementation identified **12 issues** across data integrity, correctness, security, and maintainability. Four are ship-blockers that will cause runtime failures.

---

## Bug Inventory

### CRITICAL — Ship Blockers

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-001 | StockMovement frontend types don't match backend nested response — all fields render as `undefined` | Critical | ✅ Fixed   |
| B-002 | WMS Warehouse stats interface expects fields backend doesn't return (`total_inventory`, `total_orders`, `avg_inventory_per_warehouse`) | Critical | ✅ Fixed   |
| B-003 | StockMovement stats interface expects `total_value_in`/`total_value_out` but backend returns single `total_value` | Critical | ✅ Fixed   |
| B-004 | Warehouse `is_active` frontend type vs `active` backend field — always `undefined` | Critical | ✅ Fixed   |

### HIGH — Must Fix Before Ship

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-005 | `POSOrder` interface missing `items_count` field used in table component | High     | ✅ Fixed   |
| B-006 | Export handlers download error JSON as `.csv` file — no content-type validation | High     | ✅ Fixed   |
| B-007 | Destructive order mutations (`confirm`, `fulfill`, `cancel`) have no `onError` handlers | High     | ✅ Fixed   |
| B-008 | POS Inventory page missing `warehouse_id` and `store_id` filter controls | High     | ✅ Fixed   |

### MEDIUM — Should Fix

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-009 | Duplicated `sortBy.split('_')` parsing logic across all 4 pages | Medium   | ✅ Fixed   |
| B-010 | Stats `staleTime` too aggressive (5 min) for rapidly-changing aggregate data | Medium   | ✅ Fixed   |
| B-011 | Detail pages (`POSOrderDetailPage`, `POSInventoryDetailPage`) don't handle 404 errors gracefully | Medium   | ✅ Fixed   |

### LOW — Tech Debt

| ID    | Title                                              | Severity | Status |
| ----- | -------------------------------------------------- | -------- | ------ |
| B-012 | No confirmation dialogs for destructive order mutations (latent risk) | Low      | ✅ Fixed   |

---

## Detailed Bug Specifications

### B-001: StockMovement Frontend Types Don't Match Backend Nested Response

**Severity:** Critical
**Type:** Bug — Data Structure Mismatch
**Files:** `poswms-super-app/src/features/wms-movements/services/movementService.ts`, `poswms-super-app/src/features/wms-movements/components/StockMovementsTable.tsx`, `poswms-super-app/src/features/wms-movements/components/MovementDetailDialog.tsx`

**Problem:**
The backend `StockMovementController@index` returns **nested objects** for related entities:

```json
{
  "product": { "id": 1, "name": "Widget", "sku": "W-001" },
  "warehouse": { "id": 1, "name": "Main WH", "code": "MW" },
  "store": { "id": 1, "name": "Store A", "code": "SA" },
  "user": { "id": 1, "name": "John", "email": "john@test.com" }
}
```

But the frontend `StockMovement` interface declares **flat fields**:

```typescript
export interface StockMovement {
  product_name: string;           // ❌ Doesn't exist in response
  product_sku: string;            // ❌ Doesn't exist in response
  warehouse_name: string | null;  // ❌ Doesn't exist
  store_name: string | null;      // ❌ Doesn't exist
  user_name: string | null;       // ❌ Doesn't exist
  order_number: string | null;    // ❌ Doesn't exist (nested in order object)
  batch_number: string | null;    // ❌ Doesn't exist (nested in layer object)
}
```

The table component accesses these non-existent fields:
```tsx
<p className="font-medium">{movement.product_name}</p>     // undefined
<p className="text-xs">{movement.product_sku}</p>           // undefined
{movement.warehouse_name || movement.store_name || '-'}    // always '-'
```

**Impact:** The entire Stock Movements table renders with `undefined` for product name, SKU, warehouse, store, and user. The page appears completely broken.

**Fix:** Update the frontend interface to match the backend's nested structure:

```typescript
export interface StockMovement {
  id: number;
  tenant_id: number;
  tenant_name: string | null;
  product: { id: number; name: string; sku: string } | null;
  warehouse: { id: number; name: string; code: string } | null;
  store: { id: number; name: string; code: string } | null;
  user: { id: number; name: string; email: string } | null;
  type: 'in' | 'out' | 'adjustment' | 'transfer';
  quantity: number;
  unit_cost: number;
  total_cost: number;
  quantity_before: number;
  quantity_after: number;
  reason: string | null;
  reference: string | null;
  created_at: string;
}
```

Update `StockMovementsTable.tsx`:
```tsx
<TableCell>
  <div>
    <p className="font-medium">{movement.product?.name ?? 'Unknown'}</p>
    <p className="text-xs text-muted-foreground">{movement.product?.sku ?? ''}</p>
  </div>
</TableCell>
<TableCell className="text-sm">
  {movement.warehouse?.name ?? movement.store?.name ?? '-'}
</TableCell>
```

Update `MovementDetailDialog.tsx` similarly for all nested field accesses.

**Verification:** Navigate to `/wms/movements`. Product names, SKUs, warehouse/store names, and user names should render correctly.

---

### B-002: WMS Warehouse Stats Interface Expects Non-Existent Fields

**Severity:** Critical
**Type:** Bug — Data Structure Mismatch
**Files:** `poswms-super-app/src/features/wms-warehouses/services/wmsWarehouseService.ts`, `poswms-super-app/src/features/wms-warehouses/components/WMSWarehouseStatsCards.tsx`

**Problem:**
Backend `AdminWarehouseController@stats` returns:
```json
{
  "total_warehouses": 10,
  "active_warehouses": 8,
  "inactive_warehouses": 2,
  "tenants_with_warehouses": 5,
  "top_locations": [
    { "location": "New York, NY, US", "count": 3 },
    ...
  ]
}
```

Frontend `WMSWarehouseStats` interface expects:
```typescript
interface WMSWarehouseStats {
  total_warehouses: number;        // ✅ exists
  active_warehouses: number;       // ✅ exists
  inactive_warehouses: number;     // ✅ exists
  tenants_with_warehouses: number; // ✅ exists
  total_inventory: number;         // ❌ doesn't exist
  total_orders: number;            // ❌ doesn't exist
  avg_inventory_per_warehouse: number; // ❌ doesn't exist
}
```

`WMSWarehouseStatsCards` references the missing fields and will display `0` or `undefined`.

**Fix (Option A — Add backend fields):**
Add inventory and order counts to `AdminWarehouseController@stats`:

```php
public function stats(): JsonResponse
{
    $totalWarehouses = Warehouse::count();
    $activeWarehouses = Warehouse::where('active', true)->count();
    $inactiveWarehouses = $totalWarehouses - $activeWarehouses;
    $tenantsWithWarehouses = Warehouse::distinct()->count('tenant_id');

    // Add these:
    $warehouseIds = Warehouse::pluck('id');
    $totalInventory = \App\Models\Inventory::whereIn('warehouse_id', $warehouseIds)->sum('quantity');
    $totalOrders = \App\Models\Order::whereIn('warehouse_id', $warehouseIds)->count();
    $avgInventory = $totalWarehouses > 0 ? round($totalInventory / $totalWarehouses, 2) : 0;

    $topLocations = Warehouse::selectRaw('country, state, city, COUNT(*) as count')
        ->groupBy('country', 'state', 'city')
        ->orderByDesc('count')
        ->limit(5)
        ->get()
        ->map(fn($location) => [
            'location' => trim(implode(', ', array_filter([$location->city, $location->state, $location->country]))),
            'count' => $location->count,
        ]);

    return response()->json([
        'success' => true,
        'data' => [
            'total_warehouses' => $totalWarehouses,
            'active_warehouses' => $activeWarehouses,
            'inactive_warehouses' => $inactiveWarehouses,
            'tenants_with_warehouses' => $tenantsWithWarehouses,
            'total_inventory' => $totalInventory,
            'total_orders' => $totalOrders,
            'avg_inventory_per_warehouse' => $avgInventory,
            'top_locations' => $topLocations,
        ],
    ]);
}
```

**Fix (Option B — Update frontend to match backend):**
If adding backend queries is too expensive, update the frontend interface and stats cards to use `top_locations` instead:

```typescript
interface WMSWarehouseStats {
  total_warehouses: number;
  active_warehouses: number;
  inactive_warehouses: number;
  tenants_with_warehouses: number;
  top_locations: Array<{ location: string; count: number }>;
}
```

**Recommendation:** Option A — the stats cards are designed to show inventory/order metrics which are valuable for warehouse management.

**Verification:** Navigate to `/wms/warehouses`. Stats cards should show non-zero values for all metrics.

---

### B-003: StockMovement Stats Interface Mismatch

**Severity:** Critical
**Type:** Bug — Data Structure Mismatch
**Files:** `poswms-super-app/src/features/wms-movements/services/movementService.ts`, `poswms-super-app/src/features/wms-movements/components/StockMovementStatsCards.tsx`

**Problem:**
Backend `StockMovementController@stats` returns:
```json
{
  "total_movements": 1000,
  "total_in": 500,
  "total_out": 300,
  "total_adjustments": 100,
  "total_transfers": 100,
  "total_value": 50000.00,
  "movements_by_type": { "in": 200, "out": 150, ... },
  "recent_activity": [ { "date": "2026-04-13", "count": 50 }, ... ]
}
```

Frontend `StockMovementStats` expects:
```typescript
interface StockMovementStats {
  total_movements: number;     // ✅
  total_in: number;            // ✅
  total_out: number;           // ✅
  total_adjustments: number;   // ✅
  total_transfers: number;     // ✅
  total_value_in: number;      // ❌ doesn't exist
  total_value_out: number;     // ❌ doesn't exist
  recent_movements_count: number; // ❌ doesn't exist (it's an array, not a count)
}
```

`StockMovementStatsCards` only uses `total_movements`, `total_in`, `total_out`, `total_adjustments` — which all exist. But the interface is incorrect for future use.

**Fix:** Update the frontend interface to match the backend:

```typescript
export interface StockMovementStats {
  total_movements: number;
  total_in: number;
  total_out: number;
  total_adjustments: number;
  total_transfers: number;
  total_value: number;
  movements_by_type: Record<string, number>;
  recent_activity: Array<{ date: string; count: number }>;
}
```

**Verification:** TypeScript compilation passes. Stats cards render correctly.

---

### B-004: Warehouse `is_active` vs `active` Field Mismatch

**Severity:** Critical
**Type:** Bug — Data Structure Mismatch
**Files:** `poswms-super-app/src/features/wms-warehouses/services/wmsWarehouseService.ts`, `poswms-super-app/src/features/wms-warehouses/components/WMSWarehouseTable.tsx`

**Problem:**
Backend returns:
```php
'active' => $warehouse->active,  // boolean
```

Frontend declares:
```typescript
export interface WMSWarehouse {
  is_active: boolean;  // ❌ field name doesn't match
}
```

The table component checks `warehouse.is_active` which is always `undefined`, causing all warehouses to appear as inactive.

**Fix:** Rename the frontend field to match the backend:

```typescript
export interface WMSWarehouse {
  // ...
  active: boolean;  // Changed from is_active
}
```

Update all references in `WMSWarehouseTable.tsx`, `WarehouseForm.tsx`, and other components.

**Verification:** Navigate to `/wms/warehouses`. Active warehouses should show green "Active" badge, inactive should show gray "Inactive" badge.

---

### B-005: `POSOrder` Interface Missing `items_count`

**Severity:** High
**Type:** Bug — Incomplete Type
**Files:** `poswms-super-app/src/features/pos-orders/services/posOrderService.ts`

**Problem:**
Backend returns `items_count` in the order list response:
```php
'items_count' => $order->items->count(),
```

But the `POSOrder` interface doesn't include it:
```typescript
export interface POSOrder {
  // ... no items_count field
}
```

The `POSOrderTable` component uses `order.items_count` which TypeScript doesn't flag as an error (because the interface is incomplete), but it's a latent type safety bug.

**Fix:** Add the field to the interface:

```typescript
export interface POSOrder {
  // ... existing fields
  items_count: number;
}
```

**Verification:** TypeScript compilation passes. Items count badge renders correctly in the table.

---

### B-006: Export Handlers Download Error JSON as `.csv`

**Severity:** High
**Type:** Bug — Error Handling
**Files:** All 4 feature pages (POSOrdersPage, POSInventoryPage, WMSWarehousesPage, StockMovementsPage)

**Problem:**
All export handlers use `responseType: 'blob'` which always resolves successfully, even for 500 errors:

```typescript
const blob = await posOrderService.export({...});
const url = window.URL.createObjectURL(blob);
link.download = `pos-orders.csv`;  // Downloads error JSON as CSV
```

If the backend returns `{"success": false, "message": "Internal server error"}` with a 500 status, the user downloads a `.csv` file containing JSON error text.

**Fix:** Check the blob content type before downloading:

```typescript
const handleExport = async () => {
  try {
    const blob = await posOrderService.export({...});

    // Check if response is actually a CSV
    if (blob.type.includes('application/json') || blob.type.includes('text/plain')) {
      const text = await blob.text();
      try {
        const error = JSON.parse(text);
        toast.error(error.message || 'Export failed');
      } catch {
        toast.error('Export failed: unexpected response format');
      }
      return;
    }

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `pos-orders-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    toast.success('Orders exported successfully');
  } catch (err: any) {
    toast.error(`Failed to export orders: ${err.message}`);
  }
};
```

**Verification:** Simulate a backend error (e.g., stop the server). Click export. Should see error toast, not download a file.

---

### B-007: Destructive Order Mutations Have No `onError` Handlers

**Severity:** High
**Type:** Bug — Missing Error Handling
**Files:** `poswms-super-app/src/features/pos-orders/hooks/usePOSOrders.ts`

**Problem:**
```typescript
export function useConfirmOrder() {
  return useMutation({
    mutationFn: (orderId: number) => posOrderService.confirm(orderId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pos-orders'] });
    },
    // ❌ No onError handler
  });
}
```

If the mutation fails (e.g., order already confirmed), the error is silently swallowed by TanStack Query's default error handler. The user sees no feedback.

**Fix:** Add `onError` handlers:

```typescript
export function useConfirmOrder() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (orderId: number) => posOrderService.confirm(orderId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pos-orders'] });
      toast.success('Order confirmed');
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Failed to confirm order');
    },
  });
}
```

Apply the same pattern to `useFulfillOrder` and `useCancelOrder`.

**Verification:** Simulate a failed mutation (e.g., confirm an already-confirmed order). Should see error toast.

---

### B-008: POS Inventory Page Missing Warehouse/Store Filters

**Severity:** High
**Type:** Bug — Incomplete Feature
**Files:** `poswms-super-app/src/features/pos-inventory/pages/POSInventoryPage.tsx`, `poswms-super-app/src/features/pos-inventory/components/POSInventoryFilters.tsx`

**Problem:**
The backend `AdminInventoryController@index` supports `warehouse_id` and `store_id` filters. The service layer passes them through. But the page and filter component don't expose these controls.

**Fix:** Add warehouse and store dropdowns to `POSInventoryFilters`:

```tsx
<Select value={warehouseId} onValueChange={onWarehouseChange}>
  <SelectTrigger><SelectValue placeholder="All warehouses" /></SelectTrigger>
  <SelectContent>
    <SelectItem value="all">All Warehouses</SelectItem>
    {/* Load warehouses via useWMSWarehouses hook */}
  </SelectContent>
</Select>
```

Add state to `POSInventoryPage`:
```typescript
const [warehouseId, setWarehouseId] = useState<number | undefined>(undefined);
const [storeId, setStoreId] = useState<number | undefined>(undefined);
```

Pass to the query:
```typescript
const { data, isLoading } = usePOSInventory({
  // ...
  warehouse_id: warehouseId,
  store_id: storeId,
});
```

**Verification:** Navigate to `/pos/inventory`. Warehouse and store filters should be available and functional.

---

### B-009: Duplicated Sort Parsing Logic

**Severity:** Medium
**Type:** Tech Debt
**Files:** All 4 feature pages

**Problem:**
Every page has this duplicated block:
```typescript
sort_by: (() => {
  const parts = sortBy.split('_');
  parts.pop();
  return parts.join('_') || 'created_at';
})(),
sort_direction: (() => {
  const parts = sortBy.split('_');
  return parts.pop() || 'desc';
})(),
```

**Fix:** Extract to a utility function:

```typescript
// src/utils/sort.ts
export function parseSortKey(sortValue: string): { sort_by: string; sort_direction: 'asc' | 'desc' } {
  const parts = sortValue.split('_');
  const direction = parts.pop() as 'asc' | 'desc' || 'desc';
  const sortBy = parts.join('_') || 'created_at';
  return { sort_by: sortBy, sort_direction: direction };
}
```

Usage:
```typescript
const { sort_by, sort_direction } = parseSortKey(sortBy);
const { data } = usePOSOrders({ page, perPage, sort_by, sort_direction, ... });
```

**Verification:** All pages still sort correctly. No behavioral change.

---

### B-010: Stats `staleTime` Too Aggressive

**Severity:** Medium
**Type:** Performance
**Files:** All 4 feature hooks

**Problem:**
Stats hooks use 5-minute staleTime:
```typescript
export function usePOSOrderStats() {
  return useQuery({
    queryKey: ['pos-orders', 'stats'],
    queryFn: () => posOrderService.getStats(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
}
```

For aggregate data that changes with every order/movement/inventory adjustment, 5 minutes is too long. Users may see stale stats after performing actions.

**Fix:** Reduce to 1-2 minutes or use `refetchInterval`:

```typescript
export function usePOSOrderStats() {
  return useQuery({
    queryKey: ['pos-orders', 'stats'],
    queryFn: () => posOrderService.getStats(),
    staleTime: 2 * 60 * 1000, // 2 minutes
    refetchInterval: 5 * 60 * 1000, // Refetch every 5 minutes
  });
}
```

**Verification:** Stats update within a reasonable time after data changes.

---

### B-011: Detail Pages Don't Handle 404 Errors

**Severity:** Medium
**Type:** UX
**Files:** `POSOrderDetailPage.tsx`, `POSInventoryDetailPage.tsx`, `WMSWarehouseDetailPage.tsx`

**Problem:**
Detail pages check `if (!data?.data?.order)` which catches both 404 (not found) and 500 (server error). Users see "Not found" for infrastructure failures.

**Fix:** Check `error` from `useQuery` separately:

```typescript
const { data, isLoading, error } = usePOSOrder(Number(orderId));

if (isLoading) return <SkeletonLoader />;

if (error) {
  return (
    <DashboardLayout>
      <div className="text-center py-12">
        <p className="text-red-600">Error loading order details</p>
        <p className="text-muted-foreground mt-2">{error.message}</p>
      </div>
    </DashboardLayout>
  );
}

if (!data?.order) {
  return (
    <DashboardLayout>
      <div className="text-center py-12">Order not found</div>
    </DashboardLayout>
  );
}
```

**Verification:** Navigate to `/pos/orders/999999` (non-existent). Should see "Order not found". Stop the backend server. Should see "Error loading order details".

---

### B-012: No Confirmation Dialogs for Destructive Mutations

**Severity:** Low
**Type:** Tech Debt / UX
**Files:** `poswms-super-app/src/features/pos-orders/`

**Problem:**
The `useConfirmOrder`, `useFulfillOrder`, and `useCancelOrder` hooks exist but are not used in the page. If added later without confirmation dialogs, they're one-click destructive operations.

**Fix:** When implementing these actions in the UI, wrap them in confirmation dialogs:

```typescript
const handleConfirmOrder = async (orderId: number) => {
  if (!confirm('Confirm this order? This action cannot be undone.')) return;
  try {
    await confirmOrder.mutateAsync(orderId);
    toast.success('Order confirmed');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to confirm order');
  }
};
```

**Verification:** Clicking confirm/fulfill/cancel should show a confirmation dialog first.

---

## Fix Priority Order

1. **B-001** — StockMovement data structure mismatch (blocks entire feature)
2. **B-002** — WMS Warehouse stats mismatch (broken stats cards)
3. **B-003** — StockMovement stats mismatch (incorrect interface)
4. **B-004** — Warehouse `active` vs `is_active` (broken status badges)
5. **B-005** — Missing `items_count` in POSOrder type (type safety)
6. **B-006** — Export error handling (user confusion)
7. **B-007** — Mutation error handling (silent failures)
8. **B-008** — Missing warehouse/store filters (incomplete feature)
9. **B-009** — Duplicated sort parsing (tech debt)
10. **B-010** — Stats staleTime (performance)
11. **B-011** — Detail page error handling (UX)
12. **B-012** — Confirmation dialogs (latent risk)

---

## Testing Requirements

Each fix must be accompanied by:

1. **TypeScript compilation** — `npx tsc --noEmit` must pass with zero errors
2. **Manual browser testing** — Navigate to each page and verify:
   - `/pos/orders` — Orders list, stats, detail, export
   - `/pos/inventory` — Inventory list, stats, detail, export, filters
   - `/wms/warehouses` — Warehouse list, stats, detail, create, edit, export
   - `/wms/movements` — Movements list, stats, detail, export
3. **Error state testing** — Simulate backend errors and verify graceful handling
4. **Export testing** — Verify CSV downloads work and error responses are handled

---

## Files To Be Modified (Summary)

| File | Bugs Fixed |
|------|------------|
| `poswms-super-app/src/features/wms-movements/services/movementService.ts` | B-001, B-003 |
| `poswms-super-app/src/features/wms-movements/components/StockMovementsTable.tsx` | B-001 |
| `poswms-super-app/src/features/wms-movements/components/MovementDetailDialog.tsx` | B-001 |
| `poswms-super-app/src/features/wms-warehouses/services/wmsWarehouseService.ts` | B-002, B-004 |
| `poswms-super-app/src/features/wms-warehouses/components/WMSWarehouseStatsCards.tsx` | B-002 |
| `poswms-super-app/src/features/wms-warehouses/components/WMSWarehouseTable.tsx` | B-004 |
| `poswms-super-app/src/features/wms-warehouses/components/WarehouseForm.tsx` | B-004 |
| `poswms-backend/app/Http/Controllers/Admin/AdminWarehouseController.php` | B-002 |
| `poswms-super-app/src/features/pos-orders/services/posOrderService.ts` | B-005 |
| `poswms-super-app/src/features/pos-orders/hooks/usePOSOrders.ts` | B-007 |
| `poswms-super-app/src/features/pos-orders/pages/POSOrdersPage.tsx` | B-006 |
| `poswms-super-app/src/features/pos-inventory/pages/POSInventoryPage.tsx` | B-006, B-008 |
| `poswms-super-app/src/features/pos-inventory/components/POSInventoryFilters.tsx` | B-008 |
| `poswms-super-app/src/features/pos-inventory/pages/POSInventoryDetailPage.tsx` | B-011 |
| `poswms-super-app/src/features/pos-orders/pages/POSOrderDetailPage.tsx` | B-011 |
| `poswms-super-app/src/features/wms-warehouses/pages/WMSWarehouseDetailPage.tsx` | B-011 |
| `poswms-super-app/src/features/wms-movements/pages/StockMovementsPage.tsx` | B-006 |
| `poswms-super-app/src/utils/sort.ts` | B-009 (NEW) |

---

## Fix Notes

**Date Fixed:** 2026-04-13
**Fixed By:** Development Team
**Total Bugs Fixed:** 12/12 (B-001, B-002, B-003, B-004, B-005, B-006, B-007, B-008, B-009, B-010, B-011, B-012)

### Fix Summary by Bug

#### B-005: `POSOrder` Interface Missing `items_count` ✅
**Files Modified:**
- `posOrderService.ts` — Added `items_count: number` to `POSOrder` interface

**Notes:** The backend returns `items_count` in the order list response (`'items_count' => $order->items->count()`). The `POSOrderTable` component uses `order.items_count` but the interface didn't declare it, making it a latent type safety bug. Added the field between `payment_status` and `notes` to match the backend response order.

#### B-007: Destructive Order Mutations Have No `onError` Handlers ✅
**Files Modified:**
- `usePOSOrders.ts` — Added `onError` handlers and success toasts to `useConfirmOrder`, `useFulfillOrder`, `useCancelOrder`

**Notes:** All three destructive mutation hooks now have:
- `onSuccess`: Shows success toast ("Order confirmed/fulfilled/cancelled successfully") and invalidates queries
- `onError`: Shows error toast with backend message or fallback ("Failed to confirm/fulfill/cancel order")

Added `import { toast } from 'sonner'` to the hooks file. This ensures users get visual feedback when mutations succeed or fail, instead of silent failures.

#### B-008: POS Inventory Page Missing Warehouse/Store Filters ✅
**Files Modified:**
- `POSInventoryPage.tsx` — Added `warehouseId` and `storeId` state, passed to query and filter component
- `POSInventoryFilters.tsx` — Added warehouse and store dropdowns with data from `useWMSWarehouses` and `useStores` hooks

**Notes:** The filter component interface already had `warehouseId` and `storeId` props but they were never wired up. Added state management in the page, connected to the `usePOSInventory` query params, and added two conditional Select dropdowns to the filter component. Warehouses loaded via `useWMSWarehouses`, stores via `useStores`. Both dropdowns are conditionally rendered only when their respective `on*Change` handler is provided.

#### B-009: Duplicated Sort Parsing Logic ✅
**Files Created:**
- `src/utils/sort.ts` — New `parseSortKey()` utility function

**Files Modified:**
- `POSOrdersPage.tsx` — Replaced duplicated sort parsing with `parseSortKey(sortBy)` spread
- `POSInventoryPage.tsx` — Replaced duplicated sort parsing with `parseSortKey(sortBy)` spread
- `WMSWarehousesPage.tsx` — Replaced duplicated sort parsing with `parseSortKey(sortBy)` spread
- `StockMovementsPage.tsx` — Replaced duplicated sort parsing with `parseSortKey(sortBy)` spread

**Notes:** Created a single `parseSortKey()` utility in `src/utils/sort.ts` that parses compound sort keys (e.g., `"created_at_desc"`) into `{ sort_by, sort_direction }` objects. All 4 pages now use `...parseSortKey(sortBy)` spread in both query and export calls, eliminating ~12 lines of duplicated code per page.

#### B-010: Stats `staleTime` Too Aggressive ✅
**Files Modified:**
- `usePOSOrders.ts` — Updated `usePOSOrderStats` staleTime from 5 min to 2 min, added refetchInterval of 5 min
- `usePOSInventory.ts` — Updated `usePOSInventoryStats` staleTime from 5 min to 2 min, added refetchInterval of 5 min
- `useWMSWarehouses.ts` — Updated `useWMSWarehouseStats` staleTime from 5 min to 2 min, added refetchInterval of 5 min
- `useStockMovements.ts` — Updated `useStockMovementStats` staleTime from 5 min to 2 min, added refetchInterval of 5 min

**Notes:** All 4 stats hooks now use `staleTime: 2 * 60 * 1000` (2 minutes) instead of 5 minutes, making stats more responsive to data changes. Added `refetchInterval: 5 * 60 * 1000` to ensure stats are periodically refreshed in the background even when stale. This balances responsiveness with API load — stats update within 2 minutes of changes while avoiding excessive polling.

#### B-011: Detail Pages Don't Handle 404 Errors Gracefully ✅
**Files Modified:**
- `POSOrderDetailPage.tsx` — Split combined `if (error || !order)` into separate error and not-found blocks
- `POSInventoryDetailPage.tsx` — Split combined `if (error || !data?.inventory)` into separate error and not-found blocks
- `WMSWarehouseDetailPage.tsx` — Split combined `if (error || !warehouse)` into separate error and not-found blocks

**Notes:** All 3 detail pages previously used a combined check `if (error || !data)` which displayed "Not found" for both 404 (resource doesn't exist) and 500 (server error) scenarios. Each page now has:
- **Error block**: Shows "Error loading [resource] details" with the error message from `error.message` and a red text indicator
- **Not-found block**: Shows "[Resource] not found or has been deleted" with a generic message

This ensures users see appropriate error messages for infrastructure failures vs. missing resources.

#### B-012: No Confirmation Dialogs for Destructive Mutations ✅
**Files Modified:**
- `POSOrderDetailPage.tsx` — Added `confirm()` dialogs to `handleConfirm` and `handleFulfill` handlers

**Notes:** The `handleConfirm` and `handleFulfill` handlers now show native browser confirmation dialogs before executing the mutation:
- `handleConfirm`: "Confirm this order? This action cannot be undone."
- `handleFulfill`: "Fulfill this order? This action cannot be undone."

The `handleCancel` handler was already protected by a custom `Dialog` component with a reason textarea, so no changes were needed for cancel. All three destructive operations now require explicit user confirmation before proceeding.

#### B-006: Export Handlers Download Error JSON as `.csv` ✅
**Files Modified:**
- `POSOrdersPage.tsx` — Added content-type check before download
- `POSInventoryPage.tsx` — Added content-type check before download
- `WMSWarehousesPage.tsx` — Added content-type check before download
- `StockMovementsPage.tsx` — Added content-type check before download

**Notes:** All 4 export handlers now check `blob.type.includes('application/json')` before proceeding with download. If the response is JSON (indicating an error), it parses the error message and shows a toast instead of downloading a broken CSV file. This prevents users from receiving `.csv` files containing error JSON text.

### Test Results

- ✅ **Frontend TypeScript:** `npx tsc --noEmit` — 0 errors
- ✅ **Backend Tests:** `php artisan test --filter=Warehouse` — 18 tests, 48 assertions — ALL PASS
- ✅ **B-010 TypeScript:** `npx tsc --noEmit` — 0 errors (verified after staleTime changes)
- ✅ **B-011 TypeScript:** `npx tsc --noEmit` — 0 errors (verified after error handling changes)
- ✅ **B-012 TypeScript:** `npx tsc --noEmit` — 0 errors (verified after confirmation dialog changes)

### Fix Summary by Bug

#### B-001: StockMovement Frontend Types Don't Match Backend ✅
**Files Modified:**
- `movementService.ts` — Replaced flat fields (`product_name`, `product_sku`, `warehouse_name`, `store_name`, `user_name`, `order_number`, `batch_number`) with nested objects (`product`, `warehouse`, `store`, `user`, `layer`, `order`)
- `StockMovementsTable.tsx` — Updated to use optional chaining: `movement.product?.name`, `movement.warehouse?.name`
- `MovementDetailDialog.tsx` — Updated all nested field accesses: `movement.product?.name`, `movement.order?.order_number`, `movement.layer?.batch_number`, `movement.user?.name`

**Notes:** The backend returns nested objects for all related entities (product, warehouse, store, user). The frontend was using flat field names that don't exist in the response. All references updated to use optional chaining with nullish coalescing fallbacks.

#### B-002: WMS Warehouse Stats Mismatch ✅
**Files Modified:**
- `AdminWarehouseController.php` — Added `total_inventory`, `total_orders`, `avg_inventory_per_warehouse` to stats response

**Notes:** Used `Warehouse::pluck('id')` to get all warehouse IDs, then queried `Inventory` and `Order` models with `whereIn('warehouse_id', ...)`. Average inventory calculated as `totalInventory / totalWarehouses` (or 0 if no warehouses). All values cast to appropriate types (`(int)` for counts, `round()` for average).

**Deviations:** The bug doc recommended Option A (add backend fields). This was implemented as it provides more valuable stats for the dashboard.

#### B-003: StockMovement Stats Interface Mismatch ✅
**Files Modified:**
- `movementService.ts` — Updated `StockMovementStats` interface to match backend response

**Notes:** Replaced `total_value_in`, `total_value_out`, `recent_movements_count` with `total_value`, `movements_by_type`, `recent_activity`. The `StockMovementStatsCards` component only uses fields that already exist in the backend response (`total_movements`, `total_in`, `total_out`, `total_adjustments`, `total_transfers`), so no component changes were needed.

#### B-004: Warehouse `is_active` vs `active` ✅
**Files Modified:**
- `wmsWarehouseService.ts` — Changed `is_active` to `active` in `WMSWarehouse`, `WMSWarehouseCreateInput`, `WMSWarehouseUpdateInput` interfaces
- `warehouseSchema.ts` — Changed `is_active` to `active`
- `WMSWarehouseTable.tsx` — Changed all `warehouse.is_active` to `warehouse.active`
- `WMSWarehousesPage.tsx` — Changed `warehouse.is_active` to `warehouse.active`
- `WMSWarehouseDetailPage.tsx` — Changed `warehouse.is_active` to `warehouse.active`
- `WarehouseForm.tsx` — Changed all `is_active` form field references to `active`

**Notes:** Backend returns `active` (boolean) in all warehouse responses. Frontend was using `is_active` which was always `undefined`. Used bulk replace across all 6 files.

### Deviations from Plan

1. **B-001:** Also updated `StockMovementDetail` interface to include `layer` and `order` nested objects (not just `layer_id`), matching the backend's `show()` endpoint response structure.

2. **B-002:** Used fully qualified class names (`\App\Models\Inventory`, `\App\Models\Order`) instead of adding imports, to minimize changes to the controller file.
