<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20', 'unique:users,contact_number'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'password' => ['required', 'string', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'max:2048'],
            'id_proof_type' => ['nullable', 'in:aadhaar,pan,driving_license'],
            'id_proof_number' => ['nullable', 'string', 'max:100'],
            'id_proof_file' => ['nullable', 'file', 'max:2048'],
        ];

        if (auth()->user()->isPlatformAdmin()) {
            $rules['temple_id'] = ['nullable', 'exists:temples,id'];
            $rules['user_type'] = ['required', 'in:platform_admin,temple_user'];
        }

        return $rules;
    }
}
