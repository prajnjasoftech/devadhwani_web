<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'amount' => $this->amount,
            'amount_formatted' => '₹' . number_format($this->amount, 2),
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'payment_date_formatted' => $this->payment_date->format('d M Y'),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'received_by' => $this->whenLoaded('receiver', fn() => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
