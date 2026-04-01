<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeePayment::query()
            ->with(['employee:id,employee_code,name,designation', 'account:id,account_name'])
            ->search($request->search);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->has('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        $payments = $query
            ->orderBy('payment_date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:bonus,advance,reimbursement,incentive,other',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,upi,bank_transfer,cheque',
            'account_id' => 'required|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();

        // Validate sufficient balance
        $account = \App\Models\Account::find($validated['account_id']);
        if ($account->current_balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($account->current_balance, 2),
            ], 422);
        }

        $payment = EmployeePayment::create($validated);

        // Create ledger entry for the payment
        $ledgerService = app(\App\Services\LedgerService::class);
        $employee = $payment->employee;
        $ledgerService->debit(
            $account,
            $validated['amount'],
            'employee_payment',
            $payment->id,
            ucfirst($validated['payment_type']) . " payment to {$employee->name}",
            $validated['payment_date']
        );

        $payment->load(['employee:id,employee_code,name', 'account:id,account_name']);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $payment,
        ], 201);
    }

    public function show(EmployeePayment $employeePayment): JsonResponse
    {
        $employeePayment->load(['employee', 'account', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $employeePayment,
        ]);
    }

    public function update(Request $request, EmployeePayment $employeePayment): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'sometimes|date',
            'payment_type' => 'sometimes|in:bonus,advance,reimbursement,incentive,other',
            'description' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $employeePayment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $employeePayment,
        ]);
    }

    public function destroy(EmployeePayment $employeePayment): JsonResponse
    {
        $employeePayment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;
        $thisMonth = now()->startOfMonth()->toDateString();

        $monthlyStats = EmployeePayment::where('temple_id', $templeId)
            ->where('payment_date', '>=', $thisMonth)
            ->selectRaw('payment_type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_type')
            ->get();

        $totalThisMonth = EmployeePayment::where('temple_id', $templeId)
            ->where('payment_date', '>=', $thisMonth)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'this_month' => [
                    'total' => $totalThisMonth,
                    'by_type' => $monthlyStats,
                ],
            ],
        ]);
    }
}
