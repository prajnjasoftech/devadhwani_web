<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }
}
