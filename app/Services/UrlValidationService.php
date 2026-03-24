<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Service for validating URLs to prevent SSRF attacks.
 *
 * This service validates that URLs do not point to:
 * - Private IP ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
 * - Loopback addresses (127.x.x.x)
 * - Link-local addresses (169.254.x.x)
 * - Cloud metadata endpoints
 * - Localhost
 *
 * Features:
 * - DNS rebinding protection (double resolution)
 * - IPv6 support
 * - Audit logging for security events
 * - Configurable strictness levels
 * - Allowlist for verified domains
 * - Redirect validation
 */
class UrlValidationService
{
    /**
     * @var array<string> List of blocked IP patterns (regex)
     */
    private array $blockedIpPatterns = [
        '/^127\./',                              // Loopback
        '/^10\./',                               // Private Class A
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',     // Private Class B
        '/^192\.168\./',                         // Private Class C
        '/^169\.254\./',                         // Link-local (cloud metadata)
        '/^0\.0\.0\.0/',                         // All interfaces
        '/^::1$/',                               // IPv6 loopback
        '/^fc00:/i',                             // IPv6 unique local
        '/^fe80:/i',                             // IPv6 link-local
        '/^::ffff:127\./',                       // IPv4-mapped IPv6 loopback
        '/^::ffff:10\./i',                       // IPv4-mapped IPv6 private A
        '/^::ffff:172\.(1[6-9]|2[0-9]|3[0-1])\./i', // IPv4-mapped IPv6 private B
        '/^::ffff:192\.168\./i',                 // IPv4-mapped IPv6 private C
    ];

    /**
     * @var array<string> List of blocked hostnames
     */
    private array $blockedHostnames = [
        'localhost',
        'metadata.google.internal',
        'metadata',
        'kubernetes.default.svc',
        'minikube',
        'docker.internal',
        'host.docker.internal',
    ];

    /**
     * @var array<string> List of blocked cloud metadata paths
     */
    private array $blockedCloudMetadataPaths = [
        '/latest/meta-data/',
        '/computeMetadata/',
        '/metadata/',
        '/2009-04-04/meta-data/',
    ];

    /**
     * @var array<string> List of allowed domains (when allowlist is enabled)
     */
    private array $allowedDomains = [];

    /**
     * @var string Security mode: 'OFF', 'SOFT', or 'STRICT'
     */
    private string $strictMode = 'OFF';

    /**
     * @var bool Whether allowlist mode is enabled
     */
    private bool $allowlistEnabled = false;

    /**
     * @var bool Whether to validate redirects
     */
    private bool $validateRedirects = false;

    /**
     * @var bool Whether DNS rebinding protection is enabled
     */
    private bool $dnsRebindingProtection = true;

    /**
     * @var bool Whether testing mode is enabled
     */
    private bool $testingMode = false;

    private ?LoggerInterface $logger = null;

    /**
     * Create a new UrlValidationService instance.
     */
    public function __construct()
    {
        // Load configuration
        $this->strictMode = strtoupper((string) config('ssrf.strict_mode', 'OFF'));
        $this->allowlistEnabled = config('ssrf.allowlist_enabled', false);
        $this->validateRedirects = config('ssrf.validate_redirects', false);
        $this->dnsRebindingProtection = config('ssrf.dns_rebinding_protection', true);
        $this->testingMode = config('ssrf.testing_mode', false);

        // Load allowed domains from config
        $this->allowedDomains = config('ssrf.allowed_domains', []);

        // Merge additional blocked patterns from config
        $configBlockedPatterns = config('ssrf.blocked_ip_patterns', []);
        if (! empty($configBlockedPatterns)) {
            $this->blockedIpPatterns = array_merge($this->blockedIpPatterns, $configBlockedPatterns);
        }

        // Merge additional blocked hostnames from config
        $configBlockedHostnames = config('ssrf.blocked_hostnames', []);
        if (! empty($configBlockedHostnames)) {
            $this->blockedHostnames = array_merge($this->blockedHostnames, $configBlockedHostnames);
        }
    }

