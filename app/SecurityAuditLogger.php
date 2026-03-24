<?php

namespace App;

use App\Jobs\LogSecurityEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Centralized security audit logging service.
 *
 * Provides async logging for high-risk security events:
 * - Authentication failures
 * - Permission denials
 * - Webhook configuration changes
 * - User/role modifications
 * - Suspicious activity
 *
 * Supports both synchronous and asynchronous (queued) logging
 * to avoid performance degradation during critical operations.
 */
class SecurityAuditLogger
{
    /**
     * High-risk event types that should always be logged.
     */
    public const HIGH_RISK_EVENTS = [
        // Authentication events
        'auth.login_failed',
        'auth.login_locked',
        'auth.logout',
        'auth.token_revoked',
        'auth.impersonation_started',
        'auth.impersonation_ended',

        // Authorization events
        'authorization.denied',
        'permission.assigned',
        'permission.revoked',
        'role.assigned',
        'role.revoked',
        'role.created',
        'role.updated',
        'role.deleted',

        // User management events
        'user.created',
        'user.updated',
        'user.deleted',
        'user.deactivated',
        'user.activated',

        // Webhook events
        'webhook.created',
        'webhook.updated',
        'webhook.deleted',
        'webhook.ssrf_blocked',
        'webhook.test_blocked',

        // Security events
        'security.ssrf_detected',
        'security.rate_limit_exceeded',
        'security.suspicious_activity',
        'security.data_export',
    ];

