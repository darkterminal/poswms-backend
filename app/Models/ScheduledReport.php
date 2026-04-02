<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledReport extends Model
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
        'updated_by',
        'name',
        'description',
        'type',
        'filters',
        'schedule_frequency',
        'schedule_day',
        'schedule_time',
        'recipients',
        'export_format',
        'is_active',
        'last_run_at',
        'next_run_at',
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
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'schedule_time' => 'datetime:H:i',
        ];
    }

    /**
     * Get the tenant that owns the scheduled report.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the template used for this scheduled report.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Get the user who created the scheduled report.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the scheduled report.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the execution history for this scheduled report.
     */
    public function executionHistory(): HasMany
    {
        return $this->hasMany(ScheduledReportExecution::class);
    }

    /**
     * Scope a query to only include active scheduled reports.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include reports due for execution.
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->whereNull('last_run_at')
            ->orWhere('next_run_at', '<=', now());
    }

    /**
     * Scope a query to only include reports for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Calculate the next run time based on schedule configuration.
     */
    public function calculateNextRun(): \Carbon\CarbonInterface
    {
        $baseTime = $this->last_run_at ?? now();
        $timeParts = explode(':', $this->schedule_time);
        $hour = (int) $timeParts[0];
        $minute = (int) $timeParts[1];

        return match ($this->schedule_frequency) {
            'daily' => $baseTime->copy()->addDay()->setTime($hour, $minute),
            'weekly' => $baseTime->copy()->addWeek()->next((int) $this->schedule_day)->setTime($hour, $minute),
            'monthly' => $baseTime->copy()->addMonth()->day((int) $this->schedule_day)->setTime($hour, $minute),
            default => $baseTime->copy()->addDay()->setTime($hour, $minute),
        };
    }

    /**
     * Update the next run time.
     */
    public function updateNextRun(): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun(),
        ]);
    }

    /**
     * Check if the report is due for execution.
     */
    public function isDue(): bool
    {
        return $this->is_active && 
            ($this->next_run_at === null || $this->next_run_at->isPast());
    }

    /**
     * Get the schedule description in human readable format.
     */
    public function getScheduleDescription(): string
    {
        $time = \Carbon\Carbon::parse($this->schedule_time)->format('g:i A');

        return match ($this->schedule_frequency) {
            'daily' => "Daily at {$time}",
            'weekly' => "Weekly on day {$this->schedule_day} at {$time}",
            'monthly' => "Monthly on day {$this->schedule_day} at {$time}",
            default => "Unknown schedule",
        };
    }

    /**
     * Get the recipients as a comma-separated string.
     */
    public function getRecipientsList(): string
    {
        return implode(', ', $this->recipients ?? []);
    }
}
