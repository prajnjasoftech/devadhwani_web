<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'purchase_number',
        'purchase_date',
        'vendor_id',
        'category_id',
        'purpose_id',
        'item_description',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'payment_status',
        'paid_amount',
        'payment_method',
        'account_id',
        'bill_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Purchase $purchase) {
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = static::generatePurchaseNumber($purchase->temple_id);
            }
            // Auto-calculate total
            if ($purchase->quantity && $purchase->unit_price) {
                $purchase->total_amount = $purchase->quantity * $purchase->unit_price;
            }
        });

        static::updating(function (Purchase $purchase) {
            // Auto-calculate total on update
            if ($purchase->isDirty(['quantity', 'unit_price'])) {
                $purchase->total_amount = $purchase->quantity * $purchase->unit_price;
            }
        });
    }

    public static function generatePurchaseNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'PUR';
        $year = date('Y');
        $month = date('m');

        $lastPurchase = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPurchase ? ((int) substr($lastPurchase->purchase_number, -4)) + 1 : 1;

        return sprintf('%s/PUR/%s%s/%04d', $prefix, $year, $month, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PurchaseCategory::class, 'category_id');
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(PurchasePurpose::class, 'purpose_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('purchase_number', 'like', "%{$search}%")
              ->orWhere('item_description', 'like', "%{$search}%")
              ->orWhere('bill_number', 'like', "%{$search}%")
              ->orWhereHas('vendor', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Accessors
    public function getTotalAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->total_amount, 2);
    }

    public function getBalanceAmountAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getBalanceAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->balance_amount, 2);
    }

    // Methods
    public function markAsPaid(string $paymentMethod = 'cash'): void
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_amount' => $this->total_amount,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function addPayment(float $amount, string $paymentMethod): void
    {
        $newPaidAmount = $this->paid_amount + $amount;
        $status = $newPaidAmount >= $this->total_amount ? 'paid' : 'partial';

        $this->update([
            'paid_amount' => min($newPaidAmount, $this->total_amount),
            'payment_status' => $status,
            'payment_method' => $paymentMethod,
        ]);
    }
}
