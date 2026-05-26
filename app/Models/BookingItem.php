<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'pooja_id',
        'deity_id',
        'start_date',
        'end_date',
        'frequency',
        'schedule_type',
        'schedule_rule',
        'occurrences_total',
        'occurrences_completed',
        'weekly_day',
        'monthly_type',
        'monthly_day',
        'unit_amount',
        'quantity',
        'beneficiary_count',
        'occurrence_count',
        'total_amount',
        'notes',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'unit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'schedule_rule' => 'array',
    ];

    // Relationships
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function pooja(): BelongsTo
    {
        return $this->belongsTo(Pooja::class);
    }

    public function deity(): BelongsTo
    {
        return $this->belongsTo(Deity::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(BookingBeneficiary::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BookingSchedule::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Methods
    public function calculateScheduleDates(): array
    {
        $dates = [];
        $startDate = Carbon::parse($this->start_date);

        if ($this->frequency === 'once') {
            $dates[] = $startDate->toDateString();
            return $dates;
        }

        $endDate = Carbon::parse($this->end_date);

        switch ($this->frequency) {
            case 'daily':
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    $dates[] = $currentDate->toDateString();
                    $currentDate->addDay();
                }
                break;

            case 'weekly':
                $currentDate = $startDate->copy();
                $targetDayOfWeek = $this->weekly_day ?? $startDate->dayOfWeek;

                // Adjust to target day if start date is on a different day
                if ($currentDate->dayOfWeek !== $targetDayOfWeek) {
                    $currentDate->next($targetDayOfWeek);
                }

                while ($currentDate->lte($endDate)) {
                    $dates[] = $currentDate->toDateString();
                    $currentDate->addWeek();
                }
                break;

            case 'monthly':
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    $dates[] = $currentDate->toDateString();

                    if ($this->monthly_type === 'by_date' && $this->monthly_day) {
                        // Move to next month, same day
                        $currentDate->addMonth();
                        $lastDay = $currentDate->daysInMonth;
                        $day = min($this->monthly_day, $lastDay);
                        $currentDate->day($day);
                    } else {
                        // Default: same date next month
                        $currentDate->addMonth();
                    }
                }
                break;
        }

        return $dates;
    }

    public function generateSchedules(): void
    {
        $dates = $this->calculateScheduleDates();

        foreach ($dates as $date) {
            $this->schedules()->firstOrCreate([
                'scheduled_date' => $date,
            ], [
                'status' => 'pending',
            ]);
        }

        // Update occurrence count
        $this->occurrence_count = count($dates);
        $this->calculateTotalAmount();
    }

    public function calculateTotalAmount(): void
    {
        // Update beneficiary_count from actual beneficiaries if they exist
        $actualBeneficiaries = $this->beneficiaries()->count();
        if ($actualBeneficiaries > 0) {
            $this->beneficiary_count = $actualBeneficiaries;
        }
        // If no beneficiaries, beneficiary_count defaults to 1

        // Formula: amount × quantity × devotee_count × occurrences
        $quantity = $this->quantity ?? 1;
        $this->total_amount = $this->unit_amount * $quantity * $this->beneficiary_count * $this->occurrence_count;
        $this->save();

        // Recalculate booking totals
        $this->booking->recalculateTotals();
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'once' => 'One Time',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            default => ucfirst($this->frequency),
        };
    }

    public function getDateRangeAttribute(): string
    {
        if ($this->frequency === 'once') {
            return $this->start_date->format('d M Y');
        }

        return $this->start_date->format('d M Y') . ' - ' . $this->end_date->format('d M Y');
    }

    public function getScheduleTypeLabelAttribute(): string
    {
        return match ($this->schedule_type) {
            'once' => 'One Time',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly_same_date' => 'Monthly (Same Date)',
            'monthly_nakshatra' => 'Monthly (Nakshatra)',
            'monthly_malayalam_weekday' => 'Monthly (Malayalam Weekday)',
            'monthly_pooja_schedule' => 'Monthly (Pooja Schedule)',
            default => ucfirst($this->schedule_type ?? 'once'),
        };
    }

    /**
     * Check if this booking item needs cron-based schedule creation
     */
    public function needsCronProcessing(): bool
    {
        return in_array($this->schedule_type, [
            'daily',
            'weekly',
            'monthly_same_date',
            'monthly_nakshatra',
            'monthly_malayalam_weekday',
            'monthly_pooja_schedule',
        ]);
    }

    /**
     * Check if all occurrences are completed
     */
    public function isCompleted(): bool
    {
        return $this->occurrences_completed >= $this->occurrences_total;
    }
}
