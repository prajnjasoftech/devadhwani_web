<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePoojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deity_id' => 'nullable|exists:deities,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'sometimes|required|in:once,daily,weekly,monthly',
            'next_pooja_date' => 'nullable|date',
            'amount' => 'sometimes|required|numeric|min:0',
            'devotee_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
