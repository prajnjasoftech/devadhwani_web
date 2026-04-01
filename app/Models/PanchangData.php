<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanchangData extends Model
{
    protected $table = 'panchang_data';

    protected $fillable = [
        'date',
        'malayalam_day',
        'malayalam_month',
        'malayalam_month_name',
        'malayalam_year',
        'sunrise',
        'sunset',
        'moonrise',
        'moonset',
        'vaara',
        'tithi',
        'nakshatra',
        'yoga',
        'karana',
        'auspicious',
        'inauspicious',
    ];

    protected $casts = [
        'date' => 'date',
        'tithi' => 'array',
        'nakshatra' => 'array',
        'yoga' => 'array',
        'karana' => 'array',
        'auspicious' => 'array',
        'inauspicious' => 'array',
    ];

    /**
     * Get formatted data for API response
     */
    public function toApiResponse(): array
    {
        return [
            'malayalam_date' => [
                'day' => $this->malayalam_day,
                'month' => $this->malayalam_month,
                'month_name' => $this->malayalam_month_name,
                'year' => $this->malayalam_year,
                'year_name' => 'കൊല്ലവർഷം',
            ],
            'sunrise' => $this->sunrise,
            'sunset' => $this->sunset,
            'moonrise' => $this->moonrise,
            'moonset' => $this->moonset,
            'vaara' => $this->vaara,
            'tithi' => $this->tithi,
            'nakshatra' => $this->nakshatra,
            'yoga' => $this->yoga,
            'karana' => $this->karana,
            'auspicious' => $this->auspicious ?? [],
            'inauspicious' => $this->inauspicious ?? [],
        ];
    }

    /**
     * Create from API response
     */
    public static function createFromApiResponse(string $date, array $data): self
    {
        return self::updateOrCreate(
            ['date' => $date],
            [
                'malayalam_day' => $data['malayalam_date']['day'] ?? null,
                'malayalam_month' => $data['malayalam_date']['month'] ?? null,
                'malayalam_month_name' => $data['malayalam_date']['month_name'] ?? null,
                'malayalam_year' => $data['malayalam_date']['year'] ?? null,
                'sunrise' => $data['sunrise'] ?? null,
                'sunset' => $data['sunset'] ?? null,
                'moonrise' => $data['moonrise'] ?? null,
                'moonset' => $data['moonset'] ?? null,
                'vaara' => $data['vaara'] ?? null,
                'tithi' => $data['tithi'] ?? null,
                'nakshatra' => $data['nakshatra'] ?? null,
                'yoga' => $data['yoga'] ?? null,
                'karana' => $data['karana'] ?? null,
                'auspicious' => $data['auspicious'] ?? [],
                'inauspicious' => $data['inauspicious'] ?? [],
            ]
        );
    }
}
