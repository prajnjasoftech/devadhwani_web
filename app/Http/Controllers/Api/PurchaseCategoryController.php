<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseCategory::query()->withCount('purchases');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $categories = PurchaseCategory::active()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $categories,
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

        $category = PurchaseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function show(PurchaseCategory $purchaseCategory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $purchaseCategory,
        ]);
    }

    public function update(Request $request, PurchaseCategory $purchaseCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $purchaseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $purchaseCategory,
        ]);
    }

    public function destroy(PurchaseCategory $purchaseCategory): JsonResponse
    {
        if ($purchaseCategory->purchases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing purchases.',
            ], 422);
        }

        $purchaseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
