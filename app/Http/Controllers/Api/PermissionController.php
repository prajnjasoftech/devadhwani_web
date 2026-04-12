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

        // Temple users can see/assign permissions EXCEPT:
        // - temples: Platform Admin only
        // - dashboard: Doesn't need permission (available to all)
        if ($user->isTempleUser()) {
            $permissions = Permission::whereNotIn('module_key', ['temples', 'dashboard'])
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