    /**
     * Log a security event synchronously.
     *
     * @param  string  $eventType  The type of security event
     * @param  string  $description  Human-readable description
     * @param  array<string, mixed>  $context  Additional context data
     * @param  int|null  $tenantId  Tenant ID (auto-detected if null)
     * @param  int|null  $userId  User ID (auto-detected if null)
     * @param  Request|null  $request  HTTP request (auto-detected if null)
     */
    public function log(
        string $eventType,
        string $description,
        array $context = [],
        ?int $tenantId = null,
        ?int $userId = null,
        ?Request $request = null
    ): AuditLog {
        // Auto-detect values if not provided
        $tenantId = $tenantId ?? $this->resolveTenantId();
        $userId = $userId ?? $this->resolveUserId();
        $request = $request ?? request();

        // Build audit log entry
        $auditLog = AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'description' => $description,
            'auditable_type' => $context['auditable_type'] ?? null,
            'auditable_id' => $context['auditable_id'] ?? null,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge($context, [
                'logged_at' => now()->toIso8601String(),
                'is_high_risk' => $this->isHighRiskEvent($eventType),
            ]),
            'old_values' => $context['old_values'] ?? [],
            'new_values' => $context['new_values'] ?? [],
        ]);

        // Log to application log for critical events
        if ($this->isHighRiskEvent($eventType)) {
            $this->logToChannel($eventType, $description, $context, $tenantId, $userId);
        }

        return $auditLog;
    }

    /**
     * Log a security event asynchronously via queue.
     *
     * This method is preferred for high-volume events to avoid
     * performance degradation during critical operations.
     *
     * @param  string  $eventType  The type of security event
     * @param  string  $description  Human-readable description
     * @param  array<string, mixed>  $context  Additional context data
     * @param  int|null  $tenantId  Tenant ID (auto-detected if null)
     * @param  int|null  $userId  User ID (auto-detected if null)
     * @param  Request|null  $request  HTTP request (auto-detected if null)
     */
    public function logAsync(
        string $eventType,
        string $description,
        array $context = [],
        ?int $tenantId = null,
        ?int $userId = null,
        ?Request $request = null
    ): void {
        // Auto-detect values if not provided
        $tenantId = $tenantId ?? $this->resolveTenantId();
        $userId = $userId ?? $this->resolveUserId();
        $request = $request ?? request();

        // Dispatch to queue
        LogSecurityEvent::dispatch(
            eventType: $eventType,
            description: $description,
            context: $context,
            tenantId: $tenantId,
            userId: $userId,
            ipAddress: $request?->ip(),
            userAgent: $request?->userAgent(),
            url: $request?->fullUrl()
        )->onQueue('security-audit');
    }

    /**
     * Log an authentication failure.
     *
     * @param  string  $email  Email that failed to authenticate
     * @param  int|null  $userId  User ID if found
     * @param  array<string, mixed>  $context  Additional context
     */
    public function logAuthFailure(
        string $email,
        ?int $userId = null,
        array $context = []
    ): AuditLog {
        return $this->log(
            eventType: 'auth.login_failed',
            description: 'Failed login attempt',
            context: array_merge($context, [
                'email' => $email,
                'user_found' => $userId !== null,
            ]),
            tenantId: $context['tenant_id'] ?? null,
            userId: $userId,
        );
    }

    /**
     * Log an account lockout.
     *
     * @param  string  $email  Email that was locked out
     * @param  int  $attemptCount  Number of failed attempts
     * @param  int  $lockoutDuration  Lockout duration in seconds
     */
    public function logAccountLockout(
        string $email,
        int $attemptCount,
        int $lockoutDuration
    ): AuditLog {
        return $this->log(
            eventType: 'auth.login_locked',
            description: 'Account locked after ' . $attemptCount . ' failed attempts',
            context: [
                'email' => $email,
                'attempt_count' => $attemptCount,
                'lockout_duration' => $lockoutDuration,
                'lockout_until' => now()->addSeconds($lockoutDuration)->toIso8601String(),
            ],
        );
    }

    /**
     * Log a permission denial.
     *
     * @param  User  $user  User who was denied
     * @param  string  $permission  Permission that was denied
     * @param  string  $resource  Resource being accessed
     */
    public function logPermissionDenied(
        User $user,
        string $permission,
        string $resource
    ): AuditLog {
        return $this->log(
            eventType: 'authorization.denied',
            description: "Permission denied: {$permission} on {$resource}",
            context: [
                'permission' => $permission,
                'resource' => $resource,
                'user_email' => $user->email,
            ],
            tenantId: $user->tenant_id,
            userId: $user->id,
        );
    }

    /**
     * Log a role change.
     *
     * @param  User  $user  User whose role changed
     * @param  string  $action  Action performed (assigned, revoked)
     * @param  array<string, mixed>  $roleData  Role information
     */
    public function logRoleChange(
        User $user,
        string $action,
        array $roleData
    ): AuditLog {
        $roleName = $roleData['name'] ?? $roleData['role_name'] ?? 'Unknown';

        return $this->log(
            eventType: "role.{$action}",
            description: "Role {$action}: {$roleName}",
            context: $roleData,
            tenantId: $user->tenant_id,
            userId: $user->id,
        );
    }

    /**
     * Log a webhook configuration change.
     *
     * @param  string  $action  Action performed (created, updated, deleted)
     * @param  int  $webhookId  Webhook ID
     * @param  array<string, mixed>  $webhookData  Webhook data
     * @param  int  $tenantId  Tenant ID
     * @param  int  $userId  User ID
     */
    public function logWebhookChange(
        string $action,
        int $webhookId,
        array $webhookData,
        int $tenantId,
        int $userId
    ): AuditLog {
        $webhookName = $webhookData['name'] ?? 'ID ' . $webhookId;

        return $this->log(
            eventType: "webhook.{$action}",
            description: "Webhook {$action}: {$webhookName}",
            context: array_merge($webhookData, [
                'webhook_id' => $webhookId,
            ]),
            tenantId: $tenantId,
            userId: $userId,
        );
    }

    /**
     * Log a user modification.
     *
     * @param  string  $action  Action performed (created, updated, deleted)
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $userData  User data
     * @param  int  $tenantId  Tenant ID
     * @param  int|null  $actorId  ID of user who performed the action
     */
    public function logUserChange(
        string $action,
        int $userId,
        array $userData,
        int $tenantId,
        ?int $actorId = null
    ): AuditLog {
        $userEmail = $userData['email'] ?? 'ID ' . $userId;

        return $this->log(
            eventType: "user.{$action}",
            description: "User {$action}: {$userEmail}",
            context: array_merge($userData, [
                'target_user_id' => $userId,
            ]),
            tenantId: $tenantId,
            userId: $actorId,
        );
    }

    /**
     * Log suspicious activity.
     *
     * @param  string  $reason  Reason for suspicion
     * @param  array<string, mixed>  $context  Context data
     * @param  int|null  $userId  User ID if known
     */
    public function logSuspiciousActivity(
        string $reason,
        array $context = [],
        ?int $userId = null
    ): AuditLog {
        return $this->log(
            eventType: 'security.suspicious_activity',
            description: "Suspicious activity detected: {$reason}",
            context: array_merge($context, [
                'reason' => $reason,
            ]),
            userId: $userId,
        );
    }

    /**
     * Log SSRF attempt.
     *
     * @param  string  $url  URL that was blocked
     * @param  string  $reason  Reason for blocking
     * @param  int  $tenantId  Tenant ID
     * @param  int  $userId  User ID
     */
    public function logSsrfAttempt(
        string $url,
        string $reason,
        int $tenantId,
        int $userId
    ): AuditLog {
        return $this->log(
            eventType: 'security.ssrf_detected',
            description: 'SSRF attempt blocked',
            context: [
                'url' => $url,
                'reason' => $reason,
                'risk_level' => 'high',
            ],
            tenantId: $tenantId,
            userId: $userId,
        );
    }

    /**
     * Check if an event is considered high-risk.
     *
     * @param  string  $eventType  Event type to check
     */
    public function isHighRiskEvent(string $eventType): bool
    {
        return in_array($eventType, self::HIGH_RISK_EVENTS, true);
    }

    /**
     * Get events for a specific risk category.
     *
     * @param  string  $category  Category prefix (e.g., 'auth', 'webhook')
     * @return array<string> Array of event types
     */
    public function getEventsByCategory(string $category): array
    {
        return array_filter(
            self::HIGH_RISK_EVENTS,
            fn($event) => str_starts_with($event, $category . '.')
        );
    }

    /**
     * Resolve the current tenant ID.
     */
    private function resolveTenantId(): ?int
    {
        $user = Auth::user();

        return $user?->tenant_id;
    }

    /**
     * Resolve the current user ID.
     */
    private function resolveUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * Log to application log channel for critical events.
     *
     * @param  string  $eventType  Event type
     * @param  string  $description  Description
     * @param  array<string, mixed>  $context  Context data
     * @param  int|null  $tenantId  Tenant ID
     * @param  int|null  $userId  User ID
     */
    private function logToChannel(
        string $eventType,
        string $description,
        array $context,
        ?int $tenantId,
        ?int $userId
    ): void {
        $logLevel = $this->getLogLevelForEvent($eventType);

        Log::channel('security')->log($logLevel, $description, [
            'event_type' => $eventType,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the appropriate log level for an event.
     *
     * @param  string  $eventType  Event type
     * @return string Log level (warning, error, critical)
     */
    private function getLogLevelForEvent(string $eventType): string
    {
        return match (true) {
            str_contains($eventType, 'ssrf'),
            str_contains($eventType, 'locked') => 'error',
            str_contains($eventType, 'denied'),
            str_contains($eventType, 'failed'),
            str_contains($eventType, 'suspicious') => 'warning',
            default => 'info',
        };
    }
}
