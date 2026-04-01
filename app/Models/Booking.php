<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'booking_number',
        'booking_date',
        'contact_name',
        'contact_number',
        'contact_email',
        'contact_address',
        'prasadam_required',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'booking_status',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'prasadam_required' => 'boolean',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Booking $booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = static::generateBookingNumber($booking->temple_id);
            }
        });
    }

    public static function generateBookingNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'BK';
        $year = date('Y');

        $lastBooking = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastBooking ? ((int) substr($lastBooking->booking_number, -6)) + 1 : 1;

        return sprintf('%s/%s/%06d', $prefix, $year, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    // Scopes
    public function scopeConfirmed($query)
    {
        return $query->where('booking_status', 'confirmed');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance_amount', '>', 0);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('booking_number', 'like', "%{$search}%")
              ->orWhere('contact_name', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%");
        });
    }

    // Methods
    public function recalculateTotals(): void
    {
        $this->total_amount = $this->items()->where('status', 'active')->sum('total_amount');
        $this->paid_amount = $this->payments()->sum('amount');
        $this->balance_amount = $this->total_amount - $this->paid_amount;

        if ($this->balance_amount <= 0) {
            $this->payment_status = 'paid';
            $this->balance_amount = 0;
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }

        $this->save();
    }

    public function addPayment(float $amount, string $method, ?int $accountId = null, ?string $reference = null, ?string $notes = null): BookingPayment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => now()->toDateString(),
            'account_id' => $accountId,
            'reference_number' => $reference,
            'notes' => $notes,
            'received_by' => auth()->id(),
        ]);

        $this->recalculateTotals();

        // Create ledger entry if account is specified
        if ($accountId) {
            $account = Account::find($accountId);
            if ($account) {
                $ledgerService = app(\App\Services\LedgerService::class);
                $ledgerService->credit(
                    $account,
                    $amount,
                    'booking',
                    $this->id,
                    "Booking payment - {$this->booking_number} - {$this->devotee_name}",
                    now()->toDateString()
                );
            }
        }

        return $payment;
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'booking_status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Cancel all schedules
        foreach ($this->items as $item) {
            $item->schedules()->where('status', 'pending')->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => 'Booking cancelled',
            ]);
        }
    }
}
