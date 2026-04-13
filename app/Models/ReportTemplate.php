<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by',
        'updated_by',
        'name',
        'description',
        'type',
        'config',
        'is_global',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns the report template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who created the template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the template.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the saved reports using this template.
     */
    public function savedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SavedReport::class);
    }

    /**
     * Get the scheduled reports using this template.
     */
    public function scheduledReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include global templates or tenant-specific templates.
     */
    public function scopeForTenant($query, ?int $tenantId = null)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('is_global', true)
                ->orWhere('tenant_id', $tenantId);
        });
    }
}
