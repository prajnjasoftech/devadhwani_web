<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }
}
