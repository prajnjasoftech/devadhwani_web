<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_reset_password) {
            $allowedRoutes = [
                'api/auth/reset-password',
                'api/auth/logout',
                'api/auth/me',
            ];

            $currentPath = $request->path();

            if (!in_array($currentPath, $allowedRoutes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password reset required',
                    'must_reset_password' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
