<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to detect and log potential injection attempts.
 *
 * This middleware inspects incoming requests for common injection patterns:
 * - SQL injection attempts in query parameters
 * - Command injection patterns
 * - XSS script tags
 *
 * All suspicious activity is logged for security monitoring.
 * The middleware does NOT block requests by default (monitoring mode),
 * but can be configured to block in production environments.
 */
class LogInjectionAttempts
{
    /**
     * Patterns that indicate potential injection attacks.
     */
    protected array $sqlInjectionPatterns = [
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bOR\b.*=.*)/i',
        '/(\bAND\b.*=.*)/i',
        '/(--)/',
        '/(\/\*)/',
        '/(\*\/)/',
        '/(#)/',
        '/(;)/',
        '/(\bWAITFOR\b.*\bDELAY\b)/i',
        '/(\bBENCHMARK\b)/i',
        '/(\bSLEEP\b)/i',
        '/(\bINFORMATION_SCHEMA\b)/i',
    ];

    protected array $commandInjectionPatterns = [
        '/[;&|`$(){}[\]<>\\\\!]/',
        '/(\bexec\b)/i',
        '/(\bsystem\b)/i',
        '/(\bshell\b)/i',
        '/(\beval\b)/i',
        '/(\bpassthru\b)/i',
        '/(\bpopen\b)/i',
        '/(\bproc_open\b)/i',
        '/(\bcurl\b.*\bhttp)/i',
        '/(\bwget\b)/i',
    ];

    protected array $xssPatterns = [
        '/<script.*?>/i',
        '/<\/script>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',  // onclick=, onerror=, etc.
        '/<iframe/i',
        '/<object/i',
        '/<embed/i',
    ];

    /**
     * Whether to block requests with detected injection attempts.
     * Set to true in production for active protection.
     */
    protected bool $blockOnDetection = false;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check all input sources for injection patterns
        $inputs = $this->getAllInputs($request);

        foreach ($inputs as $key => $value) {
            if (is_string($value) && ! empty(trim($value))) {
                $detectionResult = $this->checkForInjection($value, $key);

                if ($detectionResult['detected']) {
                    $this->logInjectionAttempt($request, $key, $value, $detectionResult['type'], $detectionResult['pattern']);

                    if ($this->blockOnDetection) {
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 'SECURITY_BLOCKED',
                                'message' => 'Request blocked due to security policy violation',
                            ],
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Get all input data from the request.
     *
     * @return array<string, mixed>
     */
    protected function getAllInputs(Request $request): array
    {
        return array_merge(
            $request->query->all(),
            $request->all()
        );
    }

    /**
     * Check if value contains injection patterns.
     *
     * @param  string  $value  The input value to check
     * @param  string  $key  The input key/parameter name
     * @return array{detected: bool, type: string, pattern: string}
     */
    protected function checkForInjection(string $value, string $key): array
    {
        // Skip certain safe parameters
        $skipParameters = ['password', 'password_confirmation', 'token', 'api_key'];
        if (in_array(strtolower($key), $skipParameters, true)) {
            return ['detected' => false, 'type' => '', 'pattern' => ''];
        }

        // Check SQL injection
        foreach ($this->sqlInjectionPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return ['detected' => true, 'type' => 'sql_injection', 'pattern' => $pattern];
            }
        }

        // Check command injection
        foreach ($this->commandInjectionPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return ['detected' => true, 'type' => 'command_injection', 'pattern' => $pattern];
            }
        }

        // Check XSS
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return ['detected' => true, 'type' => 'xss', 'pattern' => $pattern];
            }
        }

        return ['detected' => false, 'type' => '', 'pattern' => ''];
    }

    /**
     * Log the injection attempt for security monitoring.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $parameter  The parameter name
     * @param  string  $value  The parameter value
     * @param  string  $type  The type of injection detected
     * @param  string  $pattern  The pattern that matched
     */
    protected function logInjectionAttempt(
        Request $request,
        string $parameter,
        string $value,
        string $type,
        string $pattern
    ): void {
        // Truncate value for logging (avoid logging sensitive data)
        $truncatedValue = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;

        Log::warning('Potential injection attempt detected', [
            'type' => $type,
            'parameter' => $parameter,
            'value' => $truncatedValue,
            'pattern_matched' => $pattern,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'query' => $request->getQueryString(),
        ]);
    }
}
