<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Contact Details (validated in BookingService based on conditions)
            'contact_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'contact_address' => 'nullable|string',
            'prasadam_required' => 'boolean',
            'booking_date' => 'nullable|date',
            'notes' => 'nullable|string',

            // Items (at least one required)
            'items' => 'required|array|min:1',
            'items.*.pooja_id' => 'required|exists:poojas,id',
            'items.*.deity_id' => 'nullable|exists:deities,id',
            'items.*.start_date' => 'required|date',
            'items.*.end_date' => 'nullable|date|after_or_equal:items.*.start_date',
            'items.*.frequency' => 'required|in:once,daily,weekly,monthly',
            'items.*.weekly_day' => 'nullable|integer|min:0|max:6',
            'items.*.monthly_type' => 'nullable|in:by_date,by_nakshathra',
            'items.*.monthly_day' => 'nullable|integer|min:1|max:31',
            'items.*.unit_amount' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:1', // For poojas without devotee requirement
            'items.*.notes' => 'nullable|string',

            // Beneficiaries per item (optional, validated in BookingService based on devotee_required)
            'items.*.beneficiaries' => 'sometimes|array',
            'items.*.beneficiaries.*.name' => 'required_with:items.*.beneficiaries|string|max:255',
            'items.*.beneficiaries.*.nakshathra_id' => 'nullable|exists:nakshathras,id',
            'items.*.beneficiaries.*.gothram' => 'nullable|string|max:100',
            'items.*.beneficiaries.*.notes' => 'nullable|string',

            // Initial Payment
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card,upi,bank_transfer,other',
            'account_id' => 'nullable|exists:accounts,id',
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one pooja item is required',
            'items.*.pooja_id.required' => 'Please select a pooja',
            'items.*.start_date.required' => 'Start date is required',
            'items.*.beneficiaries.required' => 'At least one beneficiary is required for each pooja',
            'items.*.beneficiaries.*.name.required' => 'Beneficiary name is required',
        ];
    }
}
