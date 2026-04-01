<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->with(['items.pooja', 'items.deity', 'creator'])
            ->withCount('items')
            ->search($request->search);

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('booking_status')) {
            $query->where('booking_status', $request->booking_status);
        }

        if ($request->has('date_from')) {
            $query->where('booking_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('booking_date', '<=', $request->date_to);
        }

        $bookings = $query
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->createBooking($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => new BookingResource($booking),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('BookingController: Exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Booking $booking): JsonResponse
    {
        $booking->load([
            'items.pooja',
            'items.deity',
            'items.beneficiaries.nakshathra',
            'items.schedules',
            'payments.receiver',
            'creator',
        ]);

        return response()->json([
            'success' => true,
            'data' => new BookingResource($booking),
        ]);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->booking_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a cancelled booking',
            ], 422);
        }

        $validated = $request->validate([
            'contact_name' => 'sometimes|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'contact_address' => 'nullable|string',
            'prasadam_required' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $booking->update($validated);

        // Check if contact details are required
        $hasRecurring = $booking->items()->where('frequency', '!=', 'once')->exists();
        $contactRequired = $booking->balance_amount > 0 || $hasRecurring || $booking->prasadam_required;

        if ($contactRequired) {
            $reasons = [];
            if ($booking->balance_amount > 0) $reasons[] = 'pending payment';
            if ($hasRecurring) $reasons[] = 'recurring poojas';
            if ($booking->prasadam_required) $reasons[] = 'sending prasadam';

            if (empty($booking->contact_name) || empty($booking->contact_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact name and number are required for ' . implode(', ', $reasons),
                ], 422);
            }

            if ($booking->prasadam_required && empty($booking->contact_address)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery address is required for sending prasadam',
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'data' => new BookingResource($booking->fresh()->load('items.pooja', 'items.deity')),
        ]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        if ($booking->booking_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 422);
        }

        $reason = request('cancellation_reason', 'Cancelled by user');
        $this->bookingService->cancelBooking($booking, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ]);
    }

    public function addPayment(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->booking_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add payment to a cancelled booking',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,upi,bank_transfer,other',
            'account_id' => 'required|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $payment = $booking->addPayment(
            $validated['amount'],
            $validated['payment_method'],
            $validated['account_id'],
            $validated['reference_number'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment added successfully',
            'data' => [
                'payment' => new \App\Http\Resources\BookingPaymentResource($payment->load('receiver')),
                'booking' => new BookingResource($booking->fresh()),
            ],
        ]);
    }

    public function payments(Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\BookingPaymentResource::collection(
                $booking->payments()->with('receiver')->orderBy('payment_date', 'desc')->get()
            ),
        ]);
    }

    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'bookings' => Booking::where('temple_id', $templeId)
                        ->whereDate('booking_date', $today)->count(),
                    'amount' => Booking::where('temple_id', $templeId)
                        ->whereDate('booking_date', $today)->sum('total_amount'),
                    'collected' => Booking::where('temple_id', $templeId)
                        ->whereDate('booking_date', $today)->sum('paid_amount'),
                ],
                'month' => [
                    'bookings' => Booking::where('temple_id', $templeId)
                        ->where('booking_date', '>=', $thisMonth)->count(),
                    'amount' => Booking::where('temple_id', $templeId)
                        ->where('booking_date', '>=', $thisMonth)->sum('total_amount'),
                    'collected' => Booking::where('temple_id', $templeId)
                        ->where('booking_date', '>=', $thisMonth)->sum('paid_amount'),
                ],
                'outstanding' => [
                    'count' => Booking::where('temple_id', $templeId)
                        ->where('balance_amount', '>', 0)
                        ->where('booking_status', 'confirmed')->count(),
                    'amount' => Booking::where('temple_id', $templeId)
                        ->where('balance_amount', '>', 0)
                        ->where('booking_status', 'confirmed')->sum('balance_amount'),
                ],
            ],
        ]);
    }
}
