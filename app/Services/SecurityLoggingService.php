<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Service for logging security-related events.
 *
 * This service provides a centralized way to log security events
 * with consistent formatting and severity levels.
 */
class SecurityLoggingService
{
    /**
     * Log a security event.
     *
     * @param  string  $eventType  Type of security event (e.g., 'auth.login_failed')
     * @param  string  $description  Human-readable description
     * @param  int|null  $tenantId  Tenant ID if applicable
     * @param  int|null  $userId  User ID if applicable
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string|null  $ipAddress  IP address (auto-detected if null)
     * @return AuditLog The created audit log entry
     */
    public function logEvent(
        string $eventType,
        string $description,
        ?int $tenantId = null,
        ?int $userId = null,
        array $context = [],
        ?string $ipAddress = null
    ): AuditLog {
        // Auto-detect IP and user agent if not provided
        if ($ipAddress === null && request()) {
            $ipAddress = request()->ip();
        }

        $userAgent = request()?->userAgent();

        // Create audit log entry
        $auditLog = AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'properties' => $context,
        ]);

        // Also log to Laravel logger for real-time monitoring
        $logLevel = $this->getLogLevel($eventType);
        Log::log($logLevel, "Security Event: {$description}", [
            'event_type' => $eventType,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'context' => $context,
        ]);

        return $auditLog;
    }

    /**
     * Log authentication failure.
     */
    public function logAuthFailure(
        string $reason,
        ?string $email = null,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'auth.' . $reason,
            'Authentication failure: ' . $reason,
            $tenantId,
            $userId,
            array_merge(['email' => $email], $context),
        );
    }

    /**
     * Log authorization failure.
     */
    public function logAuthorizationFailure(
        string $permission,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'authorization.denied',
            'Authorization denied for permission: ' . $permission,
            $tenantId,
            $userId,
            array_merge(['permission' => $permission], $context),
        );
    }

    /**
     * Log sensitive data access.
     */
    public function logSensitiveDataAccess(
        string $dataType,
        int $recordId,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'data.sensitive_access',
            "Sensitive data accessed: {$dataType} #{$recordId}",
            $tenantId,
            $userId,
            array_merge([
                'data_type' => $dataType,
                'record_id' => $recordId,
            ], $context),
        );
    }

    /**
     * Log configuration/security setting changes.
     */
    public function logSecuritySettingChange(
        string $settingName,
        mixed $oldValue,
        mixed $newValue,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'security.setting_changed',
            "Security setting changed: {$settingName}",
            $tenantId,
            $userId,
            array_merge([
                'setting_name' => $settingName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ], $context),
        );
    }

    /**
     * Log role/permission changes.
     */
    public function logRolePermissionChange(
        string $action,
        int $targetUserId,
        string $roleOrPermission,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'rbac.' . $action,
            "RBAC change: {$action} {$roleOrPermission} for user #{$targetUserId}",
            $tenantId,
            $userId,
            array_merge([
                'action' => $action,
                'target_user_id' => $targetUserId,
                'role_or_permission' => $roleOrPermission,
            ], $context),
        );
    }

    /**
     * Log webhook security events.
     */
    public function logWebhookSecurityEvent(
        string $eventType,
        int $webhookId,
        string $details,
        ?int $tenantId = null,
        ?int $userId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'webhook.' . $eventType,
            "Webhook security event: {$details}",
            $tenantId,
            $userId,
            array_merge([
                'webhook_id' => $webhookId,
            ], $context),
        );
    }

    /**
     * Log suspicious activity.
     */
    public function logSuspiciousActivity(
        string $activityType,
        string $description,
        ?int $userId = null,
        ?int $tenantId = null,
        array $context = []
    ): AuditLog {
        return $this->logEvent(
            'security.suspicious',
            "Suspicious activity: {$description}",
            $tenantId,
            $userId,
            array_merge(['activity_type' => $activityType], $context),
        );
    }

    /**
     * Get the appropriate log level for an event type.
     */
    private function getLogLevel(string $eventType): string
    {
        $warningEvents = ['auth.', 'authorization.', 'security.suspicious', 'webhook.'];
        $infoEvents = ['data.', 'rbac.', 'security.setting_changed'];

        foreach ($warningEvents as $prefix) {
            if (str_starts_with($eventType, $prefix)) {
                return 'warning';
            }
        }

        foreach ($infoEvents as $prefix) {
            if (str_starts_with($eventType, $prefix)) {
                return 'info';
            }
        }

        return 'notice';
    }
}
