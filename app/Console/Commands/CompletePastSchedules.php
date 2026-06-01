<?php

namespace App\Console\Commands;

use App\Models\BookingSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CompletePastSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedules:complete-past
                            {--date= : Complete schedules up to this date (default: yesterday)}
                            {--dry-run : Show what would be completed without making changes}
                            {--force : Run without confirmation (for cron)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all pending schedules before the specified date as completed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $endDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $dryRun = $this->option('dry-run');

        $this->info("Finding pending schedules up to {$endDate->format('Y-m-d')}...");

        $query = BookingSchedule::where('status', 'pending')
            ->where('scheduled_date', '<=', $endDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No pending schedules found to complete.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} pending schedule(s).");

        if ($dryRun) {
            $this->warn('Dry run mode - no changes made.');

            // Show sample of what would be completed
            $samples = $query->with(['bookingItem.pooja', 'bookingItem.booking'])
                ->limit(10)
                ->get();

            $this->table(
                ['ID', 'Date', 'Pooja', 'Booking #'],
                $samples->map(fn ($s) => [
                    $s->id,
                    $s->scheduled_date->format('Y-m-d'),
                    $s->bookingItem?->pooja?->name ?? 'N/A',
                    $s->bookingItem?->booking?->booking_number ?? 'N/A',
                ])
            );

            if ($count > 10) {
                $this->info("... and " . ($count - 10) . " more.");
            }

            return Command::SUCCESS;
        }

        // Confirm before proceeding (skip if --force)
        if (!$this->option('force') && !$this->confirm("Do you want to mark {$count} schedule(s) as completed?")) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Update all pending schedules
        $updated = BookingSchedule::where('status', 'pending')
            ->where('scheduled_date', '<=', $endDate)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => 'Auto-completed by system',
            ]);

        $this->info("Successfully marked {$updated} schedule(s) as completed.");

        return Command::SUCCESS;
    }
}
