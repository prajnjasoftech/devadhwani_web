<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,

            // Pooja & Deity
            'pooja_id' => $this->pooja_id,
            'pooja' => $this->whenLoaded('pooja', fn() => [
                'id' => $this->pooja->id,
                'name' => $this->pooja->name,
            ]),
            'deity_id' => $this->deity_id,
            'deity' => $this->whenLoaded('deity', fn() => [
                'id' => $this->deity->id,
                'name' => $this->deity->name,
            ]),

            // Schedule
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'start_date_formatted' => $this->start_date->format('d M Y'),
            'end_date_formatted' => $this->end_date?->format('d M Y'),
            'date_range' => $this->date_range,
            'frequency' => $this->frequency,
            'frequency_label' => $this->frequency_label,
            'monthly_type' => $this->monthly_type,
            'monthly_day' => $this->monthly_day,

            // Pricing
            'unit_amount' => $this->unit_amount,
            'unit_amount_formatted' => '₹' . number_format($this->unit_amount, 2),
            'beneficiary_count' => $this->beneficiary_count,
            'occurrence_count' => $this->occurrence_count,
            'total_amount' => $this->total_amount,
            'total_amount_formatted' => '₹' . number_format($this->total_amount, 2),

            'notes' => $this->notes,
            'status' => $this->status,

            // Beneficiaries
            'beneficiaries' => BookingBeneficiaryResource::collection($this->whenLoaded('beneficiaries')),

            // Schedule summary
            'schedules_count' => $this->whenCounted('schedules'),
            'completed_count' => $this->when(
                $this->relationLoaded('schedules'),
                fn() => $this->schedules->where('status', 'completed')->count()
            ),
            'pending_count' => $this->when(
                $this->relationLoaded('schedules'),
                fn() => $this->schedules->where('status', 'pending')->count()
            ),

            // Schedules
            'schedules' => $this->when(
                $this->relationLoaded('schedules'),
                fn() => $this->schedules->map(fn($schedule) => [
                    'id' => $schedule->id,
                    'scheduled_date' => $schedule->scheduled_date->format('Y-m-d'),
                    'scheduled_date_formatted' => $schedule->scheduled_date->format('d M Y'),
                    'status' => $schedule->status,
                    'completed_at' => $schedule->completed_at?->toISOString(),
                    'completed_at_formatted' => $schedule->completed_at?->format('d M Y h:i A'),
                ])
            ),

            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
