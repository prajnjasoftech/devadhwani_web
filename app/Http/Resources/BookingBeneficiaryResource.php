<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingBeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_item_id' => $this->booking_item_id,
            'name' => $this->name,
            'nakshathra_id' => $this->nakshathra_id,
            'nakshathra' => $this->whenLoaded('nakshathra', fn() => [
                'id' => $this->nakshathra->id,
                'name' => $this->nakshathra->name,
                'malayalam_name' => $this->nakshathra->malayalam_name,
            ]),
            'gothram' => $this->gothram,
            'notes' => $this->notes,
        ];
    }
}
