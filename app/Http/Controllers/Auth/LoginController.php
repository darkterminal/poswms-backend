<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @var int Maximum failed login attempts before lockout
     */
    private int $maxAttempts = 5;

    /**
     * @var int Lockout duration in seconds (5 minutes)
     */
    private int $lockoutDuration = 300;

    /**
     * @var int Cache TTL for failed attempts (15 minutes)
     */
    private int $cacheTtl = 900;

    /**
     * Handle user login and create Sanctum token.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $ipAddress = $request->ip();
        $lockoutKey = 'login_attempts:' . strtolower(trim($email));

        // Check for account lockout
        $lockoutUntil = Cache::get($lockoutKey . ':lockout_until');

        if ($lockoutUntil && now()->timestamp < $lockoutUntil) {
            $waitTime = $lockoutUntil - now()->timestamp;

            Log::warning('Login attempt on locked account', [
                'email' => $email,
                'ip' => $ipAddress,
                'wait_time' => $waitTime,
            ]);

            // Only log if we have a user (previous login attempt)
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $existingUser->tenant_id) {
                AuditLog::create([
                    'tenant_id' => $existingUser->tenant_id,
                    'user_id' => $existingUser->id,
                    'event_type' => 'auth.login_locked',
                    'auditable_type' => 'App\\Models\\User',
                    'auditable_id' => $existingUser->id,
                    'description' => 'Login attempt on locked account',
                    'ip_address' => $ipAddress,
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'email' => $email,
                        'wait_time' => $waitTime,
                    ],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Account locked. Try again in ' . ceil($waitTime / 60) . ' minutes.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Increment failed attempts
            $attempts = Cache::get($lockoutKey, 0);
            $attempts++;
            Cache::put($lockoutKey, $attempts, $this->cacheTtl);

            // Log failed attempt
            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip' => $ipAddress,
                'attempt' => $attempts,
                'user_found' => $user !== null,
            ]);

            // Check if should lock account
            if ($attempts >= $this->maxAttempts) {
                $lockoutUntil = now()->addSeconds($this->lockoutDuration)->timestamp;
                Cache::put($lockoutKey . ':lockout_until', $lockoutUntil, $this->lockoutDuration);

                Log::warning('Account locked due to failed login attempts', [
                    'email' => $email,
                    'ip' => $ipAddress,
                    'attempts' => $attempts,
                ]);

                // Only create audit log if we have a user with tenant_id
                if ($user && $user->tenant_id) {
                    AuditLog::create([
                        'tenant_id' => $user->tenant_id,
                        'user_id' => $user->id,
                        'event_type' => 'auth.login_locked',
                        'auditable_type' => 'App\\Models\\User',
                        'auditable_id' => $user->id,
                        'description' => 'Account locked after ' . $attempts . ' failed attempts',
                        'ip_address' => $ipAddress,
                        'user_agent' => $request->userAgent(),
                        'metadata' => [
                            'email' => $email,
                            'attempts' => $attempts,
                            'lockout_duration' => $this->lockoutDuration,
                        ],
                    ]);
                }

                throw ValidationException::withMessages([
                    'email' => ['Too many failed attempts. Account locked. Try again in ' . ceil($this->lockoutDuration / 60) . ' minutes.'],
                ]);
            }

            // Only create audit log if we have a user with tenant_id
            if ($user && $user->tenant_id) {
                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'event_type' => 'auth.login_failed',
                    'auditable_type' => 'App\\Models\\User',
                    'auditable_id' => $user->id,
                    'description' => 'Failed login attempt',
                    'ip_address' => $ipAddress,
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'email' => $email,
                        'attempt' => $attempts,
                        'remaining_attempts' => $this->maxAttempts - $attempts,
                    ],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Successful login - reset failed attempts
        Cache::forget($lockoutKey);
        Cache::forget($lockoutKey . ':lockout_until');

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Log successful login only if user has tenant_id
        if ($user->tenant_id) {
            AuditLog::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event_type' => 'auth.login_success',
                'auditable_type' => 'App\\Models\\User',
                'auditable_id' => $user->id,
                'description' => 'User logged in successfully',
                'ip_address' => $ipAddress,
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'email' => $email,
                ],
            ]);
        }

        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $ipAddress,
        ]);

        // Create a new token for the user
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'Login successful',
        ], 200);
    }

    /**
     * Handle user logout by revoking current token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], 200);
    }

    /**
     * Refresh token (revoke current and issue new one).
     */
    public function refresh(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $request->user()->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'Token refreshed successfully',
        ], 200);
    }

    /**
     * Get authenticated user details.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user(),
            ],
            'message' => 'User retrieved successfully',
        ], 200);
    }
}
