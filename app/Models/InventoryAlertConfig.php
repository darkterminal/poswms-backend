<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAlertConfig extends Model
{
    use HasFactory, ScopedByTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'store_id',
        'min_threshold',
        'max_threshold',
        'alert_enabled',
        'email_recipients',
    ];

    protected function casts(): array
    {
        return [
            'min_threshold' => 'integer',
            'max_threshold' => 'integer',
            'alert_enabled' => 'boolean',
            'email_recipients' => 'array',
        ];
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get enabled alerts only.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('alert_enabled', true);
    }

    /**
     * Scope to filter by product.
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by store.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get alert config for a specific product and location.
     */
    public static function getConfigForProduct(
        int $tenantId,
        int $productId,
        ?int $warehouseId = null,
        ?int $storeId = null
    ): ?self {
        return self::forTenant($tenantId)
            ->where('product_id', $productId)
            ->where(function ($query) use ($warehouseId, $storeId) {
                if ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                } else {
                    $query->whereNull('warehouse_id');
                }

                if ($storeId) {
                    $query->where('store_id', $storeId);
                } else {
                    $query->whereNull('store_id');
                }
            })
            ->first();
    }

    /**
     * Get all enabled configs for tenant with product and location details.
     */
    public static function getWithDetails(int $tenantId): \Illuminate\Support\Collection
    {
        return self::forTenant($tenantId)
            ->enabled()
            ->with([
                'product:id,name,sku',
                'warehouse:id,name,code',
                'store:id,name,code',
            ])
            ->get();
    }

    /**
     * Check if current inventory level triggers this alert.
     */
    public function isTriggered(Inventory $inventory): bool
    {
        if (!$this->alert_enabled) {
            return false;
        }

        return $inventory->available <= $this->min_threshold;
    }

    /**
     * Get formatted email recipients list.
     */
    public function getRecipientsList(): array
    {
        return $this->email_recipients ?? [];
    }

    /**
     * Add email recipient to the config.
     */
    public function addRecipient(string $email): void
    {
        $recipients = $this->getRecipientsList();

        if (!in_array($email, $recipients)) {
            $recipients[] = $email;
            $this->email_recipients = $recipients;
            $this->save();
        }
    }

    /**
     * Remove email recipient from the config.
     */
    public function removeRecipient(string $email): void
    {
        $recipients = $this->getRecipientsList();
        $this->email_recipients = array_values(array_diff($recipients, [$email]));
        $this->save();
    }
}
