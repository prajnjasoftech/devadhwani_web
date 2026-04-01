<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::query()
            ->with(['vendor:id,name', 'category:id,name', 'purpose:id,name', 'creator:id,name', 'account:id,account_name'])
            ->search($request->search);

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('purpose_id')) {
            $query->where('purpose_id', $request->purpose_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->where('purchase_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('purchase_date', '<=', $request->date_to);
        }

        $purchases = $query
            ->orderBy($request->sort_by ?? 'purchase_date', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $purchases->items(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_date' => 'required|date',
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'required|exists:purchase_categories,id',
            'purpose_id' => 'required|exists:purchase_purposes,id',
            'item_description' => 'required|string|max:500',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,upi,card,bank_transfer,credit,other',
            'account_id' => 'nullable|exists:accounts,id',
            'bill_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['temple_id'] = auth()->user()->temple_id;
        $validated['created_by'] = auth()->id();
        $validated['total_amount'] = $validated['quantity'] * $validated['unit_price'];

        // Set payment status based on paid amount
        if (!isset($validated['payment_status'])) {
            if (($validated['paid_amount'] ?? 0) >= $validated['total_amount']) {
                $validated['payment_status'] = 'paid';
                $validated['paid_amount'] = $validated['total_amount'];
            } elseif (($validated['paid_amount'] ?? 0) > 0) {
                $validated['payment_status'] = 'partial';
            } else {
                $validated['payment_status'] = 'pending';
                $validated['paid_amount'] = 0;
            }
        }

        // Validate sufficient balance before creating purchase
        if (($validated['paid_amount'] ?? 0) > 0 && !empty($validated['account_id'])) {
            $account = \App\Models\Account::find($validated['account_id']);
            if ($account->current_balance < $validated['paid_amount']) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient balance in {$account->display_name}. Available: ₹" . number_format($account->current_balance, 2),
                ], 422);
            }
        }

        $purchase = Purchase::create($validated);

        // Create ledger entry if payment was made
        if (($validated['paid_amount'] ?? 0) > 0 && !empty($validated['account_id'])) {
            $account = \App\Models\Account::find($validated['account_id']);
            $ledgerService = app(\App\Services\LedgerService::class);
            $ledgerService->debit(
                $account,
                $validated['paid_amount'],
                'purchase',
                $purchase->id,
                "Purchase payment - {$purchase->purchase_number} - {$purchase->item_description}",
                $validated['purchase_date']
            );
        }

        $purchase->load(['vendor:id,name', 'category:id,name', 'purpose:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase recorded successfully',
            'data' => $purchase,
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['vendor', 'category', 'purpose', 'creator:id,name', 'account:id,account_name,account_type']);

        return response()->json([
            'success' => true,
            'data' => $purchase,
        ]);
    }

    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'purchase_date' => 'sometimes|date',
            'vendor_id' => 'sometimes|exists:vendors,id',
            'category_id' => 'sometimes|exists:purchase_categories,id',
            'purpose_id' => 'sometimes|exists:purchase_purposes,id',
            'item_description' => 'sometimes|string|max:500',
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'sometimes|numeric|min:0',
            'payment_status' => 'nullable|in:pending,partial,paid',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,upi,card,bank_transfer,credit,other',
            'account_id' => 'nullable|exists:accounts,id',
            'bill_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Recalculate total if quantity or unit_price changed
        if (isset($validated['quantity']) || isset($validated['unit_price'])) {
            $quantity = $validated['quantity'] ?? $purchase->quantity;
            $unitPrice = $validated['unit_price'] ?? $purchase->unit_price;
            $validated['total_amount'] = $quantity * $unitPrice;
        }

        $purchase->update($validated);
        $purchase->load(['vendor:id,name', 'category:id,name', 'purpose:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase updated successfully',
            'data' => $purchase,
        ]);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $purchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase deleted successfully',
        ]);
    }

    public function addPayment(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,upi,card,bank_transfer,credit,other',
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
            'purchase',
            $purchase->id,
            "Purchase payment - {$purchase->purchase_number}",
            now()->toDateString()
        );

        $purchase->addPayment($validated['amount'], $validated['payment_method']);
        $purchase->update(['account_id' => $validated['account_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Payment added successfully',
            'data' => $purchase->fresh(['vendor:id,name', 'category:id,name', 'purpose:id,name']),
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
                    'count' => Purchase::where('temple_id', $templeId)
                        ->whereDate('purchase_date', $today)->count(),
                    'total' => Purchase::where('temple_id', $templeId)
                        ->whereDate('purchase_date', $today)->sum('total_amount'),
                ],
                'month' => [
                    'count' => Purchase::where('temple_id', $templeId)
                        ->where('purchase_date', '>=', $thisMonth)->count(),
                    'total' => Purchase::where('temple_id', $templeId)
                        ->where('purchase_date', '>=', $thisMonth)->sum('total_amount'),
                    'paid' => Purchase::where('temple_id', $templeId)
                        ->where('purchase_date', '>=', $thisMonth)->sum('paid_amount'),
                ],
                'pending' => [
                    'count' => Purchase::where('temple_id', $templeId)
                        ->whereIn('payment_status', ['pending', 'partial'])->count(),
                    'amount' => Purchase::where('temple_id', $templeId)
                        ->whereIn('payment_status', ['pending', 'partial'])
                        ->selectRaw('SUM(total_amount - paid_amount) as pending')
                        ->value('pending') ?? 0,
                ],
            ],
        ]);
    }
}
