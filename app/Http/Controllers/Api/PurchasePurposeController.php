<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasePurpose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasePurposeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchasePurpose::query()->withCount('purchases');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $purposes = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $purposes->items(),
            'meta' => [
                'current_page' => $purposes->currentPage(),
                'last_page' => $purposes->lastPage(),
                'per_page' => $purposes->perPage(),
                'total' => $purposes->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $purposes = PurchasePurpose::active()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $purposes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        $purpose = PurchasePurpose::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Purpose created successfully',
            'data' => $purpose,
        ], 201);
    }

    public function show(PurchasePurpose $purchasePurpose): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $purchasePurpose,
        ]);
    }

    public function update(Request $request, PurchasePurpose $purchasePurpose): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $purchasePurpose->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Purpose updated successfully',
            'data' => $purchasePurpose,
        ]);
    }

    public function destroy(PurchasePurpose $purchasePurpose): JsonResponse
    {
        if ($purchasePurpose->purchases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete purpose with existing purchases.',
            ], 422);
        }

        $purchasePurpose->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purpose deleted successfully',
        ]);
    }
}
