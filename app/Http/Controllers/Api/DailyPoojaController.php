<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingSchedule;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyPoojaController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    /**
     * Get daily pooja schedule grouped by pooja and deity
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());
        $templeId = auth()->user()->temple_id;

        $grouped = $this->bookingService->getDailySchedule($templeId, $date);

        // Get summary counts
        $totalPending = BookingSchedule::forTemple($templeId)
            ->forDate($date)
            ->pending()
            ->count();

        $totalCompleted = BookingSchedule::forTemple($templeId)
            ->forDate($date)
            ->completed()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'summary' => [
                    'pending' => $totalPending,
                    'completed' => $totalCompleted,
                    'total' => $totalPending + $totalCompleted,
                ],
                'poojas' => $grouped,
            ],
        ]);
    }

    /**
     * Mark a single schedule as completed
     */
    public function complete(BookingSchedule $schedule): JsonResponse
    {
        if ($schedule->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Schedule is not pending',
            ], 422);
        }

        $schedule->markCompleted();

        return response()->json([
            'success' => true,
            'message' => 'Pooja marked as completed',
        ]);
    }

    /**
     * Batch complete multiple schedules
     */
    public function batchComplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'exists:booking_schedules,id',
        ]);

        $count = $this->bookingService->batchCompleteSchedules($validated['schedule_ids']);

        return response()->json([
            'success' => true,
            'message' => "{$count} poojas marked as completed",
            'completed_count' => $count,
        ]);
    }

    /**
     * Get upcoming schedules for a booking item
     */
    public function upcoming(Request $request): JsonResponse
    {
        $templeId = auth()->user()->temple_id;
        $days = $request->get('days', 7);

        $schedules = BookingSchedule::with([
            'bookingItem.pooja',
            'bookingItem.deity',
            'bookingItem.beneficiaries',
            'bookingItem.booking',
        ])
            ->forTemple($templeId)
            ->pending()
            ->where('scheduled_date', '>=', now()->toDateString())
            ->where('scheduled_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('scheduled_date')
            ->get()
            ->groupBy(function ($schedule) {
                return $schedule->scheduled_date->format('Y-m-d');
            });

        $result = [];
        foreach ($schedules as $date => $items) {
            $result[] = [
                'date' => $date,
                'date_formatted' => \Carbon\Carbon::parse($date)->format('d M Y (l)'),
                'count' => $items->count(),
                'schedules' => $items->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'pooja' => $schedule->bookingItem->pooja->name,
                        'deity' => $schedule->bookingItem->deity?->name ?? 'General',
                        'booking_number' => $schedule->bookingItem->booking->booking_number,
                        'beneficiaries' => $schedule->bookingItem->beneficiaries->pluck('name'),
                    ];
                }),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
