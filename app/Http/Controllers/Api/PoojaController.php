<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePoojaRequest;
use App\Http\Requests\UpdatePoojaRequest;
use App\Http\Resources\PoojaResource;
use App\Models\BookingItem;
use App\Models\Pooja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoojaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pooja::query()
            ->with('deity')
            ->search($request->search);

        if ($request->has('deity_id')) {
            $query->where('deity_id', $request->deity_id);
        }

        if ($request->has('frequency')) {
            $query->where('frequency', $request->frequency);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $poojas = $query
            ->orderBy($request->sort_by ?? 'name', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => PoojaResource::collection($poojas),
            'meta' => [
                'current_page' => $poojas->currentPage(),
                'last_page' => $poojas->lastPage(),
                'per_page' => $poojas->perPage(),
                'total' => $poojas->total(),
            ],
        ]);
    }

    public function all(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        // Get booking counts per pooja for this temple
        $bookingCounts = BookingItem::query()
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('bookings.temple_id', $templeId)
            ->select('booking_items.pooja_id', DB::raw('COUNT(*) as booking_count'))
            ->groupBy('booking_items.pooja_id')
            ->pluck('booking_count', 'pooja_id');

        $poojas = Pooja::query()
            ->with('deity:id,name')
            ->active()
            ->orderBy('name')
            ->get();

        // Add booking count and sort by frequency (desc), then name (asc)
        $poojas = $poojas->map(function ($pooja) use ($bookingCounts) {
            $pooja->booking_count = $bookingCounts[$pooja->id] ?? 0;
            return $pooja;
        })->sortBy([
            ['booking_count', 'desc'],
            ['name', 'asc'],
        ])->values();

        return response()->json([
            'success' => true,
            'data' => PoojaResource::collection($poojas),
        ]);
    }

    public function store(StorePoojaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['temple_id'] = auth()->user()->temple_id;

        $pooja = Pooja::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Pooja created successfully',
            'data' => new PoojaResource($pooja->load('deity')),
        ], 201);
    }

    public function show(Pooja $pooja): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PoojaResource($pooja->load('deity')),
        ]);
    }

    public function update(UpdatePoojaRequest $request, Pooja $pooja): JsonResponse
    {
        $pooja->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pooja updated successfully',
            'data' => new PoojaResource($pooja->fresh()->load('deity')),
        ]);
    }

    public function destroy(Pooja $pooja): JsonResponse
    {
        $pooja->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pooja deleted successfully',
        ]);
    }
}
