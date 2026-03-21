<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class ImpersonationService
{
    /**
     * Generate an impersonation token for a user.
     *
     * @param  User  $user  The user to impersonate
     * @param  User  $impersonator  The super admin performing impersonation
     * @return array{token: string, expiresAt: \Carbon\Carbon}
     */
    public function generateImpersonationToken(User $user, User $impersonator): array
    {
        // Create a token with a special name indicating impersonation
        $tokenName = "impersonation_{$user->id}_by_{$impersonator->id}_" . time();

        // Create token with 15 minute expiry
        $expiresAt = now()->addMinutes(15);

        /** @var NewAccessToken $accessToken */
        $accessToken = $user->createToken($tokenName, ['impersonation'], $expiresAt);

        return [
            'token' => $accessToken->plainTextToken,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Check if a token is an impersonation token.
     */
    public function isImpersonationToken(string $tokenName): bool
    {
        return str_starts_with($tokenName, 'impersonation_');
    }

    /**
     * Extract impersonated user ID from token name.
     */
    public function getImpersonatedUserId(string $tokenName): ?int
    {
        if (! $this->isImpersonationToken($tokenName)) {
            return null;
        }

        // Token name format: impersonation_{userId}_by_{adminId}_{timestamp}
        $parts = explode('_', $tokenName);

        if (count($parts) < 4) {
            return null;
        }

        return (int) ($parts[1] ?? 0);
    }

    /**
     * Revoke all impersonation tokens for a user.
     */
    public function revokeImpersonationTokens(User $user): int
    {
        $revoked = 0;

        foreach ($user->tokens as $token) {
            if ($this->isImpersonationToken($token->name)) {
                $token->delete();
                $revoked++;
            }
        }

        return $revoked;
    }

    /**
     * Get active impersonation sessions for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveImpersonations(User $user): array
    {
        $impersonations = [];

        foreach ($user->tokens as $token) {
            if ($this->isImpersonationToken($token->name)) {
                $impersonations[] = [
                    'token_id' => $token->id,
                    'token_name' => $token->name,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                ];
            }
        }

        return $impersonations;
    }
}
