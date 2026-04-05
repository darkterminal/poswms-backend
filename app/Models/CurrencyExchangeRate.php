<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'base_currency',
        'target_currency',
        'rate',
        'source',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the exchange rate from one currency to another.
     * Returns null if no rate is found.
     *
     * @param  int|null  $tenantId  Null for global rates
     */
    public static function getRate(string $from, string $to, ?int $tenantId = null): ?float
    {
        $query = self::where('base_currency', $from)
            ->where('target_currency', $to)
            ->orderBy('effective_at', 'desc');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $rate = $query->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Create or update an exchange rate.
     */
    public static function updateRate(
        string $from,
        string $to,
        float $rate,
        string $source = 'manual',
        ?int $tenantId = null
    ): self {
        return self::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'base_currency' => $from,
                'target_currency' => $to,
            ],
            [
                'rate' => $rate,
                'source' => $source,
                'effective_at' => now(),
            ]
        );
    }

    /**
     * Get all rates for a tenant (or global if no tenant).
     */
    public static function getRatesForTenant(?int $tenantId = null): array
    {
        $query = self::orderBy('base_currency')->orderBy('target_currency');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->get()->map(function ($rate) {
            return [
                'id' => $rate->id,
                'base_currency' => $rate->base_currency,
                'target_currency' => $rate->target_currency,
                'rate' => (float) $rate->rate,
                'source' => $rate->source,
                'effective_at' => $rate->effective_at?->toIso8601String(),
                'tenant_id' => $rate->tenant_id,
            ];
        })->toArray();
    }

    /**
     * Check if this is a global rate (not tenant-scoped).
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }
}
