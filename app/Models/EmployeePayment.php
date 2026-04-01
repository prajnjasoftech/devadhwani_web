<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayment extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'employee_id',
        'payment_number',
        'payment_date',
        'payment_type',
        'description',
        'amount',
        'payment_method',
        'account_id',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (EmployeePayment $payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = static::generatePaymentNumber($payment->temple_id);
            }
        });
    }

    public static function generatePaymentNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'PAY';
        $year = date('Y');
        $month = date('m');

        $lastPayment = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -4)) + 1 : 1;

        return sprintf('%s/PAY/%s%s/%04d', $prefix, $year, $month, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
            $q->where('payment_number', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('employee', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }

    // Accessors
    public function getAmountFormattedAttribute(): string
    {
        return '₹' . number_format($this->amount, 2);
    }

    public function getPaymentTypeLabelAttribute(): string
    {
        return match ($this->payment_type) {
            'bonus' => 'Bonus',
            'advance' => 'Advance',
            'reimbursement' => 'Reimbursement',
            'incentive' => 'Incentive',
            'other' => 'Other',
            default => ucfirst($this->payment_type),
        };
    }
}
