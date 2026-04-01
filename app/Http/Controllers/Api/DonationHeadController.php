<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonationHead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationHeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DonationHead::query()->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $heads = $query
            ->withCount('donations')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $heads->items(),
            'meta' => [
                'current_page' => $heads->currentPage(),
                'last_page' => $heads->lastPage(),
                'per_page' => $heads->perPage(),
                'total' => $heads->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $heads = DonationHead::active()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $heads,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        $head = DonationHead::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Donation head created successfully',
            'data' => $head,
        ], 201);
    }

    public function show(DonationHead $donationHead): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $donationHead,
        ]);
    }

    public function update(Request $request, DonationHead $donationHead): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $donationHead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Donation head updated successfully',
            'data' => $donationHead,
        ]);
    }

    public function destroy(DonationHead $donationHead): JsonResponse
    {
        if ($donationHead->donations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete donation head with existing donations',
            ], 422);
        }

        $donationHead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Donation head deleted successfully',
        ]);
    }
}
