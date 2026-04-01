<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProkeralaService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use ApiResponse;

    protected ProkeralaService $prokeralaService;

    public function __construct(ProkeralaService $prokeralaService)
    {
        $this->prokeralaService = $prokeralaService;
    }

    /**
     * Get Panchang and Malayalam calendar details for a specific date
     */
    public function getPanchang(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $latitude = (float) ($request->latitude ?? 10.5276);
            $longitude = (float) ($request->longitude ?? 76.2144);

            $data = $this->prokeralaService->getDayDetails(
                $request->date,
                $latitude,
                $longitude
            );

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch Panchang data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get calendar overview for a month with today's panchang
     */
    public function getMonthData(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->month;
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $monthData = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $monthData[$day] = [
                'date' => $date,
                'malayalam_date' => $this->prokeralaService->getApproximateMalayalamDate($date),
            ];
        }

        // Get today's panchang if viewing current month
        $today = date('Y-m-d');
        $todayPanchang = null;

        if ((int) date('Y') === $year && (int) date('n') === $month) {
            try {
                $latitude = (float) ($request->latitude ?? 10.5276);
                $longitude = (float) ($request->longitude ?? 76.2144);
                $todayPanchang = $this->prokeralaService->getDayDetails($today, $latitude, $longitude);
            } catch (\Exception $e) {
                // Silently fail - today's panchang is optional
            }
        }

        return $this->success([
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'days' => $monthData,
            'today' => $today,
            'today_panchang' => $todayPanchang,
        ]);
    }
}
