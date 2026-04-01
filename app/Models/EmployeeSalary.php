<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'employee_id',
        'year',
        'month',
        'basic_salary',
        'allowances',
        'deductions',
        'net_salary',
        'payment_status',
        'paid_amount',
        'payment_date',
        'payment_method',
        'account_id',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected $appends = ['salary_month', 'status'];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (EmployeeSalary $salary) {
            // Calculate net salary
            $salary->net_salary = $salary->basic_salary + $salary->allowances - $salary->deductions;
        });

        static::updating(function (EmployeeSalary $salary) {
            if ($salary->isDirty(['basic_salary', 'allowances', 'deductions'])) {
                $salary->net_salary = $salary->basic_salary + $salary->allowances - $salary->deductions;
            }
        });
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
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Accessors
    public function getNetSalaryFormattedAttribute(): string
    {
        return '₹' . number_format($this->net_salary, 2);
    }

    public function getBalanceAmountAttribute(): float
    {
        return $this->net_salary - $this->paid_amount;
    }

    public function getMonthNameAttribute(): string
    {
        return date('F', mktime(0, 0, 0, $this->month, 1));
    }

    public function getPeriodAttribute(): string
    {
        return $this->month_name . ' ' . $this->year;
    }

    public function getSalaryMonthAttribute(): string
    {
        return $this->month_name . ' ' . $this->year;
    }

    public function getStatusAttribute(): string
    {
        return $this->payment_status;
    }

    // Methods
    public function markAsPaid(string $paymentMethod, ?int $accountId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_amount' => $this->net_salary,
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'account_id' => $accountId,
        ]);
    }

    public function addPayment(float $amount, string $paymentMethod, ?int $accountId = null): void
    {
        $newPaidAmount = $this->paid_amount + $amount;
        $status = $newPaidAmount >= $this->net_salary ? 'paid' : 'partial';

        $this->update([
            'paid_amount' => min($newPaidAmount, $this->net_salary),
            'payment_status' => $status,
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'account_id' => $accountId,
        ]);
    }
}
