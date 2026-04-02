<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndianMobile implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Indian mobile numbers: 10 digits, starting with 6, 7, 8, or 9
        if (!preg_match('/^[6-9]\d{9}$/', $value)) {
            $fail('The :attribute must be a valid 10-digit Indian mobile number.');
        }
    }
}
