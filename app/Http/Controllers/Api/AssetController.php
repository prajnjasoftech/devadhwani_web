<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->with(['assetType:id,name,unit', 'donation:id,donation_number,donor_name', 'creator:id,name'])
            ->search($request->search);

        if ($request->has('asset_type_id')) {
            $query->where('asset_type_id', $request->asset_type_id);
        }

        if ($request->has('acquisition_type')) {
            $query->where('acquisition_type', $request->acquisition_type);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $assets = $query
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_type_id' => 'required|exists:asset_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0.001',
            'estimated_value' => 'nullable|numeric|min:0',
            'acquisition_date' => 'nullable|date',
            'acquisition_type' => 'required|in:existing,donation,purchase',
            'donation_id' => 'nullable|exists:donations,id',
            'location' => 'nullable|string|max:255',
            'condition' => 'required|in:excellent,good,fair,poor',
            'notes' => 'nullable|string',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();

        // Clear donation_id if not from donation
        if ($validated['acquisition_type'] !== 'donation') {
            $validated['donation_id'] = null;
        }

        $asset = Asset::create($validated);
        $asset->load(['assetType:id,name,unit']);

        return response()->json([
            'success' => true,
            'message' => 'Asset added successfully',
            'data' => $asset,
        ], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['assetType', 'donation', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $asset,
        ]);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'asset_type_id' => 'sometimes|exists:asset_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|numeric|min:0.001',
            'estimated_value' => 'nullable|numeric|min:0',
            'acquisition_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'condition' => 'sometimes|in:excellent,good,fair,poor',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $asset->update($validated);
        $asset->load(['assetType:id,name,unit']);

        return response()->json([
            'success' => true,
            'message' => 'Asset updated successfully',
            'data' => $asset,
        ]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset deleted successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        // Get asset counts by type
        $byType = Asset::where('temple_id', $templeId)
            ->where('is_active', true)
            ->selectRaw('asset_type_id, COUNT(*) as count, SUM(estimated_value) as total_value')
            ->groupBy('asset_type_id')
            ->with('assetType:id,name')
            ->get();

        // Get total value
        $totalValue = Asset::where('temple_id', $templeId)
            ->where('is_active', true)
            ->sum('estimated_value');

        // Get counts by acquisition type
        $byAcquisition = Asset::where('temple_id', $templeId)
            ->where('is_active', true)
            ->selectRaw('acquisition_type, COUNT(*) as count')
            ->groupBy('acquisition_type')
            ->pluck('count', 'acquisition_type');

        return response()->json([
            'success' => true,
            'data' => [
                'total_assets' => Asset::where('temple_id', $templeId)->where('is_active', true)->count(),
                'total_value' => $totalValue,
                'by_type' => $byType,
                'by_acquisition' => $byAcquisition,
            ],
        ]);
    }

    /**
     * Create asset from donation
     */
    public function createFromDonation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'donation_id' => 'required|exists:donations,id',
            'location' => 'nullable|string|max:255',
            'condition' => 'required|in:excellent,good,fair,poor',
            'notes' => 'nullable|string',
        ]);

        $donation = \App\Models\Donation::findOrFail($validated['donation_id']);

        // Verify it's an asset donation
        if ($donation->donation_type !== 'asset') {
            return response()->json([
                'success' => false,
                'message' => 'Only asset donations can be added to asset register',
            ], 422);
        }

        // Check if already added
        if (Asset::where('donation_id', $donation->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This donation has already been added to asset register',
            ], 422);
        }

        $asset = Asset::create([
            'temple_id' => auth()->user()->temple_id,
            'asset_type_id' => $donation->asset_type_id,
            'name' => $donation->asset_description,
            'description' => "Donated by {$donation->donor_name}",
            'quantity' => $donation->quantity,
            'estimated_value' => $donation->estimated_value,
            'acquisition_date' => $donation->donation_date,
            'acquisition_type' => 'donation',
            'donation_id' => $donation->id,
            'location' => $validated['location'],
            'condition' => $validated['condition'],
            'notes' => $validated['notes'],
            'created_by' => auth()->id(),
        ]);

        $asset->load(['assetType:id,name,unit', 'donation:id,donation_number,donor_name']);

        return response()->json([
            'success' => true,
            'message' => 'Asset added from donation successfully',
            'data' => $asset,
        ], 201);
    }
}
