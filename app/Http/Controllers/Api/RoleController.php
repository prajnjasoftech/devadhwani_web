<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->search($request->search)
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('role_name')
            ->get(['id', 'role_name', 'is_system_role']);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $data['temple_id'] = auth()->user()->temple_id;
        $data['is_system_role'] = false;

        $role = Role::create($data);
        $role->syncPermissions($permissions);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->load('permissions')->loadCount('users')),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->is_system_role) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be modified',
            ], 422);
        }

        $data = $request->validated();
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role->update($data);

        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => new RoleResource($role->fresh()->load('permissions')),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system_role) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role with assigned users',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }
}
