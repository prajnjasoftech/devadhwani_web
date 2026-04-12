<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Donation;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get daily report with all income and expenses
     */
    public function daily(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Reports not available for platform admin',
            ], 403);
        }

        $templeId = $user->temple_id;

        // Get date range (default: today)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today();
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::today()->endOfDay();

        // INCOME SECTION

        // Bookings - group by pooja type (filter by booking_date)
        $bookingsQuery = Booking::where('temple_id', $templeId)
            ->whereBetween('booking_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('booking_status', '!=', 'cancelled')
            ->with(['items.pooja:id,name'])
            ->get();

        // Flatten all booking items and group by pooja
        $allItems = $bookingsQuery->flatMap(function ($booking) {
            return $booking->items->map(function ($item) use ($booking) {
                // Use quantity for quantity-based poojas, otherwise use beneficiary_count
                $qty = ($item->quantity && $item->quantity > 1) ? $item->quantity : ($item->beneficiary_count ?? 1);
                return [
                    'pooja_id' => $item->pooja_id,
                    'pooja_name' => $item->pooja->name ?? 'Unknown',
                    'quantity' => $qty,
                    'amount' => $item->total_amount,
                    'booking_id' => $booking->id,
                ];
            });
        });

        $bookings = $allItems->groupBy('pooja_name')->map(function ($items, $poojaName) {
            $bookingIds = $items->pluck('booking_id')->unique();
            return [
                'pooja_name' => $poojaName,
                'quantity' => $items->sum('quantity'),
                'total_amount' => round($items->sum('amount'), 2),
                'bookings_count' => $bookingIds->count(),
            ];
        })->sortByDesc('total_amount')->values()->toArray();

        // Donations (filter by donation_date)
        $donations = Donation::where('temple_id', $templeId)
            ->whereBetween('donation_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['donationHead:id,name', 'assetType:id,name'])
            ->orderBy('donation_date')
            ->get()
            ->map(function ($donation) {
                return [
                    'id' => $donation->id,
                    'donation_number' => $donation->donation_number,
                    'donation_date' => $donation->donation_date->format('d M Y'),
                    'donor_name' => $donation->donor_name,
                    'donor_contact' => $donation->donor_contact,
                    'donation_type' => $donation->donation_type,
                    'head_name' => $donation->donationHead->name ?? null,
                    'asset_type' => $donation->assetType->name ?? null,
                    'amount' => round($donation->amount ?? 0, 2),
                    'estimated_value' => round($donation->estimated_value ?? 0, 2),
                    'payment_method' => $donation->payment_method,
                ];
            });

        // EXPENSE SECTION

        // Purchases (filter by purchase_date)
        $purchases = Purchase::where('temple_id', $templeId)
            ->whereBetween('purchase_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['vendor:id,name', 'category:id,name', 'purpose:id,name'])
            ->orderBy('purchase_date')
            ->get()
            ->map(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'purchase_number' => $purchase->purchase_number,
                    'purchase_date' => $purchase->purchase_date->format('d M Y'),
                    'vendor_name' => $purchase->vendor->name ?? 'N/A',
                    'category' => $purchase->category->name ?? 'N/A',
                    'purpose' => $purchase->purpose->name ?? 'N/A',
                    'description' => $purchase->description,
                    'total_amount' => round($purchase->total_amount, 2),
                    'paid_amount' => round($purchase->paid_amount, 2),
                    'balance_amount' => round($purchase->balance_amount, 2),
                    'payment_status' => $purchase->payment_status,
                ];
            });

        // Expenses (filter by expense_date)
        $expenses = Expense::where('temple_id', $templeId)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['category:id,name'])
            ->orderBy('expense_date')
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'expense_number' => $expense->expense_number,
                    'expense_date' => $expense->expense_date->format('d M Y'),
                    'category' => $expense->category->name ?? 'N/A',
                    'description' => $expense->description,
                    'total_amount' => round($expense->total_amount, 2),
                    'paid_amount' => round($expense->paid_amount, 2),
                    'balance_amount' => round($expense->balance_amount, 2),
                    'payment_status' => $expense->payment_status,
                ];
            });

        // Employee Salaries (filter by payment_date)
        $salaries = EmployeeSalary::where('temple_id', $templeId)
            ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('payment_status', 'paid')
            ->with(['employee:id,name,employee_code'])
            ->orderBy('payment_date')
            ->get()
            ->map(function ($salary) {
                $grossSalary = ($salary->basic_salary ?? 0) + ($salary->allowances ?? 0);
                return [
                    'id' => $salary->id,
                    'employee_name' => $salary->employee->name ?? 'N/A',
                    'employee_code' => $salary->employee->employee_code ?? 'N/A',
                    'month_year' => $salary->month . '/' . $salary->year,
                    'payment_date' => $salary->payment_date?->format('d M Y'),
                    'gross_salary' => round($grossSalary, 2),
                    'deductions' => round($salary->deductions ?? 0, 2),
                    'net_salary' => round($salary->net_salary ?? 0, 2),
                ];
            });

        // Employee Other Payments (filter by payment_date)
        $employeePayments = EmployeePayment::where('temple_id', $templeId)
            ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['employee:id,name,employee_code'])
            ->orderBy('payment_date')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'employee_name' => $payment->employee->name ?? 'N/A',
                    'employee_code' => $payment->employee->employee_code ?? 'N/A',
                    'payment_type' => $payment->payment_type,
                    'payment_date' => $payment->payment_date->format('d M Y'),
                    'amount' => round($payment->amount, 2),
                    'description' => $payment->description,
                ];
            });

        // Calculate totals
        $totalBookingAmount = array_sum(array_column($bookings, 'total_amount'));
        $totalBookingPaid = $bookingsQuery->sum('paid_amount');
        $totalDonationAmount = $donations->where('donation_type', 'financial')->sum('amount');
        $totalDonationAssetValue = $donations->where('donation_type', 'asset')->sum('estimated_value');

        $totalPurchaseAmount = $purchases->sum('total_amount');
        $totalPurchasePaid = $purchases->sum('paid_amount');
        $totalExpenseAmount = $expenses->sum('total_amount');
        $totalExpensePaid = $expenses->sum('paid_amount');
        $totalSalaryPaid = $salaries->sum('net_salary');
        $totalEmployeePayments = $employeePayments->sum('amount');

        $totalIncome = $totalBookingPaid + $totalDonationAmount;
        $totalExpenses = $totalPurchasePaid + $totalExpensePaid + $totalSalaryPaid + $totalEmployeePayments;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'display' => $startDate->format('d M Y') . ($startDate->isSameDay($endDate) ? '' : ' - ' . $endDate->format('d M Y')),
                ],
                'income' => [
                    'bookings' => [
                        'data' => $bookings,
                        'count' => count($bookings),
                        'total_amount' => round($totalBookingAmount, 2),
                        'total_paid' => round($totalBookingPaid, 2),
                    ],
                    'donations' => [
                        'data' => $donations->values()->toArray(),
                        'count' => $donations->count(),
                        'financial_count' => $donations->where('donation_type', 'financial')->count(),
                        'asset_count' => $donations->where('donation_type', 'asset')->count(),
                        'total_amount' => round($totalDonationAmount, 2),
                        'total_asset_value' => round($totalDonationAssetValue, 2),
                    ],
                    'total' => round($totalIncome, 2),
                    'total_formatted' => '₹' . number_format($totalIncome, 2),
                ],
                'expenses' => [
                    'purchases' => [
                        'data' => $purchases->values()->toArray(),
                        'count' => $purchases->count(),
                        'total_amount' => round($totalPurchaseAmount, 2),
                        'total_paid' => round($totalPurchasePaid, 2),
                    ],
                    'expenses' => [
                        'data' => $expenses->values()->toArray(),
                        'count' => $expenses->count(),
                        'total_amount' => round($totalExpenseAmount, 2),
                        'total_paid' => round($totalExpensePaid, 2),
                    ],
                    'salaries' => [
                        'data' => $salaries->values()->toArray(),
                        'count' => $salaries->count(),
                        'total_paid' => round($totalSalaryPaid, 2),
                    ],
                    'employee_payments' => [
                        'data' => $employeePayments->values()->toArray(),
                        'count' => $employeePayments->count(),
                        'total_paid' => round($totalEmployeePayments, 2),
                    ],
                    'total' => round($totalExpenses, 2),
                    'total_formatted' => '₹' . number_format($totalExpenses, 2),
                ],
                'summary' => [
                    'total_income' => round($totalIncome, 2),
                    'total_income_formatted' => '₹' . number_format($totalIncome, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'total_expenses_formatted' => '₹' . number_format($totalExpenses, 2),
                    'net_balance' => round($totalIncome - $totalExpenses, 2),
                    'net_balance_formatted' => '₹' . number_format($totalIncome - $totalExpenses, 2),
                ],
            ],
        ]);
    }
}