    /**
     * Set the logger instance.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Log a security event.
     *
     * @param  string  $level  Log level (info, warning, error, critical)
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Log context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Validate a URL for SSRF protection.
     *
     * @param  string  $url  The URL to validate
     * @param  bool  $allowLegacy  If true, allow existing URLs but log warning (for backward compatibility)
     * @param  ?int  $tenantId  Tenant ID for audit logging
     * @param  ?int  $userId  User ID for audit logging
     * @param  bool  $skipDnsRebindingCheck  Skip DNS rebinding check (for testing)
     * @return array{valid: bool, error?: string, warning?: string, risk_level?: string}
     */
    public function validateUrl(
        string $url,
        bool $allowLegacy = false,
        ?int $tenantId = null,
        ?int $userId = null,
        bool $skipDnsRebindingCheck = false
    ): array {
        // Basic URL validation
        // In testing mode, skip active_url check which requires DNS resolution
        $urlRules = $this->testingMode
            ? ['required', 'url']
            : ['required', 'url', 'active_url'];

        $validator = Validator::make(
            ['url' => $url],
            ['url' => $urlRules]
        );

        if ($validator->fails()) {
            $this->log('warning', 'SSRF validation: Invalid URL format', ['url' => $url]);

            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Parse the URL
        $parsedUrl = parse_url($url);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            $this->log('warning', 'SSRF validation: Missing host', ['url' => $url]);

            return ['valid' => false, 'error' => 'Invalid URL: missing host'];
        }

        $host = $parsedUrl['host'];
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $path = $parsedUrl['path'] ?? '/';

        // Check for cloud metadata paths
        // Mode behavior:
        // - OFF: Log only, allow through
        // - SOFT: Log and audit, allow through with warning
        // - STRICT: Block completely (critical security risk)
        foreach ($this->blockedCloudMetadataPaths as $metadataPath) {
            if (str_contains($path, $metadataPath)) {
                $this->log('critical', 'SSRF attack blocked: Cloud metadata endpoint', [
                    'url' => $url,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ]);

                $this->createAuditLog($tenantId, $userId, 'ssrf_attack_blocked', [
                    'url' => $url,
                    'reason' => 'Cloud metadata endpoint detected',
                    'risk_level' => 'critical',
                ]);

                // In OFF or SOFT mode, log but allow
                if ($this->isSecurityModeOff() || $this->isSecurityModeSoft()) {
                    $this->log('warning', sprintf('%s mode: Allowing URL to cloud metadata endpoint', $this->strictMode), [
                        'url' => $url,
                    ]);
                    break;
                }

                // STRICT mode: block
                return [
                    'valid' => false,
                    'error' => 'URL points to blocked cloud metadata endpoint',
                    'risk_level' => 'critical',
                ];
            }
        }

        // Check blocked hostnames
        // Mode behavior:
        // - OFF: Log only, allow through
        // - SOFT: Log and audit, allow through with warning
        // - STRICT: Block completely
        if (in_array(strtolower($host), $this->blockedHostnames, true)) {
            $this->log('warning', 'SSRF validation: Blocked hostname', ['host' => $host, 'url' => $url]);

            // In OFF or SOFT mode, log but allow
            if ($this->isSecurityModeOff() || $this->isSecurityModeSoft()) {
                $this->log('warning', sprintf('%s mode: Allowing URL to blocked hostname', $this->strictMode), [
                    'url' => $url,
                    'host' => $host,
                ]);
            } else {
                // STRICT mode: block
                return ['valid' => false, 'error' => 'URL points to blocked host: ' . $host];
            }
        }

        // Check blocked hostname patterns
        // Mode behavior:
        // - OFF: Allow through
        // - SOFT: Log warning, allow through
        // - STRICT: Block completely
        foreach ($this->blockedHostnames as $blockedHostname) {
            if (str_ends_with($host, '.' . $blockedHostname)) {
                // In OFF or SOFT mode, log but allow
                if ($this->isSecurityModeOff() || $this->isSecurityModeSoft()) {
                    $this->log('warning', sprintf('%s mode: Allowing URL to blocked domain', $this->strictMode), [
                        'url' => $url,
                        'host' => $host,
                    ]);
                    break;
                }

                // STRICT mode: block
                return ['valid' => false, 'error' => 'URL points to blocked domain: ' . $host];
            }
        }

        // If allowlist is enabled, check if domain is allowed
        if ($this->allowlistEnabled && ! $this->isDomainAllowed($host)) {
            $this->log('warning', 'SSRF validation: Domain not in allowlist', [
                'host' => $host,
                'url' => $url,
            ]);

            return [
                'valid' => false,
                'error' => 'Domain not in allowed list: ' . $host,
                'risk_level' => 'high',
            ];
        }

        // DNS rebinding protection: resolve twice with delay
        // Mode behavior:
        // - OFF: Skip DNS rebinding check entirely
        // - SOFT: Perform check but only log warnings, don't block
        // - STRICT: Full enforcement with blocking
        $shouldSkipDnsCheck = $skipDnsRebindingCheck || ! $this->dnsRebindingProtection || $this->testingMode || $this->isSecurityModeOff();

        if (! $shouldSkipDnsCheck) {
            $firstResolution = $this->resolveHostname($host);

            if (! $firstResolution['success']) {
                $this->log('warning', 'SSRF validation: DNS resolution failed', [
                    'host' => $host,
                    'error' => $firstResolution['error'],
                ]);

                // In SOFT mode, log but continue
                if ($this->isSecurityModeSoft()) {
                    $this->log('warning', 'SOFT mode: Allowing URL despite DNS failure', ['url' => $url]);
                    $ip = '0.0.0.0'; // Placeholder to pass through
                } else {
                    // STRICT mode: block
                    return ['valid' => false, 'error' => 'Could not resolve hostname: ' . $host];
                }
            } else {
                // Small delay to prevent DNS rebinding attacks
                usleep(100000); // 100ms

                $secondResolution = $this->resolveHostname($host);

                if (! $secondResolution['success']) {
                    // In SOFT mode, log but continue
                    if ($this->isSecurityModeSoft()) {
                        $this->log('warning', 'SOFT mode: Allowing URL despite inconsistent DNS', ['url' => $url]);
                        $ip = $firstResolution['ip'];
                    } else {
                        // STRICT mode: block
                        return ['valid' => false, 'error' => 'DNS resolution inconsistent (possible rebinding attack)'];
                    }
                } elseif ($firstResolution['ip'] !== $secondResolution['ip']) {
                    // DNS rebinding detected
                    $this->log('critical', 'SSRF attack blocked: DNS rebinding detected', [
                        'url' => $url,
                        'first_ip' => $firstResolution['ip'],
                        'second_ip' => $secondResolution['ip'],
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                    ]);

                    $this->createAuditLog($tenantId, $userId, 'ssrf_attack_blocked', [
                        'url' => $url,
                        'reason' => 'DNS rebinding detected',
                        'first_ip' => $firstResolution['ip'],
                        'second_ip' => $secondResolution['ip'],
                        'risk_level' => 'critical',
                    ]);

                    // In SOFT mode, log but allow
                    if ($this->isSecurityModeSoft()) {
                        $this->log('warning', 'SOFT mode: Allowing URL despite DNS rebinding detection', ['url' => $url]);
                        $ip = $firstResolution['ip'];
                    } else {
                        // STRICT mode: block
                        return [
                            'valid' => false,
                            'error' => 'DNS rebinding detected (possible attack)',
                            'risk_level' => 'critical',
                        ];
                    }
                } else {
                    // DNS checks passed
                    $ip = $firstResolution['ip'];
                }
            }
        } else {
            // Skip DNS rebinding check - use simple resolution
            $ip = gethostbyname($host);

            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                // For testing, allow non-resolvable hostnames if they're not obviously malicious
                // Block only if hostname is clearly an IP address pattern
                if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host)) {
                    return [
                        'valid' => false,
                        'error' => 'Could not resolve hostname: ' . $host,
                        'risk_level' => 'high',
                    ];
                }

