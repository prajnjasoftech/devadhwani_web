<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()
            ->with(['category:id,name', 'creator:id,name', 'account:id,account_name'])
            ->search($request->search);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->where('expense_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        $expenses = $query
            ->orderBy($request->sort_by ?? 'expense_date', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'account_id' => 'nullable|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',
            'paid_to' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();

        // Set payment status based on paid amount
        if (!isset($validated['payment_status'])) {
            if (($validated['paid_amount'] ?? 0) >= $validated['amount']) {
                $validated['payment_status'] = 'paid';
                $validated['paid_amount'] = $validated['amount'];
            } elseif (($validated['paid_amount'] ?? 0) > 0) {
                $validated['payment_status'] = 'partial';
            } else {
                $validated['payment_status'] = 'pending';
                $validated['paid_amount'] = 0;
            }
        }

        // Validate sufficient balance before creating expense
        if (($validated['paid_amount'] ?? 0) > 0 && !empty($validated['account_id'])) {
            $account = \App\Models\Account::find($validated['account_id']);
            if ($account->current_balance < $validated['paid_amount']) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($account->current_balance, 2),
                ], 422);
            }
        }

        $expense = Expense::create($validated);

        // Create ledger entry if payment was made
        if (($validated['paid_amount'] ?? 0) > 0 && !empty($validated['account_id'])) {
            $account = \App\Models\Account::find($validated['account_id']);
            $ledgerService = app(\App\Services\LedgerService::class);
            $ledgerService->debit(
                $account,
                $validated['paid_amount'],
                'expense',
                $expense->id,
                "Expense payment - {$expense->expense_number} - {$expense->description}",
                $validated['expense_date']
            );
        }

        $expense->load(['category:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully',
            'data' => $expense,
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['category', 'creator:id,name', 'account:id,account_name,account_type']);

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => 'sometimes|date',
            'category_id' => 'sometimes|exists:expense_categories,id',
            'description' => 'sometimes|string|max:500',
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'account_id' => 'nullable|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',
            'paid_to' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);
        $expense->load(['category:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense,
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }

    public function addPayment(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,upi,card,bank_transfer,cheque,other',
            'account_id' => 'required|exists:accounts,id',
        ]);

        // Validate sufficient balance
        $account = \App\Models\Account::find($validated['account_id']);
        if ($account->current_balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($account->current_balance, 2),
            ], 422);
        }

        // Create ledger entry for the payment
        $ledgerService = app(\App\Services\LedgerService::class);
        $ledgerService->debit(
            $account,
            $validated['amount'],
            'expense',
            $expense->id,
            "Expense payment - {$expense->expense_number}",
            now()->toDateString()
        );

        $expense->addPayment($validated['amount'], $validated['payment_method']);
        $expense->update(['account_id' => $validated['account_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Payment added successfully',
            'data' => $expense->fresh(['category:id,name']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'count' => Expense::where('temple_id', $templeId)
                        ->whereDate('expense_date', $today)->count(),
                    'total' => Expense::where('temple_id', $templeId)
                        ->whereDate('expense_date', $today)->sum('amount'),
                ],
                'month' => [
                    'count' => Expense::where('temple_id', $templeId)
                        ->where('expense_date', '>=', $thisMonth)->count(),
                    'total' => Expense::where('temple_id', $templeId)
                        ->where('expense_date', '>=', $thisMonth)->sum('amount'),
                    'paid' => Expense::where('temple_id', $templeId)
                        ->where('expense_date', '>=', $thisMonth)->sum('paid_amount'),
                ],
                'pending' => [
                    'count' => Expense::where('temple_id', $templeId)
                        ->whereIn('payment_status', ['pending', 'partial'])->count(),
                    'amount' => Expense::where('temple_id', $templeId)
                        ->whereIn('payment_status', ['pending', 'partial'])
                        ->selectRaw('SUM(amount - paid_amount) as pending')
                        ->value('pending') ?? 0,
                ],
            ],
        ]);
    }
}
