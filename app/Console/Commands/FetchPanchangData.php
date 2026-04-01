<?php

namespace App\Console\Commands;

use App\Models\PanchangData;
use App\Services\ProkeralaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchPanchangData extends Command
{
    protected $signature = 'panchang:fetch
                            {--all : Fetch all missing dates at once}
                            {--days= : Number of days ahead to check}
                            {--from= : Start date (Y-m-d format)}';

    protected $description = 'Fetch Panchang data for the next missing date';

    public function __construct(protected ProkeralaService $prokeralaService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('panchang.fetch_days', 500));
        $fromDate = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : Carbon::today();

        $latitude = (float) config('panchang.default_latitude', 10.5276);
        $longitude = (float) config('panchang.default_longitude', 76.2144);

        return $this->option('all')
            ? $this->fetchAllMissing($fromDate, $days, $latitude, $longitude)
            : $this->fetchOneMissing($fromDate, $days, $latitude, $longitude);
    }

    protected function fetchOneMissing(Carbon $fromDate, int $days, float $latitude, float $longitude): int
    {
        $endDate = $fromDate->copy()->addDays($days - 1);

        // Get all existing dates in range with single query
        $existingDates = PanchangData::whereBetween('date', [$fromDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip()
            ->all();

        // Find first missing date
        $missingDate = null;
        for ($i = 0; $i < $days; $i++) {
            $dateStr = $fromDate->copy()->addDays($i)->format('Y-m-d');
            if (!isset($existingDates[$dateStr])) {
                $missingDate = $dateStr;
                break;
            }
        }

        if (!$missingDate) {
            $this->info("All {$days} days from {$fromDate->format('Y-m-d')} already have data.");
            return Command::SUCCESS;
        }

        $this->info("Fetching: {$missingDate}");

        try {
            $this->prokeralaService->getDayDetails($missingDate, $latitude, $longitude);
            $this->info("Saved: {$missingDate}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed {$missingDate}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function fetchAllMissing(Carbon $fromDate, int $days, float $latitude, float $longitude): int
    {
        $endDate = $fromDate->copy()->addDays($days - 1);

        // Get all existing dates in single query
        $existingDates = PanchangData::whereBetween('date', [$fromDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip()
            ->all();

        // Build list of missing dates
        $missingDates = [];
        for ($i = 0; $i < $days; $i++) {
            $dateStr = $fromDate->copy()->addDays($i)->format('Y-m-d');
            if (!isset($existingDates[$dateStr])) {
                $missingDates[] = $dateStr;
            }
        }

        $total = count($missingDates);
        if ($total === 0) {
            $this->info("All {$days} days already have data.");
            return Command::SUCCESS;
        }

        $this->info("Fetching {$total} missing dates...");

        $fetched = 0;
        $errors = 0;
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($missingDates as $dateStr) {
            try {
                $this->prokeralaService->getDayDetails($dateStr, $latitude, $longitude);
                $fetched++;
                usleep(300000); // 300ms delay
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed {$dateStr}: " . substr($e->getMessage(), 0, 50));
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Count'], [
            ['Missing', $total],
            ['Fetched', $fetched],
            ['Errors', $errors],
        ]);

        return Command::SUCCESS;
    }
}
