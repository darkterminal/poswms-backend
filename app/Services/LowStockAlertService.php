<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;

class LowStockAlertService
{
    /**
     * Check all products for low stock and return alerts.
     */
    public function checkLowStock(int $tenantId): array
    {
        $lowStockItems = Inventory::where('inventories.tenant_id', $tenantId)
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->whereNotNull('products.min_stock')
            ->whereColumn('inventories.available', '<=', 'products.min_stock')
            ->with(['product:id,name,sku,min_stock', 'warehouse:id,name', 'store:id,name'])
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

        return [
            'total_alerts' => count($lowStockItems),
            'critical' => collect($lowStockItems)->where('severity', 'critical')->count(),
            'warning' => collect($lowStockItems)->where('severity', 'warning')->count(),
            'items' => $lowStockItems,
        ];
    }

    /**
     * Check specific product for low stock.
     */
    public function isProductLowStock(int $productId): bool
    {
        $product = Product::with('inventories')->find($productId);

        if (! $product) {
            return false;
        }

        return $product->isLowStock();
    }

    /**
     * Get users who should receive stock alerts.
     */
    public function getAlertRecipients(int $tenantId, string $role = 'admin'): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('slug', $role);
            })
            ->pluck('email')
            ->toArray();
    }

    /**
     * Generate low stock report.
     */
    public function generateReport(int $tenantId, ?int $warehouseId = null, ?int $storeId = null): array
    {
        $query = Inventory::where('inventories.tenant_id', $tenantId)
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->with(['product:id,name,sku,min_stock', 'warehouse:id,name', 'store:id,name']);

        if ($warehouseId) {
            $query->where('inventories.warehouse_id', $warehouseId);
        }

        if ($storeId) {
            $query->where('inventories.store_id', $storeId);
        }

        $inventories = $query->get();

        $totalProducts = $inventories->count();
        $lowStockCount = $inventories->filter(fn($i) => $i->product && $i->product->min_stock !== null && $i->available <= $i->product->min_stock)->count();
        $outOfStockCount = $inventories->filter(fn($i) => $i->available === 0)->count();

        return [
            'summary' => [
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'health_percentage' => $totalProducts > 0
                    ? round((($totalProducts - $lowStockCount) / $totalProducts) * 100, 2)
                    : 100,
            ],
            'low_stock_items' => $inventories
                ->filter(fn($i) => $i->product && $i->product->min_stock !== null && $i->available <= $i->product->min_stock)
                ->map(fn($i) => [
                    'product' => $i->product->name,
                    'sku' => $i->product->sku,
                    'location' => $this->getLocationName($i),
                    'available' => $i->available,
                    'min_stock' => $i->product->min_stock,
                ])
                ->values()
                ->toArray(),
            'out_of_stock_items' => $inventories
                ->filter(fn($i) => $i->available === 0)
                ->map(fn($i) => [
                    'product' => $i->product->name,
                    'sku' => $i->product->sku,
                    'location' => $this->getLocationName($i),
                ])
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get location name from inventory.
     */
    private function getLocationName(Inventory $inventory): string
    {
        if ($inventory->warehouse) {
            return "Warehouse: {$inventory->warehouse->name}";
        }

        if ($inventory->store) {
            return "Store: {$inventory->store->name}";
        }

        return 'Unknown';
    }

    /**
     * Determine alert severity.
     */
    private function getSeverity(int $available, int $minStock): string
    {
        if ($available === 0) {
            return 'critical';
        }

        if ($available <= ($minStock * 0.25)) {
            return 'critical';
        }

        if ($available <= ($minStock * 0.5)) {
            return 'warning';
        }

        return 'info';
    }
}
