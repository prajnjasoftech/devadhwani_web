<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Temple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * Get all accounts for the temple
     */
    public function index(): JsonResponse
    {
        $accounts = Account::orderBy('account_type')
            ->orderBy('account_name')
            ->get();

        $temple = Temple::find(auth()->user()->temple_id);

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'setup_completed' => $temple->accounts_setup_completed ?? false,
            ],
        ]);
    }

    /**
     * Get all active accounts for dropdowns
     */
    public function all(): JsonResponse
    {
        $accounts = Account::active()
            ->orderBy('account_type')
            ->orderBy('account_name')
            ->get(['id', 'account_type', 'account_name', 'is_upi_account', 'is_card_account', 'current_balance']);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Get account balances summary
     */
    public function balances(): JsonResponse
    {
        $accounts = Account::active()->get();

        $cashBalance = $accounts->where('account_type', 'cash')->sum('current_balance');
        $bankBalance = $accounts->where('account_type', 'bank')->sum('current_balance');
        $totalBalance = $cashBalance + $bankBalance;

        return response()->json([
            'success' => true,
            'data' => [
                'cash' => $cashBalance,
                'bank' => $bankBalance,
                'total' => $totalBalance,
                'accounts' => $accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->display_name,
                        'type' => $account->account_type,
                        'balance' => $account->current_balance,
                        'is_upi' => $account->is_upi_account,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Initial setup of accounts (one-time)
     */
    public function setup(Request $request): JsonResponse
    {
        $temple = Temple::find(auth()->user()->temple_id);

        // Check if setup already completed
        if ($temple->accounts_setup_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Accounts setup has already been completed. Please contact support for changes.',
            ], 422);
        }

        $validated = $request->validate([
            'cash_opening_balance' => 'required|numeric|min:0',
            'bank_accounts' => 'required|array|min:1',
            'bank_accounts.*.account_name' => 'required|string|max:255',
            'bank_accounts.*.bank_name' => 'required|string|max:255',
            'bank_accounts.*.account_number' => 'required|string|max:50',
            'bank_accounts.*.ifsc_code' => 'nullable|string|max:20',
            'bank_accounts.*.branch' => 'nullable|string|max:255',
            'bank_accounts.*.opening_balance' => 'required|numeric|min:0',
            'bank_accounts.*.is_upi_account' => 'boolean',
            'bank_accounts.*.is_card_account' => 'boolean',
        ]);

        // Validate only one UPI account
        $upiCount = collect($validated['bank_accounts'])->where('is_upi_account', true)->count();
        if ($upiCount > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only one bank account can be marked as UPI account.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create Cash account
            Account::create([
                'temple_id' => $temple->id,
                'account_type' => 'cash',
                'account_name' => 'Cash',
                'opening_balance' => $validated['cash_opening_balance'],
                'current_balance' => $validated['cash_opening_balance'],
            ]);

            // Create Bank accounts
            foreach ($validated['bank_accounts'] as $bankAccount) {
                Account::create([
                    'temple_id' => $temple->id,
                    'account_type' => 'bank',
                    'account_name' => $bankAccount['account_name'],
                    'bank_name' => $bankAccount['bank_name'],
                    'account_number' => $bankAccount['account_number'],
                    'ifsc_code' => $bankAccount['ifsc_code'] ?? null,
                    'branch' => $bankAccount['branch'] ?? null,
                    'is_upi_account' => $bankAccount['is_upi_account'] ?? false,
                    'is_card_account' => $bankAccount['is_card_account'] ?? false,
                    'opening_balance' => $bankAccount['opening_balance'],
                    'current_balance' => $bankAccount['opening_balance'],
                ]);
            }

            // Mark setup as completed
            $temple->update(['accounts_setup_completed' => true]);

            // Create opening balance ledger entries
            $ledgerService = app(\App\Services\LedgerService::class);
            $ledgerService->createOpeningBalanceEntries($temple->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Accounts setup completed successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup accounts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update account (only for minor edits like account name, not balances)
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'account_name' => 'sometimes|string|max:255',
            'bank_name' => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'branch' => 'nullable|string|max:255',
            'is_upi_account' => 'sometimes|boolean',
            'is_card_account' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        // If setting as UPI account, remove UPI from other accounts
        if (isset($validated['is_upi_account']) && $validated['is_upi_account']) {
            Account::where('temple_id', auth()->user()->temple_id)
                ->where('id', '!=', $account->id)
                ->update(['is_upi_account' => false]);
        }

        // If setting as Card account, remove Card from other accounts
        if (isset($validated['is_card_account']) && $validated['is_card_account']) {
            Account::where('temple_id', auth()->user()->temple_id)
                ->where('id', '!=', $account->id)
                ->update(['is_card_account' => false]);
        }

        $account->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully',
            'data' => $account,
        ]);
    }

    /**
     * Add a new bank account (after initial setup)
     */
    public function store(Request $request): JsonResponse
    {
        $temple = Temple::find(auth()->user()->temple_id);

        // Must complete initial setup first
        if (!$temple->accounts_setup_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete initial account setup first.',
            ], 422);
        }

        $validated = $request->validate([
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'branch' => 'nullable|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
            'is_upi_account' => 'boolean',
            'is_card_account' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            // If setting as UPI account, remove UPI from other accounts
            if ($validated['is_upi_account'] ?? false) {
                Account::where('temple_id', $temple->id)
                    ->update(['is_upi_account' => false]);
            }

            // If setting as Card account, remove Card from other accounts
            if ($validated['is_card_account'] ?? false) {
                Account::where('temple_id', $temple->id)
                    ->update(['is_card_account' => false]);
            }

            // Create the account
            $account = Account::create([
                'temple_id' => $temple->id,
                'account_type' => 'bank',
                'account_name' => $validated['account_name'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'ifsc_code' => $validated['ifsc_code'] ?? null,
                'branch' => $validated['branch'] ?? null,
                'is_upi_account' => $validated['is_upi_account'] ?? false,
                'is_card_account' => $validated['is_card_account'] ?? false,
                'opening_balance' => $validated['opening_balance'],
                'current_balance' => $validated['opening_balance'],
            ]);

            // Create opening balance ledger entry if balance > 0
            if ($validated['opening_balance'] > 0) {
                $ledgerService = app(\App\Services\LedgerService::class);
                \App\Models\LedgerEntry::create([
                    'temple_id' => $temple->id,
                    'account_id' => $account->id,
                    'entry_date' => now()->toDateString(),
                    'type' => 'credit',
                    'amount' => $validated['opening_balance'],
                    'balance_after' => $validated['opening_balance'],
                    'source_type' => 'opening_balance',
                    'source_id' => null,
                    'narration' => "Opening balance for {$account->account_name}",
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bank account added successfully',
                'data' => $account,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add bank account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if setup is completed
     */
    public function checkSetup(): JsonResponse
    {
        $temple = Temple::find(auth()->user()->temple_id);

        return response()->json([
            'success' => true,
            'data' => [
                'setup_completed' => $temple->accounts_setup_completed ?? false,
            ],
        ]);
    }
}
