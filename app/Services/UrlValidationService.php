<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Service for validating URLs to prevent SSRF attacks.
 *
 * This service validates that URLs do not point to:
 * - Private IP ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
 * - Loopback addresses (127.x.x.x)
 * - Link-local addresses (169.254.x.x)
 * - Cloud metadata endpoints
 * - Localhost
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
        '/^169\.254\./',                         // Link-local
        '/^0\.0\.0\.0/',                         // All interfaces
        '/^::1$/',                               // IPv6 loopback
        '/^fc00:/i',                             // IPv6 unique local
        '/^fe80:/i',                             // IPv6 link-local
        '/^::ffff:127\./',                       // IPv4-mapped IPv6 loopback
    ];

    /**
     * @var array<string> List of blocked hostnames
     */
    private array $blockedHostnames = [
        'localhost',
        'metadata.google.internal',
        'metadata',
        'kubernetes.default.svc',
    ];

    /**
     * Validate a URL for SSRF protection.
     *
     * @param  string  $url  The URL to validate
     * @return array{valid: bool, error?: string}
     */
    public function validateUrl(string $url): array
    {
        // Basic URL validation
        $validator = Validator::make(
            ['url' => $url],
            ['url' => 'required|url|active_url']
        );

        if ($validator->fails()) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Parse the URL
        $parsedUrl = parse_url($url);

        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            return ['valid' => false, 'error' => 'Invalid URL: missing host'];
        }

        $host = $parsedUrl['host'];

        // Check blocked hostnames
        if (in_array(strtolower($host), $this->blockedHostnames, true)) {
            return ['valid' => false, 'error' => 'URL points to blocked host: ' . $host];
        }

        // Check blocked hostname patterns
        foreach ($this->blockedHostnames as $blockedHostname) {
            if (str_ends_with($host, '.' . $blockedHostname)) {
                return ['valid' => false, 'error' => 'URL points to blocked domain: ' . $host];
            }
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['valid' => false, 'error' => 'Could not resolve hostname: ' . $host];
        }

        // Check if IP is in blocked ranges
        foreach ($this->blockedIpPatterns as $pattern) {
            if (preg_match($pattern, $ip)) {
                return [
                    'valid' => false,
                    'error' => 'URL points to internal/private IP address: ' . $ip,
                ];
            }
        }

        // Additional check using PHP's filter for private/reserved ranges
        if (! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return [
                'valid' => false,
                'error' => 'URL points to private or reserved IP range: ' . $ip,
            ];
        }

        // Check for redirects (optional, can be expensive)
        // This is a basic check - production may want to skip or cache
        // if ($this->checkRedirects($url)) {
        //     return ['valid' => false, 'error' => 'URL redirects to blocked location'];
        // }

        return ['valid' => true];
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
