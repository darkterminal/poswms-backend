<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'access_denied',
                    'message' => 'Unauthorized. Super admin access required.',
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
