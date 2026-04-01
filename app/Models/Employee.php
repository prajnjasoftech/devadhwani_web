<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'employee_code',
        'name',
        'designation',
        'contact_number',
        'alternate_contact',
        'email',
        'address',
        'date_of_birth',
        'date_of_joining',
        'date_of_leaving',
        'basic_salary',
        'bank_name',
        'bank_account_number',
        'ifsc_code',
        'pan_number',
        'aadhaar_number',
        'user_id',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_joining' => 'date',
        'date_of_leaving' => 'date',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Employee $employee) {
            if (empty($employee->employee_code)) {
                $employee->employee_code = static::generateEmployeeCode($employee->temple_id);
            }
        });
    }

    public static function generateEmployeeCode(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'EMP';

        $lastEmployee = static::where('temple_id', $templeId)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastEmployee ? ((int) substr($lastEmployee->employee_code, -4)) + 1 : 1;

        return sprintf('%s/EMP/%04d', $prefix, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmployeePayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('employee_code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('designation', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getBasicSalaryFormattedAttribute(): string
    {
        return '₹' . number_format($this->basic_salary, 2);
    }

    public function getIsUserAttribute(): bool
    {
        return $this->user_id !== null;
    }
}
