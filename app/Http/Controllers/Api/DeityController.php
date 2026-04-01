<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeityRequest;
use App\Http\Requests\UpdateDeityRequest;
use App\Http\Resources\DeityResource;
use App\Models\Deity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Deity::query()
            ->search($request->search)
            ->ordered();

        if ($request->has('deity_type')) {
            $query->where('deity_type', $request->deity_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $deities = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => DeityResource::collection($deities),
            'meta' => [
                'current_page' => $deities->currentPage(),
                'last_page' => $deities->lastPage(),
                'per_page' => $deities->perPage(),
                'total' => $deities->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $deities = Deity::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'deity_type']);

        return response()->json([
            'success' => true,
            'data' => $deities,
        ]);
    }

    public function store(StoreDeityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['temple_id'] = auth()->user()->temple_id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('deities', 'public');
        }

        $deity = Deity::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Deity created successfully',
            'data' => new DeityResource($deity),
        ], 201);
    }

    public function show(Deity $deity): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new DeityResource($deity),
        ]);
    }

    public function update(UpdateDeityRequest $request, Deity $deity): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('deities', 'public');
        }

        $deity->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Deity updated successfully',
            'data' => new DeityResource($deity->fresh()),
        ]);
    }

    public function destroy(Deity $deity): JsonResponse
    {
        $deity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deity deleted successfully',
        ]);
    }
}
