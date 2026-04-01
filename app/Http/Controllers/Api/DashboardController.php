<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Temple;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'temples' => [
                        'total' => Temple::count(),
                        'active' => Temple::where('status', 'active')->count(),
                        'inactive' => Temple::where('status', 'inactive')->count(),
                        'suspended' => Temple::where('status', 'suspended')->count(),
                    ],
                ],
            ]);
        }

        $templeId = $user->temple_id;

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => User::where('temple_id', $templeId)->count(),
                    'active' => User::where('temple_id', $templeId)->where('is_active', true)->count(),
                ],
                'roles' => [
                    'total' => Role::where('temple_id', $templeId)->count(),
                ],
            ],
        ]);
    }
}
