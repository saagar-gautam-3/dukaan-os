<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\NepaliPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NepalMobilePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! NepaliPhone::isValidMobile($value)) {
            $fail(__('common.invalid_nepal_phone'));
        }
    }
}
