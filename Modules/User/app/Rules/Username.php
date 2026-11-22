<?php

namespace Modules\User\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Detect phone formatting without depending on a country or an assigned number.
        $isPhone = preg_match('/\A\+?[\p{Nd}\s().\-]+\z/u', trim($value)) === 1
            && preg_match('/\p{Nd}/u', $value) === 1;

        if (str_contains($value, '@') || $isPhone) {
            $fail('user::messages.username_not_email_or_phone')->translate();
        }
    }
}
