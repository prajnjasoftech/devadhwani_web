<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'booking_date_formatted' => $this->booking_date->format('d M Y'),

            // Contact
            'contact_name' => $this->contact_name,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'contact_address' => $this->contact_address,
            'prasadam_required' => $this->prasadam_required,

            // Amounts
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_amount' => $this->balance_amount,
            'total_amount_formatted' => '₹' . number_format($this->total_amount, 2),
            'paid_amount_formatted' => '₹' . number_format($this->paid_amount, 2),
            'balance_amount_formatted' => '₹' . number_format($this->balance_amount, 2),

            // Status
            'payment_status' => $this->payment_status,
            'payment_status_label' => ucfirst($this->payment_status),
            'booking_status' => $this->booking_status,
            'booking_status_label' => ucfirst($this->booking_status),

            'notes' => $this->notes,

            // Relationships
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
            'payments' => BookingPaymentResource::collection($this->whenLoaded('payments')),
            'items_count' => $this->whenCounted('items'),

            // Creator
            'created_by' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            // Cancellation
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