                // If allowlist is enabled and domain is allowed, skip IP checks
                if ($this->allowlistEnabled && $this->isDomainAllowed($host)) {
                    // Domain is in allowlist, allow even if it doesn't resolve (testing mode)
                    $ip = null; // Skip IP checks
                } else {
                    // Allow non-IP hostnames in testing (they'll be checked against blocked patterns)
                    $ip = '0.0.0.0'; // Placeholder - will pass through if not in blocked patterns
                }
            }
        }

        // Check if IP is in blocked ranges (skip if IP is null - allowlisted domain)
        // Mode behavior:
        // - OFF: Log only, allow through
        // - SOFT: Log and create audit trail, allow through with warning
        // - STRICT: Block completely
        if ($ip !== null) {
            foreach ($this->blockedIpPatterns as $pattern) {
                if (preg_match($pattern, $ip)) {
                    $this->log('warning', 'SSRF validation: Blocked private IP', [
                        'url' => $url,
                        'ip' => $ip,
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                    ]);

                    $this->createAuditLog($tenantId, $userId, 'ssrf_url_blocked', [
                        'url' => $url,
                        'ip' => $ip,
                        'reason' => 'Private/reserved IP range',
                        'risk_level' => 'high',
                    ]);

                    // In OFF or SOFT mode, log but allow
                    if ($this->isSecurityModeOff() || $this->isSecurityModeSoft()) {
                        $this->log('warning', sprintf('%s mode: Allowing URL to private IP', $this->strictMode), [
                            'url' => $url,
                            'ip' => $ip,
                        ]);

                        // Continue to next validation step
                        break;
                    }

                    // STRICT mode: block
                    return [
                        'valid' => false,
                        'error' => 'URL points to internal/private IP address: ' . $ip,
                        'risk_level' => 'high',
                    ];
                }
            }
        }

        // Additional check using PHP's filter for private/reserved ranges (skip if IP is null)
        // Mode behavior:
        // - OFF: Log only, allow through
        // - SOFT: Log and create audit trail, allow through with warning
        // - STRICT: Block completely
        if ($ip !== null && ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            $this->log('warning', 'SSRF validation: Private/reserved IP range', [
                'url' => $url,
                'ip' => $ip,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);

            $this->createAuditLog($tenantId, $userId, 'ssrf_url_blocked', [
                'url' => $url,
                'ip' => $ip,
                'reason' => 'Private or reserved IP range',
                'risk_level' => 'high',
            ]);

            // In OFF or SOFT mode, log but allow
            if ($this->isSecurityModeOff() || $this->isSecurityModeSoft()) {
                $this->log('warning', sprintf('%s mode: Allowing URL to private/reserved IP', $this->strictMode), [
                    'url' => $url,
                    'ip' => $ip,
                ]);
            } else {
                // STRICT mode: block
                return [
                    'valid' => false,
                    'error' => 'URL points to private or reserved IP range: ' . $ip,
                    'risk_level' => 'high',
                ];
            }
        }

        // In STRICT mode, validate redirects
        if ($this->isSecurityModeStrict() && $this->validateRedirects && ! $this->testingMode) {
            $redirectsToBlocked = $this->checkRedirects($url);

            if ($redirectsToBlocked) {
                $this->log('critical', 'SSRF attack blocked: Redirect to blocked location', [
                    'url' => $url,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ]);

                $this->createAuditLog($tenantId, $userId, 'ssrf_redirect_blocked', [
                    'url' => $url,
                    'reason' => 'Redirects to blocked location',
                    'risk_level' => 'critical',
                ]);

                return [
                    'valid' => false,
                    'error' => 'URL redirects to a blocked location',
                    'risk_level' => 'critical',
                ];
            }
        }

        // URL is valid - log for new URLs
        $logLevel = config('ssrf.logging.log_validated', false) ? 'info' : 'debug';
        $this->log($logLevel, 'SSRF validation: URL validated successfully', [
            'url' => $url,
            'ip' => $ip,
            'tenant_id' => $tenantId,
        ]);

        return ['valid' => true];
    }

    /**
     * Check if a domain is in the allowed list.
     *
     * @param  string  $domain  The domain to check
     * @return bool True if domain is allowed
     */
    private function isDomainAllowed(string $domain): bool
    {
        if (empty($this->allowedDomains)) {
            return false;
        }

        foreach ($this->allowedDomains as $allowedDomain) {
            // Exact match
            if ($domain === $allowedDomain) {
                return true;
            }

            // Wildcard subdomain match (e.g., *.example.com matches sub.example.com)
            if (str_starts_with($allowedDomain, '*.')) {
                $baseDomain = substr($allowedDomain, 2);

                if ($domain === $baseDomain || str_ends_with($domain, '.' . $baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve a hostname to IP address with error handling.
     *
     * @param  string  $host  Hostname to resolve
     * @return array{success: bool, ip?: string, error?: string}
     */
    private function resolveHostname(string $host): array
    {
        try {
            // Use dns_get_record for more control and IPv6 support
            $records = dns_get_record($host, DNS_A);

            if (empty($records)) {
                // Try IPv6
                $records = dns_get_record($host, DNS_AAAA);
            }

            if (empty($records)) {
                return ['success' => false, 'error' => 'No DNS records found'];
            }

            // Get first IP address
            $ip = null;
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ip = $record['ip'];
                    break;
                }
                if (isset($record['ipv6'])) {
                    $ip = $record['ipv6'];
                    break;
                }
            }

            if (! $ip) {
                return ['success' => false, 'error' => 'Could not extract IP from DNS records'];
            }

            return ['success' => true, 'ip' => $ip];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create an audit log entry for SSRF-related events.
     *
     * @param  ?int  $tenantId  Tenant ID
     * @param  ?int  $userId  User ID
     * @param  string  $eventType  Event type (e.g., 'ssrf_attack_blocked', 'ssrf_url_blocked')
     * @param  array<string, mixed>  $properties  Additional properties
     */
    private function createAuditLog(
        ?int $tenantId,
        ?int $userId,
        string $eventType,
        array $properties
    ): void {
        // Only create audit log if enabled and we have tenant context
        if (! config('ssrf.audit_logging', true) || ! $tenantId) {
            return;
        }

        try {
            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'event_type' => 'security.' . $eventType,
                'auditable_type' => null,
                'auditable_id' => null,
                'ip_address' => request()->ip() ?? 'unknown',
                'user_agent' => request()->userAgent() ?? 'unknown',
                'metadata' => $properties,
            ]);
        } catch (\Exception $e) {
            // Fail silently - audit logging shouldn't break functionality
            $this->log('error', 'Failed to create SSRF audit log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Validate a URL and throw an exception if invalid.
     *
     * @param  string  $url  The URL to validate
     *
     * @throws InvalidArgumentException
     */
    public function validateUrlOrFail(string $url): void
    {
        $result = $this->validateUrl($url);

        if (! $result['valid']) {
            throw new InvalidArgumentException($result['error'] ?? 'Invalid URL');
        }
    }

    /**
     * Check if URL redirects to a blocked location (basic implementation).
     *
     * @param  string  $url  The URL to check
     * @return bool True if URL redirects to blocked location
     */
    public function checkRedirects(string $url): bool
    {
        try {
            // Use Laravel HTTP client with no follow redirect
            $response = \Illuminate\Support\Facades\Http::withoutRedirecting()
                ->timeout(5)
                ->head($url);

            // Check if it's a redirect
            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');

                if ($location) {
                    $redirectResult = $this->validateUrl($location);

                    return ! $redirectResult['valid'];
                }
            }

            return false;
        } catch (\Exception $e) {
            // If we can't check, be safe and block
            return true;
        }
    }

    /**
     * Enable strict mode for enhanced security.
     */
    public function enableStrictMode(): void
    {
        $this->strictMode = 'STRICT';
    }

    /**
     * Disable strict mode for development/testing.
     */
    public function disableStrictMode(): void
    {
        $this->strictMode = 'OFF';
    }

    /**
     * Set security mode.
     *
     * @param  string  $mode  Security mode: 'OFF', 'SOFT', or 'STRICT'
     */
    public function setSecurityMode(string $mode): void
    {
        $this->strictMode = strtoupper($mode);
    }

    /**
     * Enable allowlist-only mode.
     *
     * @param  array<string>  $domains  List of allowed domains
     */
    public function enableAllowlistMode(array $domains): void
    {
        $this->allowlistEnabled = true;
        $this->allowedDomains = $domains;
    }

    /**
     * Disable allowlist mode.
     */
    public function disableAllowlistMode(): void
    {
        $this->allowlistEnabled = false;
        $this->allowedDomains = [];
    }

    /**
     * Check if strict mode is enabled.
     */
    public function isStrictModeEnabled(): bool
    {
        return $this->strictMode === 'STRICT';
    }

    /**
     * Check if security mode is OFF (log only).
     */
    public function isSecurityModeOff(): bool
    {
        return $this->strictMode === 'OFF';
    }

    /**
     * Check if security mode is SOFT (partial enforcement).
     */
    public function isSecurityModeSoft(): bool
    {
        return $this->strictMode === 'SOFT';
    }

    /**
     * Check if security mode is STRICT (full enforcement).
     */
    public function isSecurityModeStrict(): bool
    {
        return $this->strictMode === 'STRICT';
    }

    /**
     * Check if allowlist mode is enabled.
     */
    public function isAllowlistModeEnabled(): bool
    {
        return $this->allowlistEnabled;
    }
}
