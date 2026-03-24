<?php

namespace App\Models;

use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceLevel extends Model
{
    use HasFactory, ScopedByTenant;

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
