<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Record a credit entry (money coming in)
     */
    public function credit(
        Account $account,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        string $narration,
        ?string $entryDate = null
    ): LedgerEntry {
        return $this->createEntry($account, 'credit', $amount, $sourceType, $sourceId, $narration, $entryDate);
    }

    /**
     * Record a debit entry (money going out)
     */
    public function debit(
        Account $account,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        string $narration,
        ?string $entryDate = null
    ): LedgerEntry {
        return $this->createEntry($account, 'debit', $amount, $sourceType, $sourceId, $narration, $entryDate);
    }

    /**
     * Create a ledger entry and update account balance
     */
    protected function createEntry(
        Account $account,
        string $type,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        string $narration,
        ?string $entryDate
    ): LedgerEntry {
        return DB::transaction(function () use ($account, $type, $amount, $sourceType, $sourceId, $narration, $entryDate) {
            // Lock the account row to prevent race conditions
            $account = Account::lockForUpdate()->find($account->id);

            // Calculate new balance
            $currentBalance = $account->current_balance;

            // Validate sufficient balance for debits
            if ($type === 'debit' && $currentBalance < $amount) {
                throw new \App\Exceptions\InsufficientBalanceException(
                    "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($currentBalance, 2) . ", Required: ₹" . number_format($amount, 2)
                );
            }

            $newBalance = $type === 'credit'
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            // Update account balance
            $account->update(['current_balance' => $newBalance]);

            // Create ledger entry
            return LedgerEntry::create([
                'temple_id' => $account->temple_id,
                'account_id' => $account->id,
                'entry_date' => $entryDate ?? now()->toDateString(),
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'narration' => $narration,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Transfer money between accounts
     * Creates a debit entry for source account and credit entry for destination account
     */
    public function transfer(
        Account $fromAccount,
        Account $toAccount,
        float $amount,
        string $narration,
        ?string $entryDate = null
    ): array {
        return DB::transaction(function () use ($fromAccount, $toAccount, $amount, $narration, $entryDate) {
            $entryDate = $entryDate ?? now()->toDateString();

            // Debit from source account
            $debitEntry = $this->createEntry(
                $fromAccount,
                'debit',
                $amount,
                'transfer',
                $toAccount->id,
                "Transfer to {$toAccount->display_name}: {$narration}",
                $entryDate
            );

            // Credit to destination account
            $creditEntry = $this->createEntry(
                $toAccount,
                'credit',
                $amount,
                'transfer',
                $fromAccount->id,
                "Transfer from {$fromAccount->display_name}: {$narration}",
                $entryDate
            );

            return [
                'debit_entry' => $debitEntry,
                'credit_entry' => $creditEntry,
            ];
        });
    }

    /**
     * Reverse a ledger entry (for deletions/corrections)
     */
    public function reverse(LedgerEntry $entry, string $reason): LedgerEntry
    {
        $reverseType = $entry->type === 'credit' ? 'debit' : 'credit';
        $narration = "Reversal of {$entry->entry_number}: {$reason}";

        return $this->createEntry(
            $entry->account,
            $reverseType,
            $entry->amount,
            'adjustment',
            null,
            $narration,
            now()->toDateString()
        );
    }

    /**
     * Create opening balance entries for all accounts of a temple
     * Called after account setup
     */
    public function createOpeningBalanceEntries(int $templeId): void
    {
        $accounts = Account::where('temple_id', $templeId)->get();

        foreach ($accounts as $account) {
            if ($account->opening_balance > 0) {
                LedgerEntry::create([
                    'temple_id' => $templeId,
                    'account_id' => $account->id,
                    'entry_date' => now()->toDateString(),
                    'type' => 'credit',
                    'amount' => $account->opening_balance,
                    'balance_after' => $account->opening_balance,
                    'source_type' => 'opening_balance',
                    'source_id' => null,
                    'narration' => "Opening balance for {$account->display_name}",
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Get account statement with opening and closing balances
     */
    public function getAccountStatement(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $account = Account::findOrFail($accountId);

        // Default to current month if no dates provided
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();

        // Get opening balance (balance before from_date)
        $openingEntry = LedgerEntry::where('account_id', $accountId)
            ->where('entry_date', '<', $fromDate)
            ->orderBy('id', 'desc')
            ->first();

        $openingBalance = $openingEntry ? $openingEntry->balance_after : 0;

        // If no entries before the from_date, use account's opening balance
        if (!$openingEntry) {
            $firstEntry = LedgerEntry::where('account_id', $accountId)
                ->orderBy('id')
                ->first();

            if ($firstEntry && $firstEntry->source_type === 'opening_balance') {
                // If the first entry IS the opening balance and it falls within our range
                if ($firstEntry->entry_date >= $fromDate) {
                    $openingBalance = 0;
                }
            }
        }

        // Get entries in date range
        $entries = LedgerEntry::where('account_id', $accountId)
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Calculate totals
        $totalCredits = $entries->where('type', 'credit')->sum('amount');
        $totalDebits = $entries->where('type', 'debit')->sum('amount');
        $closingBalance = $openingBalance + $totalCredits - $totalDebits;

        return [
            'account' => $account,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_balance' => $openingBalance,
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'closing_balance' => $closingBalance,
            'entries' => $entries,
        ];
    }

    /**
     * Get balance sheet summary for all accounts
     */
    public function getBalanceSheet(int $templeId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = Account::where('temple_id', $templeId)
            ->where('is_active', true)
            ->get();

        $result = [
            'as_of_date' => $asOfDate,
            'cash' => [],
            'bank' => [],
            'total_cash' => 0,
            'total_bank' => 0,
            'grand_total' => 0,
        ];

        foreach ($accounts as $account) {
            // Get balance as of the specified date
            $lastEntry = LedgerEntry::where('account_id', $account->id)
                ->where('entry_date', '<=', $asOfDate)
                ->orderBy('id', 'desc')
                ->first();

            $balance = $lastEntry ? $lastEntry->balance_after : $account->opening_balance;

            $accountData = [
                'id' => $account->id,
                'name' => $account->display_name,
                'opening_balance' => $account->opening_balance,
                'current_balance' => $balance,
                'is_upi' => $account->is_upi_account,
            ];

            if ($account->account_type === 'cash') {
                $result['cash'][] = $accountData;
                $result['total_cash'] += $balance;
            } else {
                $result['bank'][] = $accountData;
                $result['total_bank'] += $balance;
            }
        }

        $result['grand_total'] = $result['total_cash'] + $result['total_bank'];

        return $result;
    }
}
