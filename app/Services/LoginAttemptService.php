<?php

namespace App\Services;

use App\Models\User;
use App\SecurityAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing login attempts and implementing progressive lockout.
 *
 * This service provides:
 * - Cache-based failed login tracking
 * - Progressive delays (soft lockout) before hard lockout
 * - Account lockout after threshold
 * - Comprehensive audit logging
 * - IP-based tracking for security monitoring
 */
class LoginAttemptService
{
    /**
     * @var int Maximum failed login attempts before hard lockout
     */
    private int $maxAttempts;

    /**
     * @var int Number of attempts before warning is shown
     */
    private int $warningThreshold;

    /**
     * @var int Base lockout duration in seconds (5 minutes)
     */
    private int $baseLockoutDuration;

    /**
     * @var int Maximum lockout duration in seconds (30 minutes)
     */
    private int $maxLockoutDuration;

    /**
     * @var int Cache TTL for failed attempts (15 minutes)
     */
    private int $cacheTtl;

    /**
     * @var int Delay multiplier for progressive delays
     */
    private int $delayMultiplier;

    public function __construct(
        private SecurityAuditLogger $securityLogger
    ) {
        // Configuration can be overridden via config/auth.php if needed
        $this->maxAttempts = config('auth.login.max_attempts', 5);
        $this->warningThreshold = config('auth.login.warning_threshold', 3);
        $this->baseLockoutDuration = config('auth.login.base_lockout_duration', 300);
        $this->maxLockoutDuration = config('auth.login.max_lockout_duration', 1800);
        $this->cacheTtl = config('auth.login.cache_ttl', 900);
        $this->delayMultiplier = config('auth.login.delay_multiplier', 2);
    }

    /**
     * Check if account is locked out.
     *
     * @param  string  $email  User email
     * @param  User|null  $user  User model if found (for audit logging)
     * @return array{locked: bool, waitTime: int, remainingAttempts: int}
     */
    public function checkLockout(string $email, ?User $user = null): array
    {
        $lockoutKey = $this->getLockoutKey($email);
        $lockoutUntil = Cache::get($lockoutKey . ':lockout_until');

        if ($lockoutUntil && now()->timestamp < $lockoutUntil) {
            $waitTime = $lockoutUntil - now()->timestamp;

            // Log audit event for login attempt on locked account
            if ($user) {
                $this->securityLogger->log(
                    eventType: 'auth.login_locked',
                    description: 'Login attempt on locked account',
                    context: [
                        'email' => $email,
                        'user_id' => $user->id,
                        'wait_time' => $waitTime,
                    ],
                    tenantId: $user->tenant_id,
                    userId: $user->id,
                );
            }

            return [
                'locked' => true,
                'waitTime' => max(0, $waitTime),
                'remainingAttempts' => 0,
            ];
        }

        $attempts = Cache::get($lockoutKey, 0);
        $remainingAttempts = max(0, $this->maxAttempts - $attempts);

        return [
            'locked' => false,
            'waitTime' => 0,
            'remainingAttempts' => $remainingAttempts,
        ];
    }

    /**
     * Record a failed login attempt and apply progressive delays.
     *
     * @param  string  $email  User email
     * @param  User|null  $user  User model if found
     * @param  Request  $request  HTTP request
     * @return array{shouldLock: bool, waitTime: int, attempts: int, remainingAttempts: int, isWarning: bool}
     */
    public function recordFailedAttempt(string $email, ?User $user, Request $request): array
    {
        $lockoutKey = $this->getLockoutKey($email);
        $ipAddress = $request->ip();

        // Get current attempts
        $attempts = Cache::get($lockoutKey, 0);
        $attempts++;

        // Store attempt count with TTL
        Cache::put($lockoutKey, $attempts, $this->cacheTtl);

        // Calculate progressive delay
        $waitTime = 0;
        $shouldLock = false;

        if ($attempts >= $this->maxAttempts) {
            // Hard lockout after max attempts
            $lockoutDuration = $this->calculateLockoutDuration($attempts);
            $lockoutUntil = now()->addSeconds($lockoutDuration)->timestamp;
            Cache::put($lockoutKey . ':lockout_until', $lockoutUntil, $lockoutDuration);
            $waitTime = $lockoutDuration;
            $shouldLock = true;

            Log::warning('Account locked due to failed login attempts', [
                'email' => $email,
                'ip' => $ipAddress,
                'attempts' => $attempts,
                'lockout_duration' => $lockoutDuration,
            ]);

            // Create audit log using security logger
            $this->securityLogger->logAccountLockout(
                email: $email,
                attemptCount: $attempts,
                lockoutDuration: $lockoutDuration
            );
        } elseif ($attempts >= $this->warningThreshold) {
            // Progressive delay before lockout
            $waitTime = $this->calculateProgressiveDelay($attempts);

            Log::warning('Progressive delay applied to login attempt', [
                'email' => $email,
                'ip' => $ipAddress,
                'attempts' => $attempts,
                'delay_seconds' => $waitTime,
            ]);
        }

        // Log failed attempt
        Log::warning('Failed login attempt', [
            'email' => $email,
            'ip' => $ipAddress,
            'attempt' => $attempts,
            'user_found' => $user !== null,
            'wait_time' => $waitTime,
        ]);

        // Create audit log using security logger
        $this->securityLogger->logAuthFailure(
            email: $email,
            userId: $user?->id,
            context: [
                'attempt' => $attempts,
                'remaining_attempts' => $this->maxAttempts - $attempts,
                'wait_time' => $waitTime,
                'ip_address' => $ipAddress,
            ]
        );

        return [
            'shouldLock' => $shouldLock,
            'waitTime' => $waitTime,
            'attempts' => $attempts,
            'remainingAttempts' => max(0, $this->maxAttempts - $attempts),
            'isWarning' => $attempts >= $this->warningThreshold && $attempts < $this->maxAttempts,
        ];
    }

