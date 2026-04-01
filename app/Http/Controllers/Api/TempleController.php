<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTempleRequest;
use App\Http\Requests\UpdateTempleRequest;
use App\Http\Resources\TempleResource;
use App\Models\Temple;
use App\Services\TempleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TempleController extends Controller
{
    public function __construct(private TempleService $templeService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $temples = Temple::query()
            ->search($request->search)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->withCount('users')
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => TempleResource::collection($temples),
            'meta' => [
                'current_page' => $temples->currentPage(),
                'last_page' => $temples->lastPage(),
                'per_page' => $temples->perPage(),
                'total' => $temples->total(),
            ],
        ]);
    }

    public function store(StoreTempleRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('temples', 'public');
        }

        if ($request->hasFile('id_proof_file')) {
            $data['id_proof_file'] = $request->file('id_proof_file')->store('temples/id_proofs', 'public');
        }

        $result = $this->templeService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Temple created successfully',
            'data' => [
                'temple' => new TempleResource($result['temple']),
                'super_admin' => [
                    'contact_number' => $result['user']->contact_number,
                    'password' => $result['password'],
                ],
            ],
        ], 201);
    }

    public function show(Temple $temple): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new TempleResource($temple->loadCount('users')),
        ]);
    }

    public function update(UpdateTempleRequest $request, Temple $temple): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('temples', 'public');
        }

        if ($request->hasFile('id_proof_file')) {
            $data['id_proof_file'] = $request->file('id_proof_file')->store('temples/id_proofs', 'public');
        }

        $temple = $this->templeService->update($temple, $data);

        return response()->json([
            'success' => true,
            'message' => 'Temple updated successfully',
            'data' => new TempleResource($temple),
        ]);
    }

    public function destroy(Temple $temple): JsonResponse
    {
        $this->templeService->delete($temple);

        return response()->json([
            'success' => true,
            'message' => 'Temple deleted successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Temple::count(),
                'active' => Temple::where('status', 'active')->count(),
                'inactive' => Temple::where('status', 'inactive')->count(),
                'suspended' => Temple::where('status', 'suspended')->count(),
            ],
        ]);
    }

    /**
     * Get current user's temple (for temple super admin only)
     */
    public function myTemple(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->temple_id) {
            return response()->json([
                'success' => false,
                'message' => 'No temple associated with this user',
            ], 404);
        }

        // Only Super Admin (system role) can access temple settings
        if (!$user->role || !$user->role->is_system_role) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can access temple settings',
            ], 403);
        }

        $temple = Temple::find($user->temple_id);

        return response()->json([
            'success' => true,
            'data' => new TempleResource($temple),
        ]);
    }

    /**
     * Update current user's temple (for temple super admin only)
     * Contact number cannot be changed as it's used for login
     */
    public function updateMyTemple(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->temple_id) {
            return response()->json([
                'success' => false,
                'message' => 'No temple associated with this user',
            ], 404);
        }

        // Only Super Admin (system role) can update temple settings
        if (!$user->role || !$user->role->is_system_role) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can update temple settings',
            ], 403);
        }

        $temple = Temple::find($user->temple_id);

        // Validate - exclude contact_number as it cannot be changed
        $data = $request->validate([
            'temple_name' => 'sometimes|string|max:255',
            'contact_person_name' => 'sometimes|string|max:255',
            'alternate_contact_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'place' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:2048',
            'id_proof_type' => 'nullable|in:aadhaar,pan,driving_license',
            'id_proof_number' => 'nullable|string|max:100',
            'id_proof_file' => 'nullable|file|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('temples', 'public');
        }

        if ($request->hasFile('id_proof_file')) {
            $data['id_proof_file'] = $request->file('id_proof_file')->store('temples/id_proofs', 'public');
        }

        $temple->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Temple updated successfully',
            'data' => new TempleResource($temple->fresh()),
        ]);
    }
}
