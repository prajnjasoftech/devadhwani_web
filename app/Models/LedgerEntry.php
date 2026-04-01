<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'account_id',
        'entry_number',
        'entry_date',
        'type',
        'amount',
        'balance_after',
        'source_type',
        'source_id',
        'narration',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (LedgerEntry $entry) {
            if (empty($entry->entry_number)) {
                $entry->entry_number = static::generateEntryNumber($entry->temple_id);
            }
        });
    }

    public static function generateEntryNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'LED';
        $year = date('Y');
        $month = date('m');

        $lastEntry = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastEntry ? ((int) substr($lastEntry->entry_number, -4)) + 1 : 1;

        return sprintf('%s/LED/%s%s/%04d', $prefix, $year, $month, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeInDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('entry_date', '>=', $from);
        }
        if ($to) {
            $query->where('entry_date', '<=', $to);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('entry_number', 'like', "%{$search}%")
              ->orWhere('narration', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getAmountFormattedAttribute(): string
    {
        $prefix = $this->type === 'credit' ? '+' : '-';
        return $prefix . ' ₹' . number_format($this->amount, 2);
    }

    public function getBalanceFormattedAttribute(): string
    {
        return '₹' . number_format($this->balance_after, 2);
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source_type) {
            'opening_balance' => 'Opening Balance',
            'booking' => 'Booking',
            'donation' => 'Donation',
            'purchase' => 'Purchase',
            'expense' => 'Expense',
            'salary' => 'Salary Payment',
            'employee_payment' => 'Employee Payment',
            'transfer' => 'Transfer',
            'adjustment' => 'Adjustment',
            default => ucfirst($this->source_type),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'credit' ? 'Credit' : 'Debit';
    }

    // Methods
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }
}
