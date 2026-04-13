<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'company_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'currency',
        'status',
        'subscription_plan',
        'settings',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'email' => 'encrypted',
            'phone' => 'encrypted',
        ];
    }

    /**
     * Get the users belonging to this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the stores belonging to this tenant.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get the warehouses belonging to this tenant.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Get the pricing tiers belonging to this tenant.
     */
    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }

    /**
     * Get the categories belonging to this tenant.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the products belonging to this tenant.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the customers belonging to this tenant.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the inventories belonging to this tenant.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Get the orders belonging to this tenant.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the report templates belonging to this tenant.
     */
    public function reportTemplates(): HasMany
    {
        return $this->hasMany(ReportTemplate::class);
    }

    /**
     * Get the saved reports belonging to this tenant.
     */
    public function savedReports(): HasMany
    {
        return $this->hasMany(SavedReport::class);
    }

    /**
     * Get the scheduled reports belonging to this tenant.
     */
    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    /**
     * Get the API keys belonging to this tenant.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant has active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        if (! $this->subscription_ends_at) {
            return false;
        }

        return $this->subscription_ends_at->isFuture();
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        if (! $this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    /**
     * Resolve the effective currency for this tenant.
     * Falls back to system default currency if tenant currency is not set.
     */
    public function resolveCurrency(): string
    {
        $currency = trim($this->currency ?? '');

        if ($currency !== '') {
            return $currency;
        }

        return Setting::get('application.default_currency', 'USD');
    }

    /**
     * Get exchange rates scoped to this tenant.
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class);
    }

    /**
     * Get currencies configured for this tenant.
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }
}
