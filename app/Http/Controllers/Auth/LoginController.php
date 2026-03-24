<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private LoginAttemptService $loginAttemptService
    ) {}

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

        // Check for account lockout (with progressive delay info)
        $lockoutStatus = $this->loginAttemptService->checkLockout($email);

        if ($lockoutStatus['locked']) {
            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Account locked. Try again in ' . ceil($lockoutStatus['waitTime'] / 60) . ' minutes.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Record failed attempt with progressive delay
            $attemptResult = $this->loginAttemptService->recordFailedAttempt($email, $user, $request);

            // Build appropriate error message
            if ($attemptResult['shouldLock']) {
                throw ValidationException::withMessages([
                    'email' => ['Too many failed attempts. Account locked. Try again in ' . ceil($attemptResult['waitTime'] / 60) . ' minutes.'],
                ]);
            }

            if ($attemptResult['isWarning']) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect. ' . $attemptResult['remainingAttempts'] . ' attempts remaining.'],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Successful login - reset failed attempts
        $this->loginAttemptService->resetAttempts($email);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Check for suspicious login patterns
        $suspiciousCheck = $this->loginAttemptService->checkSuspiciousLogin($user, $request);

        // Record successful login
        $this->loginAttemptService->recordSuccessfulLogin($user, $request, $suspiciousCheck['isSuspicious']);

        // Create a new token for the user
        $token = $user->createToken('auth-token')->plainTextToken;

        $responseData = [
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'Login successful',
        ];

        // Add warning header if suspicious login detected (non-intrusive)
        if ($suspiciousCheck['isSuspicious']) {
            $responseData['data']['security_notice'] = 'Login from new device or location detected';
        }

        return response()->json($responseData, 200);
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
