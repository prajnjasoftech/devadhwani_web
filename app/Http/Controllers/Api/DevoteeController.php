<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devotee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevoteeController extends Controller
{
    /**
     * List devotees with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Devotee::query()
            ->with('nakshathra:id,name,malayalam_name')
            ->withCount('bookingBeneficiaries as bookings_count');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('gothram', 'like', '%' . $request->search . '%');
            });
        }

        $devotees = $query
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $devotees->items(),
            'meta' => [
                'current_page' => $devotees->currentPage(),
                'last_page' => $devotees->lastPage(),
                'per_page' => $devotees->perPage(),
                'total' => $devotees->total(),
            ],
        ]);
    }

    /**
     * Show devotee with booking history
     */
    public function show(Devotee $devotee): JsonResponse
    {
        $devotee->load('nakshathra:id,name,malayalam_name');

        // Get booking history
        $bookingHistory = $devotee->bookingBeneficiaries()
            ->with([
                'bookingItem.booking',
                'bookingItem.pooja:id,name',
                'bookingItem.deity:id,name',
            ])
            ->get()
            ->filter(function ($beneficiary) {
                // Filter out any orphaned records
                return $beneficiary->bookingItem && $beneficiary->bookingItem->booking;
            })
            ->map(function ($beneficiary) {
                $item = $beneficiary->bookingItem;
                $booking = $item->booking;

                return [
                    'id' => $beneficiary->id,
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'booking_date' => $booking->booking_date->format('d M Y'),
                    'booking_status' => $booking->booking_status,
                    'pooja_name' => $item->pooja->name ?? '-',
                    'deity_name' => $item->deity->name ?? '-',
                    'frequency_label' => ucfirst($item->frequency),
                    'scheduled_date' => $item->start_date->format('d M Y'),
                    'amount_formatted' => '₹' . number_format($item->total_amount, 2),
                ];
            })
            ->sortByDesc('booking_date')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $devotee->id,
                'name' => $devotee->name,
                'nakshathra' => $devotee->nakshathra,
                'gothram' => $devotee->gothram,
                'booking_history' => $bookingHistory,
            ],
        ]);
    }

    /**
     * Search devotees by name (autocomplete)
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');

        if (strlen($term) < 3) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $devotees = Devotee::query()
            ->with('nakshathra:id,name,malayalam_name')
            ->search($term)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'nakshathra_id', 'gothram']);

        return response()->json([
            'success' => true,
            'data' => $devotees,
        ]);
    }

    /**
     * Store a new devotee (called automatically when creating booking)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nakshathra_id' => 'nullable|exists:nakshathras,id',
            'gothram' => 'nullable|string|max:100',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;

        // Check if devotee already exists with same details
        $existing = Devotee::where('temple_id', $validated['temple_id'])
            ->where('name', $validated['name'])
            ->where('nakshathra_id', $validated['nakshathra_id'])
            ->where('gothram', $validated['gothram'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $existing->load('nakshathra:id,name,malayalam_name'),
                'message' => 'Devotee already exists',
            ]);
        }

        $devotee = Devotee::create($validated);

        return response()->json([
            'success' => true,
            'data' => $devotee->load('nakshathra:id,name,malayalam_name'),
            'message' => 'Devotee created successfully',
        ], 201);
    }
}
