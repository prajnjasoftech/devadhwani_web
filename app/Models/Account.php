<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'account_type',
        'account_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'branch',
        'is_upi_account',
        'is_card_account',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'is_upi_account' => 'boolean',
        'is_card_account' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCash($query)
    {
        return $query->where('account_type', 'cash');
    }

    public function scopeBank($query)
    {
        return $query->where('account_type', 'bank');
    }

    public function scopeUpi($query)
    {
        return $query->where('is_upi_account', true);
    }

    public function scopeCard($query)
    {
        return $query->where('is_card_account', true);
    }

    // Accessors
    public function getOpeningBalanceFormattedAttribute(): string
    {
        return '₹' . number_format($this->opening_balance, 2);
    }

    public function getCurrentBalanceFormattedAttribute(): string
    {
        return '₹' . number_format($this->current_balance, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->account_type === 'cash') {
            return 'Cash';
        }

        $name = $this->account_name;
        $tags = [];
        if ($this->is_upi_account) {
            $tags[] = 'UPI';
        }
        if ($this->is_card_account) {
            $tags[] = 'Card';
        }
        if (!empty($tags)) {
            $name .= ' (' . implode(', ', $tags) . ')';
        }
        return $name;
    }

    // Methods
    public function credit(float $amount): void
    {
        $this->increment('current_balance', $amount);
    }

    public function debit(float $amount): void
    {
        $this->decrement('current_balance', $amount);
    }

    public function isCash(): bool
    {
        return $this->account_type === 'cash';
    }

    public function isBank(): bool
    {
        return $this->account_type === 'bank';
    }
}
