<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReportExecution extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'scheduled_report_id',
        'tenant_id',
        'executed_at',
        'success',
        'records_count',
        'file_path',
        'file_format',
        'file_size',
        'error_message',
        'recipients_notified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'success' => 'boolean',
            'records_count' => 'integer',
            'file_size' => 'integer',
            'recipients_notified' => 'array',
        ];
    }

    /**
     * Get the scheduled report that owns this execution.
     */
    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }

    /**
     * Get the tenant that owns this execution.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to only include successful executions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope a query to only include failed executions.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope a query to only include executions for a specific scheduled report.
     */
    public function scopeForScheduledReport($query, int $scheduledReportId)
    {
        return $query->where('scheduled_report_id', $scheduledReportId);
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
