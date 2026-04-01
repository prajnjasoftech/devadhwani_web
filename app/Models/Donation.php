<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'donation_number',
        'donation_date',
        'donation_head_id',
        'donation_type',
        'donor_name',
        'donor_contact',
        'donor_address',
        'amount',
        'payment_method',
        'account_id',
        'reference_number',
        'asset_type_id',
        'asset_description',
        'quantity',
        'estimated_value',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'donation_date' => 'date',
        'amount' => 'decimal:2',
        'quantity' => 'decimal:3',
        'estimated_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Donation $donation) {
            if (empty($donation->donation_number)) {
                $donation->donation_number = static::generateDonationNumber($donation->temple_id);
            }
        });

        // Create ledger entry when financial donation is created
        static::created(function (Donation $donation) {
            if ($donation->isFinancial() && $donation->account_id) {
                $ledgerService = app(\App\Services\LedgerService::class);
                $ledgerService->credit(
                    $donation->account,
                    $donation->amount,
                    'donation',
                    $donation->id,
                    "Donation from {$donation->donor_name} - {$donation->donation_number}",
                    $donation->donation_date->toDateString()
                );
            }
        });
    }

    public static function generateDonationNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'DON';
        $year = date('Y');
        $month = date('m');

        $lastDonation = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastDonation ? ((int) substr($lastDonation->donation_number, -4)) + 1 : 1;

        return sprintf('%s/DON/%s%s/%04d', $prefix, $year, $month, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function donationHead(): BelongsTo
    {
        return $this->belongsTo(DonationHead::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
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
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('donation_number', 'like', "%{$search}%")
              ->orWhere('donor_name', 'like', "%{$search}%")
              ->orWhere('donor_contact', 'like', "%{$search}%")
              ->orWhere('asset_description', 'like', "%{$search}%");
        });
    }

    public function scopeFinancial($query)
    {
        return $query->where('donation_type', 'financial');
    }

    public function scopeAsset($query)
    {
        return $query->where('donation_type', 'asset');
    }

    // Accessors
    public function getAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->amount ?? 0, 2);
    }

    public function getEstimatedValueFormattedAttribute(): string
    {
        return '₹' . number_format($this->estimated_value ?? 0, 2);
    }

    public function getDisplayValueAttribute(): string
    {
        if ($this->isFinancial()) {
            return $this->amount_formatted;
        }

        $value = $this->quantity;
        if ($this->assetType && $this->assetType->unit) {
            $value .= ' ' . $this->assetType->unit;
        }
        if ($this->estimated_value) {
            $value .= ' (~' . $this->estimated_value_formatted . ')';
        }
        return $value;
    }

    // Methods
    public function isFinancial(): bool
    {
        return $this->donation_type === 'financial';
    }

    public function isAsset(): bool
    {
        return $this->donation_type === 'asset';
    }
}
