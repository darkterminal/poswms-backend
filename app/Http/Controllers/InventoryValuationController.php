<?php

namespace App\Http\Controllers;

use App\Models\InventoryLayer;
use App\Models\StockMovement;
use App\Services\FifoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryValuationController extends Controller
{
    public function __construct(
        private FifoService $fifoService,
        private \App\Services\CacheService $cacheService
    ) {}

    /**
     * Get FIFO inventory valuation report.
     */
    public function valuation(Request $request): JsonResponse
    {
        $tenantId = (int) $request->route('tenant_id');
        $warehouseId = $request->query('warehouse_id');
        $limit = min($request->query('limit', 100), 1000);
        $offset = $request->query('offset', 0);

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $valuation = $this->cacheService->rememberReport('valuation', $tenantId, [
            'warehouse_id' => $warehouseId,
            'limit' => $limit,
            'offset' => $offset,
        ], function () use ($tenantId, $warehouseId, $limit, $offset) {
            return $this->fifoService->getInventoryValuation($tenantId, $warehouseId, (int) $limit, (int) $offset);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_quantity' => $valuation['total_quantity'],
                'total_available' => $valuation['total_available'],
                'total_value' => round($valuation['total_value'], 2),
                'layer_count' => $valuation['layer_count'],
                'pagination' => [
                    'limit' => (int) $limit,
                    'offset' => (int) $offset,
                    'total' => $valuation['total_count'] ?? $valuation['layer_count'],
                ],
                'by_product' => $valuation['by_product']->map(fn($data, $productId) => [
                    'product_id' => $productId,
                    'quantity' => $data['quantity'],
                    'value' => round($data['value'], 2),
                    'average_cost' => round($data['average_cost'], 4),
                ])->values(),
                'by_warehouse' => $valuation['by_warehouse']->map(fn($data, $warehouseId) => [
                    'warehouse_id' => $warehouseId,
                    'quantity' => $data['quantity'],
                    'value' => round($data['value'], 2),
                ])->values(),
            ],
            'message' => 'Inventory valuation retrieved successfully',
        ], 200);
    }

    /**
     * Get COGS (Cost of Goods Sold) report.
     */
    public function cogs(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $productId = $request->query('product_id');

        $validated = $request->validate([
            'date_from' => ['required', 'date', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $startDate = new \DateTime($validated['date_from']);
        $endDate = new \DateTime($validated['date_to']);

        $cogs = $this->fifoService->calculateCogs(
            $tenantId,
            $startDate,
            $endDate,
            $productId
        );

        // Get COGS breakdown by product
        $cogsByProduct = StockMovement::where('tenant_id', $tenantId)
            ->where('type', 'out')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->with('product:id,name,sku')
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(total_cost) as total_cost,
                COUNT(*) as movement_count
            ')
            ->groupBy('product_id')
            ->get()
            ->map(fn($item) => [
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                ] : null,
                'total_quantity' => $item->total_quantity,
                'total_cost' => round($item->total_cost, 2),
                'movement_count' => $item->movement_count,
                'average_unit_cost' => $item->total_quantity > 0
                    ? round($item->total_cost / $item->total_quantity, 4)
                    : 0,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $validated['date_from'],
                    'to' => $validated['date_to'],
                ],
                'summary' => [
                    'total_quantity' => $cogs['total_quantity'],
                    'total_cost' => round($cogs['total_cost'], 2),
                    'movement_count' => $cogs['movement_count'],
                    'average_unit_cost' => round($cogs['average_unit_cost'], 4),
                ],
                'by_product' => $cogsByProduct,
            ],
            'message' => 'COGS report retrieved successfully',
        ], 200);
    }

    /**
     * Get weighted average cost report.
     */
    public function weightedAverageCost(Request $request): JsonResponse
    {
        $tenantId = (int) $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $warehouseId = $request->query('warehouse_id');
        $limit = min($request->query('limit', 100), 1000);
        $offset = $request->query('offset', 0);

        $report = $this->cacheService->rememberReport('wac', $tenantId, [
            'warehouse_id' => $warehouseId,
            'limit' => $limit,
            'offset' => $offset,
        ], function () use ($tenantId, $warehouseId, $limit, $offset) {
            $query = InventoryLayer::where('tenant_id', $tenantId)
                ->fifoLayers()
                ->withStock()
                ->with(['product:id,name,sku', 'warehouse:id,name,code']);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            // Clone query for totals calculation before applying limit/offset
            $totalQuantity = (clone $query)->sum('quantity');
            $totalValue = (clone $query)->sum('total_cost');
            $totalCount = (clone $query)->count();

            $layers = $query->offset($offset)->limit($limit)->get();

            $byProduct = $layers->groupBy('product_id')->map(function ($group) {
                $totalQuantity = $group->sum('quantity');
                $totalValue = $group->sum('total_cost');

                return [
                    'product' => $group->first()?->product ? [
                        'id' => $group->first()->product->id,
                        'name' => $group->first()->product->name,
                        'sku' => $group->first()->product->sku,
                    ] : null,
                    'total_quantity' => $totalQuantity,
                    'total_value' => round($totalValue, 2),
                    'weighted_average_cost' => $totalQuantity > 0
                        ? round($totalValue / $totalQuantity, 4)
                        : 0,
                    'layer_count' => $group->count(),
                ];
            })->values();

            $summary = [
                'total_quantity' => $totalQuantity,
                'total_value' => round($totalValue, 2),
                'weighted_average_cost' => $totalQuantity > 0
                    ? round($totalValue / $totalQuantity, 4)
                    : 0,
                'total_count' => $totalCount,
            ];

            return [
                'summary' => $summary,
                'by_product' => $byProduct,
                'pagination' => [
                    'limit' => (int) $limit,
                    'offset' => (int) $offset,
                    'total' => $totalCount,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Weighted average cost report retrieved successfully',
        ], 200);
    }

    /**
     * Get inventory cash flow report over time.
     *
     * This report tracks money flowing in/out of the inventory account
     * (purchases, COGS, adjustments, transfers) — NOT actual inventory
     * value changes.
     */
    public function valueTrends(Request $request): JsonResponse
    {
        $tenantId = (int) $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $days = min((int) $request->query('days', 30), 365);

        $report = $this->cacheService->rememberReport('trends', $tenantId, [
            'days' => $days,
        ], function () use ($tenantId, $days) {
            $trends = StockMovement::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('
                    DATE(created_at) as date,
                    SUM(CASE WHEN type = "in" THEN total_cost ELSE 0 END) as value_in,
                    SUM(CASE WHEN type = "out" THEN total_cost ELSE 0 END) as value_out,
                    SUM(CASE WHEN type = "adjustment" THEN (CASE WHEN quantity_after > quantity_before THEN 1 ELSE -1 END) * total_cost ELSE 0 END) as value_adjustments,
                    SUM(CASE WHEN type LIKE "transfer%" THEN total_cost ELSE 0 END) as value_transfers
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->limit(100) // Cap daily entries for safety
                ->get();

            $currentValue = InventoryLayer::where('tenant_id', $tenantId)
                ->fifoLayers()
                ->withStock()
                ->sum('total_cost');

            return [
                'current_value' => round($currentValue, 2),
                'period_days' => $days,
                'trends' => $trends->map(fn($item) => [
                    'date' => $item->date,
                    'value_in' => round($item->value_in, 2),
                    'value_out' => round($item->value_out, 2),
                    'value_adjustments' => round($item->value_adjustments, 2),
                    'value_transfers' => round($item->value_transfers, 2),
                    'net_change' => round($item->value_in - $item->value_out + $item->value_adjustments, 2),
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => array_merge(['report_type' => 'cash_flow'], $report),
            'message' => 'Inventory cash flow report retrieved successfully',
        ], 200);
    }

    /**
     * Reconcile inventory discrepancies.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $validated = $request->validate([
            'inventory_id' => [
                'required',
                'integer',
                Rule::exists('inventories', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $inventory = \App\Models\Inventory::where('tenant_id', $tenantId)
            ->findOrFail($validated['inventory_id']);

        $result = $this->fifoService->reconcileInventory($inventory);

        return response()->json([
            'success' => true,
            'data' => [
                'reconciled' => $result['reconciled'],
                'before' => $result['before'],
                'after' => $result['after'],
            ],
            'message' => $result['reconciled']
                ? 'Inventory reconciled successfully'
                : 'No discrepancies found',
        ], 200);
    }

    /**
     * Export valuation report to CSV.
     */
    public function exportValuation(Request $request)
    {
        $tenantId = $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 400);
        }

        $warehouseId = $request->query('warehouse_id');

        $valuation = $this->fifoService->getInventoryValuation($tenantId, $warehouseId);

        $filename = 'inventory-valuation-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($valuation) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Product ID',
                'Quantity',
                'Available',
                'Value',
                'Average Cost',
            ]);

            foreach ($valuation['by_product'] as $productId => $data) {
                fputcsv($file, [
                    $productId,
                    $data['quantity'],
                    $data['available'] ?? 0,
                    round($data['value'], 2),
                    round($data['average_cost'], 4),
                ]);
            }

            // Summary row
            fputcsv($file, []);
            fputcsv($file, [
                'TOTAL',
                $valuation['total_quantity'],
                $valuation['total_available'],
                round($valuation['total_value'], 2),
                '',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
