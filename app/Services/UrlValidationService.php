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
     * @var array<string> List of blocked cloud metadata endpoints
     */
    private array $blockedCloudMetadataPaths = [
        '/latest/meta-data/',
        '/computeMetadata/',
        '/metadata/',
        '/2009-04-04/meta-data/',
    ];

    private ?LoggerInterface $logger = null;

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
        $validator = Validator::make(
            ['url' => $url],
            ['url' => 'required|url|active_url']
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

                return [
                    'valid' => false,
                    'error' => 'URL points to blocked cloud metadata endpoint',
                    'risk_level' => 'critical',
                ];
            }
        }

        // Check blocked hostnames
        if (in_array(strtolower($host), $this->blockedHostnames, true)) {
            $this->log('warning', 'SSRF validation: Blocked hostname', ['host' => $host, 'url' => $url]);

            return ['valid' => false, 'error' => 'URL points to blocked host: ' . $host];
        }

        // Check blocked hostname patterns
        foreach ($this->blockedHostnames as $blockedHostname) {
            if (str_ends_with($host, '.' . $blockedHostname)) {
                return ['valid' => false, 'error' => 'URL points to blocked domain: ' . $host];
            }
        }

        // DNS rebinding protection: resolve twice with delay
        if (! $skipDnsRebindingCheck) {
            $firstResolution = $this->resolveHostname($host);

            if (! $firstResolution['success']) {
                $this->log('warning', 'SSRF validation: DNS resolution failed', [
                    'host' => $host,
                    'error' => $firstResolution['error'],
                ]);

                return ['valid' => false, 'error' => 'Could not resolve hostname: ' . $host];
            }

            // Small delay to prevent DNS rebinding attacks
            usleep(100000); // 100ms

            $secondResolution = $this->resolveHostname($host);

            if (! $secondResolution['success']) {
                return ['valid' => false, 'error' => 'DNS resolution inconsistent (possible rebinding attack)'];
            }

            // Check for DNS rebinding (IP changed between resolutions)
            if ($firstResolution['ip'] !== $secondResolution['ip']) {
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

                return [
                    'valid' => false,
                    'error' => 'DNS rebinding detected (possible attack)',
                    'risk_level' => 'critical',
                ];
            }

            $ip = $firstResolution['ip'];
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

                // Allow non-IP hostnames in testing (they'll be checked against blocked patterns)
                $ip = '0.0.0.0'; // Placeholder - will pass through if not in blocked patterns
            }
        }

        // Check if IP is in blocked ranges
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

                return [
                    'valid' => false,
                    'error' => 'URL points to internal/private IP address: ' . $ip,
                    'risk_level' => 'high',
                ];
            }
        }

        // Additional check using PHP's filter for private/reserved ranges
        if (! filter_var(
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

            return [
                'valid' => false,
                'error' => 'URL points to private or reserved IP range: ' . $ip,
                'risk_level' => 'high',
            ];
        }

        // URL is valid - log for new URLs
        $this->log('info', 'SSRF validation: URL validated successfully', [
            'url' => $url,
            'ip' => $ip,
            'tenant_id' => $tenantId,
        ]);

        return ['valid' => true];
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
        // Only create audit log if we have tenant context
        if (! $tenantId) {
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
}
