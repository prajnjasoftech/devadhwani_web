<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTempleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templeId = $this->route('temple');

        return [
            'temple_name' => ['sometimes', 'string', 'max:255'],
            'contact_person_name' => ['sometimes', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:20', Rule::unique('temples', 'contact_number')->ignore($templeId)],
            'alternate_contact_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'district' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'id_proof_type' => ['nullable', 'in:aadhaar,pan,driving_license'],
            'id_proof_number' => ['nullable', 'string', 'max:100'],
            'id_proof_file' => ['nullable', 'file', 'max:2048'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];
    }
}
