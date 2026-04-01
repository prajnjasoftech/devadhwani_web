<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssetType::query()->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $query
            ->withCount('donations')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $types->items(),
            'meta' => [
                'current_page' => $types->currentPage(),
                'last_page' => $types->lastPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $types = AssetType::active()->orderBy('name')->get(['id', 'name', 'unit']);

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        $type = AssetType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Asset type created successfully',
            'data' => $type,
        ], 201);
    }

    public function show(AssetType $assetType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $assetType,
        ]);
    }

    public function update(Request $request, AssetType $assetType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $assetType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Asset type updated successfully',
            'data' => $assetType,
        ]);
    }

    public function destroy(AssetType $assetType): JsonResponse
    {
        if ($assetType->donations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete asset type with existing donations',
            ], 422);
        }

        $assetType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset type deleted successfully',
        ]);
    }
}
