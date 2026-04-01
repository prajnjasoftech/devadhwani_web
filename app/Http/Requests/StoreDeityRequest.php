<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sanskrit_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'deity_type' => 'required|in:main,sub,upadevata',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
