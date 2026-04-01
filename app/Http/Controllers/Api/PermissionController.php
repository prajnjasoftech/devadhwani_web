<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        // Temple users can see/assign ALL permissions EXCEPT temples
        // Temples module is exclusively managed by Platform Admin
        if ($user->isTempleUser()) {
            $permissions = Permission::where('module_key', '!=', 'temples')
                ->get()
                ->groupBy('module_key')
                ->map(function ($group, $moduleKey) {
                    $first = $group->first();
                    return [
                        'module_key' => $moduleKey,
                        'module_name' => $first->module_name,
                        'permissions' => $group->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'action' => $permission->action,
                                'key' => $permission->key,
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => Permission::getGroupedByModule(),
        ]);
    }
}
