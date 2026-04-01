<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query()
            ->withCount('purchases')
            ->search($request->search);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $vendors = $query
            ->orderBy($request->sort_by ?? 'name', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $vendors = Vendor::active()->orderBy('name')->get(['id', 'name', 'contact_person', 'contact_number']);

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        $vendor = Vendor::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vendor created successfully',
            'data' => $vendor,
        ], 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $vendor->loadCount('purchases');
        $vendor->load(['purchases' => function ($q) {
            $q->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ]);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $vendor->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully',
            'data' => $vendor,
        ]);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        if ($vendor->purchases()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vendor with existing purchases. Deactivate instead.',
            ], 422);
        }

        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor deleted successfully',
        ]);
    }
}
