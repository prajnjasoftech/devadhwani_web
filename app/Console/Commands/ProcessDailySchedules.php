<?php

namespace App\Console\Commands;

use App\Models\BookingItem;
use App\Models\BookingSchedule;
use App\Models\PanchangData;
use App\Models\Pooja;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDailySchedules extends Command
{
    protected $signature = 'schedules:process {--date= : Process for specific date (default: tomorrow)}';

    protected $description = 'Process daily schedules - creates schedules for tomorrow and queues notifications';

    public function handle()
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->addDay();

        $dateString = $targetDate->toDateString();

        $this->info("Processing schedules for: {$dateString}");

        // Get panchang data for target date
        $panchang = PanchangData::where('date', $dateString)->first();

        if (!$panchang) {
            $this->error("No panchang data found for {$dateString}");
            return 1;
        }

        $this->info("Panchang loaded - Nakshatra: " . ($panchang->nakshatra[0]['name'] ?? 'N/A'));

        $created = 0;
        $skipped = 0;

        // Process each schedule type
        $created += $this->processOnceBookings($dateString);
        $created += $this->processDailyBookings($dateString);
        $created += $this->processWeeklyBookings($dateString, $targetDate);
        $created += $this->processMonthlySameDateBookings($dateString, $targetDate);
        $created += $this->processMonthlyNakshatraBookings($dateString, $panchang);
        $created += $this->processMonthlyMalayalamWeekdayBookings($dateString, $panchang, $targetDate);
        $created += $this->processMonthlyPoojaScheduleBookings($dateString);

        $this->info("Completed: {$created} schedules created");

        // TODO: Queue notifications for created schedules

        return 0;
    }

    /**
     * Process once bookings for the target date
     */
    protected function processOnceBookings(string $date): int
    {
        $items = BookingItem::where('schedule_type', 'once')
            ->where('start_date', $date)
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'once');
    }

    /**
     * Process daily bookings
     */
    protected function processDailyBookings(string $date): int
    {
        $items = BookingItem::where('schedule_type', 'daily')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'daily');
    }

    /**
     * Process weekly bookings
     */
    protected function processWeeklyBookings(string $date, Carbon $targetDate): int
    {
        $dayOfWeek = $targetDate->dayOfWeek;

        $items = BookingItem::where('schedule_type', 'weekly')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereRaw("JSON_EXTRACT(schedule_rule, '$.weekday') = ?", [$dayOfWeek])
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'weekly');
    }

    /**
     * Process monthly same date bookings
     */
    protected function processMonthlySameDateBookings(string $date, Carbon $targetDate): int
    {
        $dayOfMonth = $targetDate->day;

        $items = BookingItem::where('schedule_type', 'monthly_same_date')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereRaw("JSON_EXTRACT(schedule_rule, '$.day') = ?", [$dayOfMonth])
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'monthly_same_date');
    }

    /**
     * Process monthly nakshatra bookings
     * Books on the 2nd occurrence of nakshatra in the Malayalam month
     * Nakshatra must not end before 2.5 hours after sunrise
     */
    protected function processMonthlyNakshatraBookings(string $date, PanchangData $panchang): int
    {
        // Get nakshatra ID for target date (first nakshatra = at sunrise)
        $nakshatraId = $panchang->nakshatra[0]['id'] ?? null;

        if (!$nakshatraId) {
            return 0;
        }

        // Check if nakshatra ends after 2.5 hours from sunrise
        if (!$this->isNakshatraValidForPooja($panchang, $nakshatraId)) {
            $this->line("  Nakshatra {$nakshatraId} ends before 2.5 hours after sunrise - skipping");
            return 0;
        }

        // Check if this is the 2nd occurrence of this nakshatra in the Malayalam month
        $malayalamMonth = $panchang->malayalam_month;
        $malayalamYear = $panchang->malayalam_year;

        // Find all dates in this Malayalam month with the same nakshatra (only valid ones)
        $occurrenceNumber = $this->getNakshatraOccurrenceInMonth($date, $nakshatraId, $malayalamMonth, $malayalamYear);

        if ($occurrenceNumber !== 2) {
            $this->line("  Nakshatra {$nakshatraId} valid occurrence #{$occurrenceNumber} in month - skipping (need 2nd)");
            return 0;
        }

        $this->info("  Found 2nd valid occurrence of nakshatra {$nakshatraId} in Malayalam month {$malayalamMonth}");

        // Find booking items with this nakshatra
        $items = BookingItem::where('schedule_type', 'monthly_nakshatra')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereRaw("JSON_EXTRACT(schedule_rule, '$.nakshatra_id') = ?", [$nakshatraId])
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'monthly_nakshatra');
    }

    /**
     * Check if nakshatra is valid for pooja (ends after 2.5 hours from sunrise)
     */
    protected function isNakshatraValidForPooja(PanchangData $panchang, int $nakshatraId): bool
    {
        // Find the nakshatra in the panchang data
        $nakshatra = collect($panchang->nakshatra)->firstWhere('id', $nakshatraId);

        if (!$nakshatra || empty($nakshatra['end'])) {
            return false;
        }

        // Parse sunrise and nakshatra end time
        $sunrise = Carbon::parse($panchang->sunrise);
        $nakshatraEnd = Carbon::parse($nakshatra['end']);

        // Nakshatra must end after 2.5 hours (150 minutes) from sunrise
        $minEndTime = $sunrise->copy()->addMinutes(150);

        return $nakshatraEnd->greaterThanOrEqualTo($minEndTime);
    }

    /**
     * Get which valid occurrence (1st, 2nd, etc.) of a nakshatra this date is within the Malayalam month
     * Only counts days where nakshatra ends after 2.5 hours from sunrise
     */
    protected function getNakshatraOccurrenceInMonth(string $targetDate, int $nakshatraId, int $malayalamMonth, int $malayalamYear): int
    {
        // Get all dates in this Malayalam month before and including target date
        $dates = PanchangData::where('malayalam_month', $malayalamMonth)
            ->where('malayalam_year', $malayalamYear)
            ->where('date', '<=', $targetDate)
            ->orderBy('date')
            ->get();

        $occurrence = 0;
        foreach ($dates as $panchangDay) {
            $dayNakshatraId = $panchangDay->nakshatra[0]['id'] ?? null;
            if ($dayNakshatraId === $nakshatraId) {
                // Only count if nakshatra is valid (ends after 2.5 hours from sunrise)
                if ($this->isNakshatraValidForPooja($panchangDay, $nakshatraId)) {
                    $occurrence++;
                }
            }
        }

        return $occurrence;
    }

    /**
     * Process monthly Malayalam weekday bookings
     * Books on the first [weekday] of each Malayalam month
     */
    protected function processMonthlyMalayalamWeekdayBookings(string $date, PanchangData $panchang, Carbon $targetDate): int
    {
        $dayOfWeek = $targetDate->dayOfWeek;
        $malayalamDay = $panchang->malayalam_day;

        // Check if this is the first occurrence of this weekday in the Malayalam month
        // Malayalam day should be <= 7 for it to potentially be the first weekday
        if ($malayalamDay > 7) {
            return 0;
        }

        // Verify this is actually the first occurrence by checking previous days
        $isFirstWeekday = $this->isFirstWeekdayInMalayalamMonth($date, $dayOfWeek, $panchang->malayalam_month, $panchang->malayalam_year);

        if (!$isFirstWeekday) {
            return 0;
        }

        $weekdayName = $targetDate->format('l');
        $this->info("  Found first {$weekdayName} of Malayalam month {$panchang->malayalam_month}");

        $items = BookingItem::where('schedule_type', 'monthly_malayalam_weekday')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereRaw("JSON_EXTRACT(schedule_rule, '$.weekday') = ?", [$dayOfWeek])
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'monthly_malayalam_weekday');
    }

    /**
     * Check if this is the first occurrence of a weekday in the Malayalam month
     */
    protected function isFirstWeekdayInMalayalamMonth(string $targetDate, int $weekday, int $malayalamMonth, int $malayalamYear): bool
    {
        // Get all earlier dates in this Malayalam month
        $earlierDates = PanchangData::where('malayalam_month', $malayalamMonth)
            ->where('malayalam_year', $malayalamYear)
            ->where('date', '<', $targetDate)
            ->get();

        foreach ($earlierDates as $panchangDay) {
            $dayOfWeek = Carbon::parse($panchangDay->date)->dayOfWeek;
            if ($dayOfWeek === $weekday) {
                return false; // Found an earlier occurrence
            }
        }

        return true;
    }

    /**
     * Process monthly pooja schedule bookings
     * Books when the pooja's next_pooja_date matches target date
     */
    protected function processMonthlyPoojaScheduleBookings(string $date): int
    {
        // Find poojas with next_pooja_date matching target date
        $poojaIds = Pooja::where('next_pooja_date', $date)->pluck('id');

        if ($poojaIds->isEmpty()) {
            return 0;
        }

        $items = BookingItem::where('schedule_type', 'monthly_pooja_schedule')
            ->whereIn('pooja_id', $poojaIds)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->whereColumn('occurrences_completed', '<', 'occurrences_total')
            ->whereDoesntHave('schedules', fn($q) => $q->where('scheduled_date', $date))
            ->get();

        return $this->createSchedules($items, $date, 'monthly_pooja_schedule');
    }

    /**
     * Create schedules for the given booking items
     */
    protected function createSchedules($items, string $date, string $type): int
    {
        $count = 0;

        foreach ($items as $item) {
            BookingSchedule::create([
                'booking_item_id' => $item->id,
                'scheduled_date' => $date,
                'status' => 'pending',
            ]);

            $count++;

            $this->line("  Created {$type} schedule for booking item #{$item->id}");

            // TODO: Queue notification
            // $this->queueNotification($item, $date);
        }

        return $count;
    }
}
