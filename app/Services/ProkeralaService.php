<?php

namespace App\Services;

use App\Models\PanchangData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProkeralaService
{
    protected string $baseUrl = 'https://api.prokerala.com/v2/';

    // Malayalam month names
    private const MALAYALAM_MONTHS = [
        1 => 'ചിങ്ങം',
        2 => 'കന്നി',
        3 => 'തുലാം',
        4 => 'വൃശ്ചികം',
        5 => 'ധനു',
        6 => 'മകരം',
        7 => 'കുംഭം',
        8 => 'മീനം',
        9 => 'മേടം',
        10 => 'ഇടവം',
        11 => 'മിഥുനം',
        12 => 'കർക്കടകം',
    ];

    protected function http()
    {
        if (app()->environment('local')) {
            return Http::withoutVerifying();
        }
        return Http::withOptions([]);
    }

    protected function getAccessToken(): string
    {
        $cacheKey = 'prokerala_access_token';

        return Cache::remember($cacheKey, 3500, function () {
            $response = $this->http()->asForm()->post('https://api.prokerala.com/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.prokerala.client_id'),
                'client_secret' => config('services.prokerala.client_secret'),
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Get Panchang data - checks DB first, then API
     */
    public function getDayDetails(string $date, float $latitude, float $longitude): array
    {
        // Check database first
        $cached = PanchangData::where('date', $date)->first();

        if ($cached) {
            return $cached->toApiResponse();
        }

        // Fetch Panchang from API (20 credits)
        $panchang = $this->fetchPanchangFromApi($date, $latitude, $longitude);

        // Calculate Malayalam date locally (saves 2000-4000 credits per call!)
        $malayalamDate = $this->getApproximateMalayalamDate($date);

        $data = array_merge(['malayalam_date' => $malayalamDate], $panchang);

        // Save to database
        PanchangData::createFromApiResponse($date, $data);

        return $data;
    }

    /**
     * Get Panchang from API (Advanced endpoint for Rahu/Gulika/Yamaganda)
     */
    protected function fetchPanchangFromApi(string $date, float $latitude, float $longitude): array
    {
        $token = $this->getAccessToken();
        $datetime = $date . 'T06:00:00+05:30';

        $response = $this->http()->withToken($token)->get($this->baseUrl . 'astrology/panchang/advanced', [
            'datetime' => $datetime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'coordinates' => "{$latitude},{$longitude}",
            'timezone' => 'Asia/Kolkata',
            'ayanamsa' => 1,
            'la' => 'ml',
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch Panchang: ' . $response->body());
        }

        return $this->formatPanchangResult($response->json('data'));
    }

    /**
     * Calculate Malayalam date locally (no API call)
     * Based on solar calendar - Kolla Varsham
     */
    public function getApproximateMalayalamDate(string $date): array
    {
        // Check if we have it in database first
        $cached = PanchangData::where('date', $date)->first();

        if ($cached && $cached->malayalam_day) {
            return [
                'day' => $cached->malayalam_day,
                'month' => $cached->malayalam_month,
                'month_name' => $cached->malayalam_month_name,
                'year' => $cached->malayalam_year,
                'year_name' => 'കൊല്ലവർഷം',
            ];
        }

        $inputDate = new \DateTime($date);

        // Malayalam month start dates for a typical year
        // Format: [month_number, gregorian_month, gregorian_day]
        // These are approximate and can vary by 1-2 days
        $monthStartsInYear = [
            ['ml' => 9, 'g_month' => 4, 'g_day' => 14],   // Medam - Apr 14
            ['ml' => 10, 'g_month' => 5, 'g_day' => 15],  // Edavam - May 15
            ['ml' => 11, 'g_month' => 6, 'g_day' => 15],  // Mithunam - Jun 15
            ['ml' => 12, 'g_month' => 7, 'g_day' => 17],  // Karkidakam - Jul 17
            ['ml' => 1, 'g_month' => 8, 'g_day' => 17],   // Chingam - Aug 17
            ['ml' => 2, 'g_month' => 9, 'g_day' => 17],   // Kanni - Sep 17
            ['ml' => 3, 'g_month' => 10, 'g_day' => 17],  // Thulam - Oct 17
            ['ml' => 4, 'g_month' => 11, 'g_day' => 16],  // Vrischikam - Nov 16
            ['ml' => 5, 'g_month' => 12, 'g_day' => 16],  // Dhanu - Dec 16
            ['ml' => 6, 'g_month' => 1, 'g_day' => 14],   // Makaram - Jan 14
            ['ml' => 7, 'g_month' => 2, 'g_day' => 13],   // Kumbham - Feb 13
            ['ml' => 8, 'g_month' => 3, 'g_day' => 15],   // Meenam - Mar 15
        ];

        $year = (int) $inputDate->format('Y');
        $month = (int) $inputDate->format('n');
        $day = (int) $inputDate->format('j');

        // Build list of month boundaries around the input date
        $boundaries = [];
        for ($y = $year - 1; $y <= $year + 1; $y++) {
            foreach ($monthStartsInYear as $ms) {
                $boundaries[] = [
                    'ml_month' => $ms['ml'],
                    'date' => new \DateTime("{$y}-{$ms['g_month']}-{$ms['g_day']}"),
                    'year' => $y,
                ];
            }
        }

        // Sort by date
        usort($boundaries, fn($a, $b) => $a['date'] <=> $b['date']);

        // Find which month the input date falls in
        $malayalamMonth = 1;
        $malayalamDay = 1;
        $boundaryYear = $year;

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $current = $boundaries[$i];
            $next = $boundaries[$i + 1];

            if ($inputDate >= $current['date'] && $inputDate < $next['date']) {
                $malayalamMonth = $current['ml_month'];
                $diff = $current['date']->diff($inputDate);
                $malayalamDay = $diff->days + 1;
                $boundaryYear = $current['year'];
                break;
            }
        }

        // Calculate Kolla Varsham
        // Kolla Varsham year starts with Chingam (mid-August)
        // 2026 Aug 17 = Chingam 1, 1202
        // So for dates before Aug 17, 2026 = 1201, after = 1202
        $kollaVarsham = $year - 825;
        if ($month < 8 || ($month === 8 && $day < 17)) {
            $kollaVarsham--;
        }

        return [
            'day' => $malayalamDay,
            'month' => $malayalamMonth,
            'month_name' => self::MALAYALAM_MONTHS[$malayalamMonth],
            'year' => $kollaVarsham,
            'year_name' => 'കൊല്ലവർഷം',
        ];
    }

    /**
     * Format Panchang API result (Advanced endpoint)
     */
    protected function formatPanchangResult(?array $data): array
    {
        if (!$data) {
            return [
                'sunrise' => null,
                'sunset' => null,
                'moonrise' => null,
                'moonset' => null,
                'vaara' => null,
                'tithi' => null,
                'nakshatra' => null,
                'yoga' => null,
                'karana' => null,
                'auspicious' => [],
                'inauspicious' => [],
            ];
        }

        $result = [
            'sunrise' => $data['sunrise'] ?? null,
            'sunset' => $data['sunset'] ?? null,
            'moonrise' => $data['moonrise'] ?? null,
            'moonset' => $data['moonset'] ?? null,
            'vaara' => $data['vaara'] ?? null,
            'tithi' => $data['tithi'] ?? null,
            'nakshatra' => $data['nakshatra'] ?? null,
            'yoga' => $data['yoga'] ?? null,
            'karana' => $data['karana'] ?? null,
            'auspicious' => [],
            'inauspicious' => [],
        ];

        // Parse auspicious periods from advanced API
        if (isset($data['auspicious_period']) && is_array($data['auspicious_period'])) {
            foreach ($data['auspicious_period'] as $period) {
                $key = $this->getPeriodKey($period['id']);
                $result['auspicious'][$key] = [
                    'name' => $period['name'],
                    'start' => $period['period'][0]['start'] ?? null,
                    'end' => $period['period'][0]['end'] ?? null,
                ];
            }
        }

        // Parse inauspicious periods from advanced API
        // IDs: 4=Rahu, 5=Yamaganda, 6=Gulika, 7=Dur Muhurat, 8=Varjyam
        if (isset($data['inauspicious_period']) && is_array($data['inauspicious_period'])) {
            foreach ($data['inauspicious_period'] as $period) {
                $key = $this->getPeriodKey($period['id']);
                $result['inauspicious'][$key] = [
                    'name' => $period['name'],
                    'start' => $period['period'][0]['start'] ?? null,
                    'end' => $period['period'][0]['end'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Get period key from API ID
     */
    protected function getPeriodKey(int $id): string
    {
        return match ($id) {
            1 => 'abhijit_muhurat',
            3 => 'brahma_muhurat',
            4 => 'rahu_kaal',
            5 => 'yamaganda_kaal',
            6 => 'gulika_kaal',
            7 => 'dur_muhurat',
            8 => 'varjyam',
            default => 'period_' . $id,
        };
    }
}
