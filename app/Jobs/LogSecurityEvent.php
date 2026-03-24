<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for asynchronously logging security events.
 *
 * This job ensures that security audit logging does not impact
 * the performance of critical operations like authentication,
 * authorization checks, or webhook deliveries.
 */
class LogSecurityEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param  string  $eventType  The type of security event
     * @param  string  $description  Human-readable description
     * @param  array<string, mixed>  $context  Additional context data
     * @param  int|null  $tenantId  Tenant ID
     * @param  int|null  $userId  User ID
     * @param  string|null  $ipAddress  IP address
     * @param  string|null  $userAgent  User agent
     * @param  string|null  $url  URL accessed
     */
    public function __construct(
        public string $eventType,
        public string $description,
        public array $context = [],
        public ?int $tenantId = null,
        public ?int $userId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $url = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if event type is empty
        if (empty($this->eventType)) {
            return;
        }

        // Create audit log entry
        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'event_type' => $this->eventType,
            'description' => $this->description,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'url' => $this->url,
            'metadata' => array_merge($this->context, [
                'logged_at' => now()->toIso8601String(),
                'logged_async' => true,
            ]),
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Even if async logging fails, we don't want to lose the security event.
     * Fall back to synchronous logging on failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure to error log
        \Log::error('Security audit logging failed, falling back to sync', [
            'event_type' => $this->eventType,
            'description' => $this->description,
            'error' => $exception->getMessage(),
        ]);

        // Attempt synchronous logging as fallback
        try {
            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'event_type' => $this->eventType,
                'description' => $this->description . ' [ASYNC FAILED]',
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'url' => $this->url,
                'metadata' => array_merge($this->context, [
                    'logged_at' => now()->toIso8601String(),
                    'logged_async_failed' => true,
                    'fallback_sync' => true,
                    'error' => $exception->getMessage(),
                ]),
            ]);
        } catch (\Throwable $fallbackException) {
            // If fallback also fails, log to error log
            \Log::critical('Security audit logging completely failed', [
                'event_type' => $this->eventType,
                'original_error' => $exception->getMessage(),
                'fallback_error' => $fallbackException->getMessage(),
            ]);
        }
    }
}
