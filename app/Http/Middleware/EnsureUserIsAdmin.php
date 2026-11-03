<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel gate. Customers who sign in through the mobile app share the
 * users table, so the panel checks the role on every request.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to access the admin panel.',
                ], 403);
            }

            abort(403, 'You do not have permission to access the admin panel.');
        }

        return $next($request);
    }
}
