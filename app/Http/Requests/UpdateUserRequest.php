<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:20', Rule::unique('users', 'contact_number')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'password' => ['nullable', 'string', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'max:2048'],
            'id_proof_type' => ['nullable', 'in:aadhaar,pan,driving_license'],
            'id_proof_number' => ['nullable', 'string', 'max:100'],
            'id_proof_file' => ['nullable', 'file', 'max:2048'],
        ];

        if (auth()->user()->isPlatformAdmin()) {
            $rules['temple_id'] = ['nullable', 'exists:temples,id'];
            $rules['user_type'] = ['sometimes', 'in:platform_admin,temple_user'];
        }

        return $rules;
    }
}
