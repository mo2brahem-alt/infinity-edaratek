<?php

namespace App\Rules;

use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SaudiMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! SaudiPhone::isValidMobile($value)) {
            $fail('صيغة :attribute غير صحيحة. استخدم رقم جوال سعودي مثل 05XXXXXXXX.');
        }
    }
}

