<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['role', 'temple', 'creator'])
            ->search($request->search);

        if (auth()->user()->isPlatformAdmin()) {
            if ($request->temple_id) {
                $query->where('temple_id', $request->temple_id);
            }
            if ($request->user_type) {
                $query->where('user_type', $request->user_type);
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->role_id) {
            $query->where('role_id', $request->role_id);
        }

        $users = $query
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['created_by'] = auth()->id();
        $data['must_reset_password'] = true;

        if (!auth()->user()->isPlatformAdmin()) {
            $data['temple_id'] = auth()->user()->temple_id;
            $data['user_type'] = 'temple_user';
        }

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('users/profiles', 'public');
        }

        if ($request->hasFile('id_proof_file')) {
            $data['id_proof_file'] = $request->file('id_proof_file')->store('users/id_proofs', 'public');
        }

        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user->load('role', 'temple')),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('role.permissions', 'temple', 'creator')),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('users/profiles', 'public');
        }

        if ($request->hasFile('id_proof_file')) {
            $data['id_proof_file'] = $request->file('id_proof_file')->store('users/id_proofs', 'public');
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user->fresh()->load('role', 'temple')),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $query = User::query();

        if (auth()->user()->isTempleUser()) {
            $query->where('temple_id', auth()->user()->temple_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->where('is_active', true)->count(),
                'inactive' => (clone $query)->where('is_active', false)->count(),
            ],
        ]);
    }
}
