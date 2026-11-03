<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token stays valid until it expires, so a suspended or deleted account
 * is checked on every request rather than only at login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $user->tokens()->delete();

            return response()->json([
                'status' => false,
                'message' => 'This account has been suspended. Please contact support.',
                'error_code' => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
