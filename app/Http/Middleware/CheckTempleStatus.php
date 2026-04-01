<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTempleStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if temple user's temple is still active
        if ($user->isTempleUser() && $user->temple && $user->temple->status !== 'active') {
            // Revoke the current token
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your temple account has been deactivated. Please contact support.',
                'code' => 'TEMPLE_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
