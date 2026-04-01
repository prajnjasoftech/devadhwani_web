<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'expense_number',
        'expense_date',
        'category_id',
        'description',
        'amount',
        'payment_status',
        'paid_amount',
        'payment_method',
        'account_id',
        'reference_number',
        'paid_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Expense $expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber($expense->temple_id);
            }
        });
    }

    public static function generateExpenseNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'EXP';
        $year = date('Y');
        $month = date('m');

        $lastExpense = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastExpense ? ((int) substr($lastExpense->expense_number, -4)) + 1 : 1;

        return sprintf('%s/EXP/%s%s/%04d', $prefix, $year, $month, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
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
            $q->where('expense_number', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('reference_number', 'like', "%{$search}%")
              ->orWhere('paid_to', 'like', "%{$search}%")
              ->orWhereHas('category', function ($q) use ($search) {
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
    public function getAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->amount, 2);
    }

    public function getBalanceAmountAttribute(): float
    {
        return $this->amount - $this->paid_amount;
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
            'paid_amount' => $this->amount,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function addPayment(float $amount, string $paymentMethod): void
    {
        $newPaidAmount = $this->paid_amount + $amount;
        $status = $newPaidAmount >= $this->amount ? 'paid' : 'partial';

        $this->update([
            'paid_amount' => min($newPaidAmount, $this->amount),
            'payment_status' => $status,
            'payment_method' => $paymentMethod,
        ]);
    }
}
