<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavedReport extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'template_id',
        'created_by',
        'name',
        'description',
        'type',
        'filters',
        'data',
        'file_path',
        'file_format',
        'file_size',
        'generated_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'data' => 'array',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns the saved report.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the template used for this report.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Get the user who created the report.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include reports for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include reports that haven't expired.
     */
    public function scopeNotExpired($query)
    {
        return $query->whereNull('expires_at')
            ->orWhere('expires_at', '>', now());
    }

    /**
     * Scope a query to only include reports by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the report has an exported file.
     */
    public function hasFile(): bool
    {
        return ! empty($this->file_path) && file_exists(storage_path('app/' . $this->file_path));
    }

    /**
     * Get the file URL for download.
     */
    public function getFileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        try {
            return route('api.saved-reports.download', $this->id);
        } catch (\Exception $e) {
            // Route might not be defined yet during tests
            return null;
        }
    }

    /**
     * Check if the report is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the file size in human readable format.
     */
    public function getFormattedFileSize(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