    /**
     * Reset failed login attempts after successful login.
     *
     * @param  string  $email  User email
     */
    public function resetAttempts(string $email): void
    {
        $lockoutKey = $this->getLockoutKey($email);
        Cache::forget($lockoutKey);
        Cache::forget($lockoutKey . ':lockout_until');
    }

    /**
     * Get the number of failed attempts for an email.
     *
     * @param  string  $email  User email
     * @return int Number of failed attempts
     */
    public function getAttempts(string $email): int
    {
        $lockoutKey = $this->getLockoutKey($email);

        return Cache::get($lockoutKey, 0);
    }

    /**
     * Check if this is a suspicious login (new IP, unusual time, etc.).
     *
     * @param  User  $user  User model
     * @param  Request  $request  HTTP request
     * @return array{isSuspicious: bool, reasons: array}
     */
    public function checkSuspiciousLogin(User $user, Request $request): array
    {
        $reasons = [];
        $ipAddress = $request->ip();

        // Check if IP is different from last login
        $lastLoginIp = $user->last_login_ip ?? null;
        if ($lastLoginIp && $lastLoginIp !== $ipAddress) {
            $reasons[] = 'ip_change';
        }

        // Check if login is at unusual hour (configurable)
        $hour = now()->hour;
        $unusualHours = config('auth.login.unusual_hours', [0, 1, 2, 3, 4, 5]);
        if (in_array($hour, $unusualHours, true)) {
            $reasons[] = 'unusual_hour';
        }

        // Check if many failed attempts recently
        $recentAttempts = $this->getAttempts($user->email);
        if ($recentAttempts > 0) {
            $reasons[] = 'recent_failed_attempts';
        }

        return [
            'isSuspicious' => count($reasons) > 0,
            'reasons' => $reasons,
        ];
    }

    /**
     * Record successful login and update user metadata.
     *
     * @param  User  $user  User model
     * @param  Request  $request  HTTP request
     * @param  bool  $wasSuspicious  Whether the login was flagged as suspicious
     */
    public function recordSuccessfulLogin(User $user, Request $request, bool $wasSuspicious = false): void
    {
        $ipAddress = $request->ip();

        // Update user's last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        // Log successful login
        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $ipAddress,
            'suspicious' => $wasSuspicious,
        ]);

        // Create audit log using security logger
        $this->securityLogger->log(
            eventType: 'auth.login_success',
            description: 'User logged in successfully' . ($wasSuspicious ? ' (suspicious)' : ''),
            context: [
                'email' => $user->email,
                'suspicious' => $wasSuspicious,
                'ip_address' => $ipAddress,
            ],
            tenantId: $user->tenant_id,
            userId: $user->id,
            request: $request,
        );
    }

    /**
     * Calculate progressive delay based on attempt count.
     *
     * Formula: base_delay * (multiplier ^ (attempts - warning_threshold))
     * Example: 2^1=2s, 2^2=4s, 2^3=8s, 2^4=16s
     *
     * @param  int  $attempts  Number of failed attempts
     * @return int Delay in seconds
     */
    private function calculateProgressiveDelay(int $attempts): int
    {
        $exponent = max(0, $attempts - $this->warningThreshold);

        return (int) ($this->baseLockoutDuration / 60 * pow($this->delayMultiplier, $exponent));
    }

    /**
     * Calculate lockout duration with exponential backoff.
     *
     * Formula: base_duration * (2 ^ (attempts - max_attempts))
     * Capped at max_lockout_duration
     *
     * @param  int  $attempts  Number of failed attempts
     * @return int Lockout duration in seconds
     */
    private function calculateLockoutDuration(int $attempts): int
    {
        $exponent = max(0, $attempts - $this->maxAttempts);
        $duration = $this->baseLockoutDuration * pow(2, $exponent);

        return min($duration, $this->maxLockoutDuration);
    }

    /**
     * Generate cache key for login attempts.
     *
     * @param  string  $email  User email
     * @return string Cache key
     */
    private function getLockoutKey(string $email): string
    {
        return 'login_attempts:' . strtolower(trim($email));
    }
}
