<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Devotee;
use App\Models\Pooja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    /**
     * Create a new booking with items and beneficiaries
     *
     * @param array $data
     * @return Booking
     */
    public function createBooking(array $data): Booking
    {
        Log::info('BookingService: Creating booking', ['items_count' => count($data['items'] ?? []), 'data_keys' => array_keys($data)]);

        return DB::transaction(function () use ($data) {
            Log::info('BookingService: Inside transaction, creating booking record');
            // Create the booking
            $booking = Booking::create([
                'temple_id' => auth()->user()->temple_id,
                'booking_date' => $data['booking_date'] ?? now()->toDateString(),
                'contact_name' => $data['contact_name'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_address' => $data['contact_address'] ?? null,
                'prasadam_required' => $data['prasadam_required'] ?? false,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            Log::info('BookingService: Booking created', ['booking_id' => $booking->id, 'booking_number' => $booking->booking_number]);

            // Create items with beneficiaries
            Log::info('BookingService: Creating items', ['booking_id' => $booking->id, 'items_count' => count($data['items'] ?? [])]);
            foreach ($data['items'] as $index => $itemData) {
                Log::info('BookingService: Creating item', ['index' => $index, 'pooja_id' => $itemData['pooja_id'] ?? null]);
                $item = $this->createBookingItem($booking, $itemData);
                Log::info('BookingService: Item created', ['item_id' => $item->id, 'total' => $item->total_amount]);
            }

            // Process initial payment if provided
            if (!empty($data['payment_amount']) && $data['payment_amount'] > 0) {
                $booking->addPayment(
                    $data['payment_amount'],
                    $data['payment_method'] ?? 'cash',
                    $data['account_id'] ?? null,
                    $data['payment_reference'] ?? null,
                    $data['payment_notes'] ?? null
                );
            } else {
                $booking->recalculateTotals();
            }

            // Check if any item is recurring
            $hasRecurring = $booking->items()->where('frequency', '!=', 'once')->exists();

            // Validate: contact details required if:
            // 1. Not fully paid, 2. Has recurring poojas, 3. Prasadam delivery
            $contactRequired = $booking->balance_amount > 0 || $hasRecurring || $booking->prasadam_required;

            if ($contactRequired) {
                if (empty($booking->contact_name) || empty($booking->contact_number)) {
                    $reasons = [];
                    if ($booking->balance_amount > 0) $reasons[] = 'pending payment';
                    if ($hasRecurring) $reasons[] = 'recurring poojas';
                    if ($booking->prasadam_required) $reasons[] = 'sending prasadam';
                    throw new \Exception('Contact name and number are required for ' . implode(', ', $reasons));
                }

                // Address required for sending prasadam
                if ($booking->prasadam_required && empty($booking->contact_address)) {
                    throw new \Exception('Delivery address is required for sending prasadam');
                }
            }

            return $booking->load('items.beneficiaries', 'items.pooja', 'items.deity', 'payments');
        });
    }

    /**
     * Create a booking item with beneficiaries and generate schedules
     */
    public function createBookingItem(Booking $booking, array $data): BookingItem
    {
        Log::info('createBookingItem: Start', ['pooja_id' => $data['pooja_id'], 'beneficiaries' => count($data['beneficiaries'] ?? []), 'quantity' => $data['quantity'] ?? null]);

        $pooja = Pooja::findOrFail($data['pooja_id']);
        Log::info('createBookingItem: Pooja found', ['name' => $pooja->name, 'amount' => $pooja->amount, 'devotee_required' => $pooja->devotee_required]);

        // Validate: if pooja requires devotee, beneficiaries must be provided
        if ($pooja->devotee_required && empty($data['beneficiaries'])) {
            Log::error('createBookingItem: Beneficiary required but not provided');
            throw new \Exception("Beneficiary details are required for {$pooja->name}");
        }

        // Get quantity - either from explicit quantity field or will be calculated from beneficiaries
        $quantity = !$pooja->devotee_required ? ($data['quantity'] ?? 1) : 1;

        Log::info('createBookingItem: Creating item record', ['quantity' => $quantity]);
        $item = BookingItem::create([
            'booking_id' => $booking->id,
            'pooja_id' => $data['pooja_id'],
            'deity_id' => $data['deity_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'frequency' => $data['frequency'] ?? 'once',
            'monthly_type' => $data['monthly_type'] ?? null,
            'monthly_day' => $data['monthly_day'] ?? null,
            'unit_amount' => $data['unit_amount'] ?? $pooja->amount,
            'notes' => $data['notes'] ?? null,
            // Initial values - beneficiary_count set to quantity for poojas without devotee requirement
            'beneficiary_count' => $quantity,
            'occurrence_count' => 1,
            'total_amount' => ($data['unit_amount'] ?? $pooja->amount) * $quantity,
        ]);
        Log::info('createBookingItem: Item created', ['item_id' => $item->id]);

        // Add beneficiaries
        if (!empty($data['beneficiaries'])) {
            Log::info('createBookingItem: Processing beneficiaries', ['count' => count($data['beneficiaries'])]);
            $templeId = auth()->user()->temple_id;

            foreach ($data['beneficiaries'] as $beneficiaryData) {
                // Skip empty beneficiary names
                $name = trim($beneficiaryData['name'] ?? '');
                if (empty($name)) {
                    continue;
                }

                // Normalize values - convert empty strings and "0" to null for IDs
                $nakshathraId = !empty($beneficiaryData['nakshathra_id']) ? (int) $beneficiaryData['nakshathra_id'] : null;
                $gothram = !empty($beneficiaryData['gothram']) ? trim($beneficiaryData['gothram']) : null;

                // Find existing devotee with same details
                $devotee = Devotee::where('temple_id', $templeId)
                    ->where('name', $name)
                    ->where(function ($query) use ($nakshathraId) {
                        if ($nakshathraId) {
                            $query->where('nakshathra_id', $nakshathraId);
                        } else {
                            $query->whereNull('nakshathra_id');
                        }
                    })
                    ->where(function ($query) use ($gothram) {
                        if ($gothram) {
                            $query->where('gothram', $gothram);
                        } else {
                            $query->whereNull('gothram');
                        }
                    })
                    ->first();

                // Create devotee if not found
                if (!$devotee) {
                    $devotee = Devotee::create([
                        'temple_id' => $templeId,
                        'name' => $name,
                        'nakshathra_id' => $nakshathraId,
                        'gothram' => $gothram,
                    ]);
                }

                // Create beneficiary with link to devotee
                $item->beneficiaries()->create([
                    'devotee_id' => $devotee->id,
                    'name' => $name,
                    'nakshathra_id' => $nakshathraId,
                    'gothram' => $gothram,
                    'notes' => $beneficiaryData['notes'] ?? null,
                ]);
            }
        }

        // Generate schedules based on frequency
        Log::info('createBookingItem: Generating schedules');
        $item->generateSchedules();
        Log::info('createBookingItem: Schedules generated, returning item', ['total_amount' => $item->total_amount]);

        return $item;
    }

    /**
     * Add more items to an existing booking
     */
    public function addItem(Booking $booking, array $data): BookingItem
    {
        return DB::transaction(function () use ($booking, $data) {
            $item = $this->createBookingItem($booking, $data);
            $booking->recalculateTotals();

            // Re-validate contact number requirement
            if ($booking->balance_amount > 0 && empty($booking->contact_number)) {
                throw new \Exception('Contact number is required for bookings with pending payment');
            }

            return $item;
        });
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(Booking $booking, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
            $booking->cancel($reason);
            return $booking->fresh();
        });
    }

    /**
     * Get daily schedule for a temple
     */
    public function getDailySchedule(int $templeId, string $date): array
    {
        $schedules = \App\Models\BookingSchedule::with([
            'bookingItem.beneficiaries.nakshathra',
            'bookingItem.pooja',
            'bookingItem.deity',
            'bookingItem.booking',
        ])
            ->forTemple($templeId)
            ->forDate($date)
            ->orderBy('status')
            ->get()
            // Filter out schedules with missing bookingItem or pooja
            ->filter(fn ($schedule) => $schedule->bookingItem && $schedule->bookingItem->pooja);

        // Group by pooja and deity
        $grouped = $schedules->groupBy(function ($schedule) {
            return $schedule->bookingItem->pooja_id . '_' . ($schedule->bookingItem->deity_id ?? 0);
        });

        $result = [];
        foreach ($grouped as $key => $items) {
            $first = $items->first();
            $pendingCount = $items->where('status', 'pending')->count();
            $completedCount = $items->where('status', 'completed')->count();

            $result[] = [
                'pooja_id' => $first->bookingItem->pooja->id,
                'pooja_name' => $first->bookingItem->pooja->name,
                'deity_id' => $first->bookingItem->deity?->id,
                'deity_name' => $first->bookingItem->deity?->name ?? 'General',
                'total_count' => $items->count(),
                'pending_count' => $pendingCount,
                'completed_count' => $completedCount,
                'schedules' => $items->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'status' => $schedule->status,
                        'booking_number' => $schedule->bookingItem->booking->booking_number,
                        'contact_name' => $schedule->bookingItem->booking->contact_name,
                        'completed_at' => $schedule->completed_at?->toISOString(),
                        'completed_at_formatted' => $schedule->completed_at?->format('h:i A'),
                        'beneficiaries' => $schedule->bookingItem->beneficiaries->pluck('name')->toArray(),
                    ];
                })->values(),
            ];
        }

        return $result;
    }

    /**
     * Batch complete schedules
     */
    public function batchCompleteSchedules(array $scheduleIds): int
    {
        return \App\Models\BookingSchedule::whereIn('id', $scheduleIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);
    }
}
