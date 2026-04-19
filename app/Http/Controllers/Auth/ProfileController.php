<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private SecurityAuditLogger $securityLogger
    ) {}

    /**
     * Get the authenticated user's profile information.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenant', 'store', 'warehouse', 'roles']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
            'message' => 'User profile retrieved successfully',
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        // Security audit logging
        $this->securityLogger->logAsync(
            eventType: 'user.profile_updated',
            description: "User '{$user->name}' updated their profile information",
            context: [
                'user_id' => $user->id,
                'changes' => array_keys($validated),
            ],
            tenantId: $user->tenant_id,
            userId: $user->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load(['tenant', 'store', 'warehouse', 'roles']),
            ],
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Security audit logging
        $this->securityLogger->logAsync(
            eventType: 'user.password_updated',
            description: "User '{$user->name}' updated their password",
            context: [
                'user_id' => $user->id,
            ],
            tenantId: $user->tenant_id,
            userId: $user->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }
}
