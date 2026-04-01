<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Donation::query()
            ->with(['donationHead:id,name', 'assetType:id,name,unit', 'account:id,account_name', 'creator:id,name'])
            ->search($request->search);

        if ($request->has('donation_head_id')) {
            $query->where('donation_head_id', $request->donation_head_id);
        }

        if ($request->has('donation_type')) {
            $query->where('donation_type', $request->donation_type);
        }

        if ($request->has('asset_type_id')) {
            $query->where('asset_type_id', $request->asset_type_id);
        }

        if ($request->has('date_from')) {
            $query->where('donation_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('donation_date', '<=', $request->date_to);
        }

        $donations = $query
            ->orderBy($request->sort_by ?? 'donation_date', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $donations->items(),
            'meta' => [
                'current_page' => $donations->currentPage(),
                'last_page' => $donations->lastPage(),
                'per_page' => $donations->perPage(),
                'total' => $donations->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        \Log::info('Donation store request', $request->all());

        $validated = $request->validate([
            'donation_date' => 'required|date',
            'donation_head_id' => 'required|exists:donation_heads,id',
            'donation_type' => 'required|in:financial,asset',
            'donor_name' => 'required|string|max:255',
            'donor_contact' => 'nullable|string|max:20',
            'donor_address' => 'nullable|string',

            // Financial donation fields
            'amount' => 'required_if:donation_type,financial|nullable|numeric|min:0.01',
            'payment_method' => 'required_if:donation_type,financial|nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'account_id' => 'required_if:donation_type,financial|nullable|exists:accounts,id',
            'reference_number' => 'nullable|string|max:100',

            // Asset donation fields
            'asset_type_id' => 'required_if:donation_type,asset|nullable|exists:asset_types,id',
            'asset_description' => 'required_if:donation_type,asset|nullable|string|max:255',
            'quantity' => 'required_if:donation_type,asset|nullable|numeric|min:0.001',
            'estimated_value' => 'nullable|numeric|min:0',

            'notes' => 'nullable|string',
        ]);

        \Log::info('Donation validated', $validated);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();

        // Clear irrelevant fields based on donation type
        if ($validated['donation_type'] === 'financial') {
            $validated['asset_type_id'] = null;
            $validated['asset_description'] = null;
            $validated['quantity'] = null;
            $validated['estimated_value'] = null;
        } else {
            $validated['amount'] = null;
            $validated['payment_method'] = null;
            $validated['account_id'] = null;
            $validated['reference_number'] = null;
        }

        $donation = Donation::create($validated);
        $donation->load(['donationHead:id,name', 'assetType:id,name,unit', 'account:id,account_name']);

        return response()->json([
            'success' => true,
            'message' => 'Donation recorded successfully',
            'data' => $donation,
        ], 201);
    }

    public function show(Donation $donation): JsonResponse
    {
        $donation->load(['donationHead', 'assetType', 'account', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => $donation,
        ]);
    }

    public function update(Request $request, Donation $donation): JsonResponse
    {
        $validated = $request->validate([
            'donation_date' => 'sometimes|date',
            'donation_head_id' => 'sometimes|exists:donation_heads,id',
            'donor_name' => 'sometimes|string|max:255',
            'donor_contact' => 'nullable|string|max:20',
            'donor_address' => 'nullable|string',
            'notes' => 'nullable|string',

            // Note: donation_type cannot be changed after creation
            // Financial fields (only if financial type)
            'amount' => 'sometimes|nullable|numeric|min:0.01',
            'payment_method' => 'sometimes|nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'reference_number' => 'nullable|string|max:100',

            // Asset fields (only if asset type)
            'asset_description' => 'sometimes|nullable|string|max:255',
            'quantity' => 'sometimes|nullable|numeric|min:0.001',
            'estimated_value' => 'nullable|numeric|min:0',
        ]);

        // Handle amount change for financial donations (create adjustment ledger entry)
        if ($donation->isFinancial() && isset($validated['amount']) && $donation->account_id) {
            $difference = $validated['amount'] - $donation->amount;
            if ($difference != 0) {
                $ledgerService = app(\App\Services\LedgerService::class);
                if ($difference > 0) {
                    $ledgerService->credit(
                        $donation->account,
                        abs($difference),
                        'donation',
                        $donation->id,
                        "Donation amount increased - {$donation->donation_number}",
                        $donation->donation_date->toDateString()
                    );
                } else {
                    $ledgerService->debit(
                        $donation->account,
                        abs($difference),
                        'adjustment',
                        null,
                        "Donation amount reduced - {$donation->donation_number}",
                        now()->toDateString()
                    );
                }
            }
        }

        $donation->update($validated);
        $donation->load(['donationHead:id,name', 'assetType:id,name,unit', 'account:id,account_name']);

        return response()->json([
            'success' => true,
            'message' => 'Donation updated successfully',
            'data' => $donation,
        ]);
    }

    public function destroy(Donation $donation): JsonResponse
    {
        // Reverse the account balance if financial donation
        if ($donation->isFinancial() && $donation->account_id && $donation->amount > 0) {
            $ledgerService = app(\App\Services\LedgerService::class);
            $ledgerService->debit(
                $donation->account,
                $donation->amount,
                'adjustment',
                null,
                "Donation deleted - {$donation->donation_number}",
                now()->toDateString()
            );
        }

        $donation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Donation deleted successfully',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $templeId = auth()->user()->temple_id;

        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        // Financial donations
        $todayFinancial = Donation::where('temple_id', $templeId)
            ->whereDate('donation_date', $today)
            ->where('donation_type', 'financial')
            ->sum('amount');

        $monthFinancial = Donation::where('temple_id', $templeId)
            ->where('donation_date', '>=', $thisMonth)
            ->where('donation_type', 'financial')
            ->sum('amount');

        // Count by type
        $monthCount = Donation::where('temple_id', $templeId)
            ->where('donation_date', '>=', $thisMonth)
            ->selectRaw('donation_type, COUNT(*) as count')
            ->groupBy('donation_type')
            ->pluck('count', 'donation_type');

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'financial' => $todayFinancial,
                    'count' => Donation::where('temple_id', $templeId)
                        ->whereDate('donation_date', $today)->count(),
                ],
                'month' => [
                    'financial' => $monthFinancial,
                    'financial_count' => $monthCount['financial'] ?? 0,
                    'asset_count' => $monthCount['asset'] ?? 0,
                    'total_count' => ($monthCount['financial'] ?? 0) + ($monthCount['asset'] ?? 0),
                ],
            ],
        ]);
    }
}
