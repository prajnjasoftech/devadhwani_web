<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'This action requires platform admin privileges',
            ], 403);
        }

        return $next($request);
    }
}
