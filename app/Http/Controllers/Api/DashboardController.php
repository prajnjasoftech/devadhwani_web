<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingSchedule;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\Pooja;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Temple;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard stats (basic info)
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'temples' => [
                        'total' => Temple::count(),
                        'active' => Temple::where('status', 'active')->count(),
                        'inactive' => Temple::where('status', 'inactive')->count(),
                        'suspended' => Temple::where('status', 'suspended')->count(),
                    ],
                ],
            ]);
        }

        $templeId = $user->temple_id;

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => User::where('temple_id', $templeId)->count(),
                    'active' => User::where('temple_id', $templeId)->where('is_active', true)->count(),
                ],
                'roles' => [
                    'total' => Role::where('temple_id', $templeId)->count(),
                ],
            ],
        ]);
    }

    /**
     * Get financial summary for the month
     */
    public function summary(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;

        // Get date range (default: current month)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->startOfMonth();
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now()->endOfMonth();

        // Get ledger entries for the period
        $ledgerQuery = LedgerEntry::where('temple_id', $templeId)
            ->whereBetween('entry_date', [$startDate, $endDate]);

        $totalCredits = (clone $ledgerQuery)->credits()->sum('amount');
        $totalDebits = (clone $ledgerQuery)->debits()->sum('amount');

        // Breakdown by source type
        $creditsBySource = LedgerEntry::where('temple_id', $templeId)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->credits()
            ->select('source_type', DB::raw('SUM(amount) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        $debitsBySource = LedgerEntry::where('temple_id', $templeId)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->debits()
            ->select('source_type', DB::raw('SUM(amount) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        // Pending receivables (booking balance)
        $pendingReceivables = Booking::where('temple_id', $templeId)
            ->where('booking_status', '!=', 'cancelled')
            ->sum('balance_amount');

        // Monthly bookings count
        $bookingsCount = Booking::where('temple_id', $templeId)
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->count();

        // Monthly donations count
        $donationsCount = Donation::where('temple_id', $templeId)
            ->whereBetween('donation_date', [$startDate, $endDate])
            ->count();

        // Today's schedule count
        $todayScheduleCount = BookingSchedule::whereHas('bookingItem.booking', function ($q) use ($templeId) {
            $q->where('temple_id', $templeId);
        })
            ->where('scheduled_date', Carbon::today())
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'month_name' => $startDate->format('F Y'),
                ],
                'financial' => [
                    'total_income' => round($totalCredits, 2),
                    'total_income_formatted' => '₹' . number_format($totalCredits, 2),
                    'total_expense' => round($totalDebits, 2),
                    'total_expense_formatted' => '₹' . number_format($totalDebits, 2),
                    'net_balance' => round($totalCredits - $totalDebits, 2),
                    'net_balance_formatted' => '₹' . number_format($totalCredits - $totalDebits, 2),
                    'pending_receivables' => round($pendingReceivables, 2),
                    'pending_receivables_formatted' => '₹' . number_format($pendingReceivables, 2),
                ],
                'income_by_source' => [
                    'booking' => round($creditsBySource['booking'] ?? 0, 2),
                    'donation' => round($creditsBySource['donation'] ?? 0, 2),
                    'other' => round(($creditsBySource['transfer'] ?? 0) + ($creditsBySource['adjustment'] ?? 0), 2),
                ],
                'expense_by_source' => [
                    'purchase' => round($debitsBySource['purchase'] ?? 0, 2),
                    'expense' => round($debitsBySource['expense'] ?? 0, 2),
                    'salary' => round($debitsBySource['salary'] ?? 0, 2),
                    'employee_payment' => round($debitsBySource['employee_payment'] ?? 0, 2),
                    'other' => round(($debitsBySource['transfer'] ?? 0) + ($debitsBySource['adjustment'] ?? 0), 2),
                ],
                'counts' => [
                    'bookings' => $bookingsCount,
                    'donations' => $donationsCount,
                    'today_poojas' => $todayScheduleCount,
                ],
            ],
        ]);
    }

    /**
     * Get chart data for income/expense trends
     */
    public function charts(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;

        // Get date range (default: current month)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->startOfMonth();
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now()->endOfMonth();

        // Daily income/expense for the month
        $dailyData = LedgerEntry::where('temple_id', $templeId)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->select(
                'entry_date',
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('entry_date', 'type')
            ->orderBy('entry_date')
            ->get();

        // Process daily data
        $dailyIncome = [];
        $dailyExpense = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dailyIncome[$dateStr] = 0;
            $dailyExpense[$dateStr] = 0;
            $currentDate->addDay();
        }

        foreach ($dailyData as $entry) {
            $dateStr = $entry->entry_date->format('Y-m-d');
            if ($entry->type === 'credit') {
                $dailyIncome[$dateStr] = round($entry->total, 2);
            } else {
                $dailyExpense[$dateStr] = round($entry->total, 2);
            }
        }

        // Income by account
        $incomeByAccount = LedgerEntry::where('temple_id', $templeId)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->credits()
            ->join('accounts', 'ledger_entries.account_id', '=', 'accounts.id')
            ->select('accounts.account_name', 'accounts.account_type', DB::raw('SUM(ledger_entries.amount) as total'))
            ->groupBy('accounts.id', 'accounts.account_name', 'accounts.account_type')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->account_name,
                    'type' => $item->account_type,
                    'total' => round($item->total, 2),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'daily_trend' => [
                    'dates' => array_keys($dailyIncome),
                    'income' => array_values($dailyIncome),
                    'expense' => array_values($dailyExpense),
                ],
                'income_by_account' => $incomeByAccount,
            ],
        ]);
    }

    /**
     * Get pooja performance data
     */
    public function poojas(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;

        // Get date range (default: current month)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->startOfMonth();
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now()->endOfMonth();

        // Get all active poojas
        $poojas = Pooja::where('temple_id', $templeId)
            ->where('is_active', true)
            ->get();

        $poojaStats = [];

        foreach ($poojas as $pooja) {
            // Get booking items for this pooja in the period
            $bookingItems = BookingItem::where('pooja_id', $pooja->id)
                ->whereHas('booking', function ($q) use ($templeId, $startDate, $endDate) {
                    $q->where('temple_id', $templeId)
                        ->where('booking_status', '!=', 'cancelled')
                        ->whereBetween('booking_date', [$startDate, $endDate]);
                })
                ->get();

            $totalBookings = $bookingItems->count();
            $totalIncome = $bookingItems->sum('total_amount');

            // Calculate pending from booking balance
            $pendingAmount = $bookingItems->sum(function ($item) {
                $booking = $item->booking;
                if ($booking->total_amount > 0) {
                    // Proportional pending based on item's share of total
                    $proportion = $item->total_amount / $booking->total_amount;
                    return $booking->balance_amount * $proportion;
                }
                return 0;
            });

            // Only include poojas that have bookings
            if ($totalBookings > 0) {
                $poojaStats[] = [
                    'id' => $pooja->id,
                    'name' => $pooja->name,
                    'total_bookings' => $totalBookings,
                    'total_income' => round($totalIncome, 2),
                    'total_income_formatted' => '₹' . number_format($totalIncome, 2),
                    'pending_amount' => round($pendingAmount, 2),
                    'pending_amount_formatted' => '₹' . number_format($pendingAmount, 2),
                ];
            }
        }

        // Sort by total income descending
        usort($poojaStats, fn($a, $b) => $b['total_income'] <=> $a['total_income']);

        return response()->json([
            'success' => true,
            'data' => $poojaStats,
        ]);
    }

    /**
     * Get today's schedule
     */
    public function today(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;
        $today = Carbon::today();

        $schedules = BookingSchedule::with([
            'bookingItem.pooja:id,name',
            'bookingItem.deity:id,name',
            'bookingItem.beneficiaries:id,booking_item_id,name',
            'bookingItem.booking:id,booking_number,contact_name',
        ])
            ->whereHas('bookingItem.booking', function ($q) use ($templeId) {
                $q->where('temple_id', $templeId)
                    ->where('booking_status', '!=', 'cancelled');
            })
            ->where('scheduled_date', $today)
            ->orderBy('status')
            ->get();

        // Group by pooja
        $grouped = $schedules->groupBy(function ($schedule) {
            return $schedule->bookingItem->pooja_id . '_' . ($schedule->bookingItem->deity_id ?? 0);
        });

        $todaySchedule = [];
        foreach ($grouped as $key => $items) {
            $first = $items->first();
            $pendingCount = $items->where('status', 'pending')->count();
            $completedCount = $items->where('status', 'completed')->count();

            $todaySchedule[] = [
                'pooja_name' => $first->bookingItem->pooja->name ?? 'Unknown',
                'deity_name' => $first->bookingItem->deity->name ?? 'General',
                'total_count' => $items->count(),
                'pending_count' => $pendingCount,
                'completed_count' => $completedCount,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $todaySchedule,
        ]);
    }

    /**
     * Get recent bookings
     */
    public function recentBookings(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Dashboard not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;

        $bookings = Booking::where('temple_id', $templeId)
            ->with(['items.pooja:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'contact_name' => $booking->contact_name,
                    'total_amount' => $booking->total_amount,
                    'total_amount_formatted' => '₹' . number_format($booking->total_amount, 2),
                    'balance_amount' => $booking->balance_amount,
                    'payment_status' => $booking->payment_status,
                    'booking_date' => $booking->booking_date->format('d M Y'),
                    'poojas' => $booking->items->pluck('pooja.name')->filter()->unique()->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }
}
