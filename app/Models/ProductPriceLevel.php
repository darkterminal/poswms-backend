<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ProductPriceLevel extends Model
{
    use HasFactory, HasMoney, ScopedByTenant;

    protected function moneyFields(): array
    {
        return ['price', 'cost'];
    }

    /**
     * The attributes that are mass assignable.
     * Security: Explicitly defined to prevent mass assignment vulnerabilities (OWASP A04).
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'level_name',
        'level_order',
        'unit_size',
        'price',
        'cost',
        'barcode',
        'active',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'unit_size' => 'integer',
            'level_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Boot the model - add security validations.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate price level data before saving (OWASP A04)
        static::saving(function (self $priceLevel) {
            // Ensure price and cost are non-negative
            if ($priceLevel->price < 0) {
                Log::warning('Negative price detected in price level', [
                    'price_level_id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'tenant_id' => $priceLevel->tenant_id,
                ]);
                $priceLevel->price = 0;
            }

            if ($priceLevel->cost < 0) {
                Log::warning('Negative cost detected in price level', [
                    'price_level_id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'tenant_id' => $priceLevel->tenant_id,
                ]);
                $priceLevel->cost = 0;
            }

            // Validate unit_size is positive
            if ($priceLevel->unit_size <= 0) {
                Log::warning('Invalid unit_size in price level', [
                    'price_level_id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'tenant_id' => $priceLevel->tenant_id,
                ]);
                $priceLevel->unit_size = 1;
            }

            // Sanitize level_name to prevent XSS (OWASP A03)
            if ($priceLevel->level_name) {
                $priceLevel->level_name = strip_tags(trim($priceLevel->level_name));
            }

            // Sanitize barcode (OWASP A03)
            if ($priceLevel->barcode) {
                $priceLevel->barcode = strip_tags(trim($priceLevel->barcode));
            }
        });

        // Log price level changes for audit (OWASP A09)
        static::updated(function (self $priceLevel) {
            $changes = $priceLevel->getChanges();
            if (! empty($changes)) {
                Log::info('Price level updated', [
                    'price_level_id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'tenant_id' => $priceLevel->tenant_id,
                    'changes' => $changes,
                ]);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isBaseUnit(): bool
    {
        return $this->level_order === 1 || $this->unit_size === 1;
    }

    public function getPricePerBaseUnit(): float
    {
        return $this->unit_size > 0 ? (float) $this->price / $this->unit_size : 0;
    }
}
