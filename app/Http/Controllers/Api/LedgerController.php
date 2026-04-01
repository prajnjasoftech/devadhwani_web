<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(protected LedgerService $ledgerService)
    {
    }

    /**
     * Get paginated ledger entries with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = LedgerEntry::query()
            ->with(['account:id,account_name,account_type', 'creator:id,name'])
            ->search($request->search);

        if ($request->has('account_id')) {
            $query->forAccount($request->account_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        $query->inDateRange($request->date_from, $request->date_to);

        $entries = $query
            ->orderBy($request->sort_by ?? 'entry_date', $request->sort_order ?? 'desc')
            ->orderBy('id', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Get single ledger entry
     */
    public function show(LedgerEntry $ledgerEntry): JsonResponse
    {
        $ledgerEntry->load(['account', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $ledgerEntry,
        ]);
    }

    /**
     * Get account statement
     */
    public function statement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        $statement = $this->ledgerService->getAccountStatement(
            $validated['account_id'],
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $statement,
        ]);
    }

    /**
     * Get balance sheet
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        $balanceSheet = $this->ledgerService->getBalanceSheet(
            $templeId,
            $request->as_of_date
        );

        return response()->json([
            'success' => true,
            'data' => $balanceSheet,
        ]);
    }

    /**
     * Transfer money between accounts
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'narration' => 'required|string|max:500',
            'entry_date' => 'nullable|date',
        ]);

        $fromAccount = Account::findOrFail($validated['from_account_id']);
        $toAccount = Account::findOrFail($validated['to_account_id']);

        // Check sufficient balance
        if ($fromAccount->current_balance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient balance in {$fromAccount->display_name}. Available: " . number_format($fromAccount->current_balance, 2),
            ], 422);
        }

        $result = $this->ledgerService->transfer(
            $fromAccount,
            $toAccount,
            $validated['amount'],
            $validated['narration'],
            $validated['entry_date'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer completed successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Get ledger stats/summary
     */
    public function stats(): JsonResponse
    {
        $templeId = auth()->user()->temple_id;
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        // Today's activity
        $todayCredits = LedgerEntry::where('temple_id', $templeId)
            ->whereDate('entry_date', $today)
            ->where('type', 'credit')
            ->sum('amount');

        $todayDebits = LedgerEntry::where('temple_id', $templeId)
            ->whereDate('entry_date', $today)
            ->where('type', 'debit')
            ->sum('amount');

        // This month
        $monthCredits = LedgerEntry::where('temple_id', $templeId)
            ->where('entry_date', '>=', $thisMonth)
            ->where('type', 'credit')
            ->sum('amount');

        $monthDebits = LedgerEntry::where('temple_id', $templeId)
            ->where('entry_date', '>=', $thisMonth)
            ->where('type', 'debit')
            ->sum('amount');

        // Entry counts by source
        $sourceBreakdown = LedgerEntry::where('temple_id', $templeId)
            ->where('entry_date', '>=', $thisMonth)
            ->selectRaw('source_type, type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('source_type', 'type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'credits' => $todayCredits,
                    'debits' => $todayDebits,
                    'net' => $todayCredits - $todayDebits,
                ],
                'month' => [
                    'credits' => $monthCredits,
                    'debits' => $monthDebits,
                    'net' => $monthCredits - $monthDebits,
                ],
                'source_breakdown' => $sourceBreakdown,
            ],
        ]);
    }
}
