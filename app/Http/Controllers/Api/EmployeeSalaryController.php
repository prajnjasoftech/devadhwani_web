<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSalary::query()
            ->with(['employee:id,employee_code,name,designation', 'account:id,account_name']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Handle month in "YYYY-MM" format
        if ($request->has('month') && str_contains($request->month, '-')) {
            [$year, $month] = explode('-', $request->month);
            $query->where('year', (int) $year)->where('month', (int) $month);
        } else {
            if ($request->has('year')) {
                $query->where('year', $request->year);
            }
            if ($request->has('month')) {
                $query->where('month', $request->month);
            }
        }

        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }

        $salaries = $query
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $salaries->items(),
            'meta' => [
                'current_page' => $salaries->currentPage(),
                'last_page' => $salaries->lastPage(),
                'per_page' => $salaries->perPage(),
                'total' => $salaries->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Check if salary already exists for this month
        $exists = EmployeeSalary::where('employee_id', $validated['employee_id'])
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Salary entry already exists for this employee for the selected month',
            ], 422);
        }

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();
        $validated['allowances'] = $validated['allowances'] ?? 0;
        $validated['deductions'] = $validated['deductions'] ?? 0;

        $salary = EmployeeSalary::create($validated);
        $salary->load(['employee:id,employee_code,name']);

        return response()->json([
            'success' => true,
            'message' => 'Salary entry created successfully',
            'data' => $salary,
        ], 201);
    }

    public function show(EmployeeSalary $employeeSalary): JsonResponse
    {
        $employeeSalary->load(['employee', 'account', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $employeeSalary,
        ]);
    }

    public function update(Request $request, EmployeeSalary $employeeSalary): JsonResponse
    {
        $validated = $request->validate([
            'basic_salary' => 'sometimes|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $employeeSalary->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Salary entry updated successfully',
            'data' => $employeeSalary,
        ]);
    }

    public function destroy(EmployeeSalary $employeeSalary): JsonResponse
    {
        if ($employeeSalary->paid_amount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete salary with payment records',
            ], 422);
        }

        $employeeSalary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Salary entry deleted successfully',
        ]);
    }

    public function pay(Request $request, EmployeeSalary $employeeSalary): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,upi,bank_transfer,cheque',
            'account_id' => 'required|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($employeeSalary->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Salary already paid',
            ], 422);
        }

        // Validate sufficient balance
        $account = \App\Models\Account::findOrFail($validated['account_id']);
        if ($account->current_balance < $employeeSalary->net_salary) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($account->current_balance, 2) . ", Required: ₹" . number_format($employeeSalary->net_salary, 2),
            ], 422);
        }

        // Create ledger entry for salary payment
        $ledgerService = app(\App\Services\LedgerService::class);
        $ledgerService->debit(
            $account,
            $employeeSalary->net_salary,
            'salary',
            $employeeSalary->id,
            "Salary payment to {$employeeSalary->employee->name} for {$employeeSalary->month}/{$employeeSalary->year}",
            $validated['payment_date']
        );

        $employeeSalary->update([
            'payment_status' => 'paid',
            'paid_amount' => $employeeSalary->net_salary,
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'account_id' => $validated['account_id'],
            'reference_number' => $validated['reference_number'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salary paid successfully',
            'data' => $employeeSalary->fresh(['employee:id,employee_code,name', 'account:id,account_name']),
        ]);
    }

    public function generateMonthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        // Parse YYYY-MM format
        [$year, $month] = explode('-', $validated['month']);
        $year = (int) $year;
        $month = (int) $month;

        $templeId = auth()->user()->temple_id;

        // Get all active employees
        $employees = Employee::where('temple_id', $templeId)
            ->where('is_active', true)
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active employees found',
            ], 422);
        }

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            // Check if salary already exists
            $exists = EmployeeSalary::where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('month', $month)
                ->exists();

            if (!$exists) {
                EmployeeSalary::create([
                    'temple_id' => $templeId,
                    'employee_id' => $employee->id,
                    'year' => $year,
                    'month' => $month,
                    'basic_salary' => $employee->basic_salary,
                    'allowances' => 0,
                    'deductions' => 0,
                    'created_by' => auth()->id(),
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Generated {$created} salary entries. Skipped {$skipped} (already exist).",
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;
        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Current month stats
        $currentMonthStats = EmployeeSalary::where('temple_id', $templeId)
            ->where('year', $currentYear)
            ->where('month', $currentMonth)
            ->selectRaw('
                COUNT(*) as total,
                SUM(net_salary) as total_salary,
                SUM(paid_amount) as total_paid,
                SUM(CASE WHEN payment_status = "pending" THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as paid_count
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => $currentYear,
                    'month' => $currentMonth,
                    'total_entries' => $currentMonthStats->total ?? 0,
                    'total_salary' => $currentMonthStats->total_salary ?? 0,
                    'total_paid' => $currentMonthStats->total_paid ?? 0,
                    'pending_count' => $currentMonthStats->pending_count ?? 0,
                    'paid_count' => $currentMonthStats->paid_count ?? 0,
                ],
            ],
        ]);
    }
}
