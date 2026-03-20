<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$permissions  List of permission slugs
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasAnyPermission($permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. Required: ' . implode(' or ', $permissions),
            ], 403);
        }

        return $next($request);
    }
}
