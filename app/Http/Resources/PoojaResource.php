<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoojaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temple_id' => $this->temple_id,
            'deity_id' => $this->deity_id,
            'deity' => $this->whenLoaded('deity', fn() => [
                'id' => $this->deity->id,
                'name' => $this->deity->name,
            ]),
            'name' => $this->name,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'frequency_label' => $this->frequency_label,
            'next_pooja_date' => $this->next_pooja_date?->format('Y-m-d'),
            'next_pooja_date_formatted' => $this->next_pooja_date?->format('d M Y'),
            'amount' => $this->amount,
            'amount_formatted' => '₹' . number_format($this->amount, 2),
            'devotee_required' => $this->devotee_required,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
