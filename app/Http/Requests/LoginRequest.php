<?php

namespace App\Http\Requests;

use App\Rules\IndianMobile;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_number' => ['required', 'string', new IndianMobile],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.required' => 'Contact number is required',
            'password.required' => 'Password is required',
        ];
    }
}
