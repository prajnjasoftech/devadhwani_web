<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query()->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $query
            ->withCount('expenses')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

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
        $categories = ExpenseCategory::active()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        $category = ExpenseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully',
            'data' => $category,
        ], 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $expenseCategory,
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $expenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully',
            'data' => $expenseCategory,
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing expenses',
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully',
        ]);
    }
}
