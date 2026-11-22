<?php

namespace Modules\Branch\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ThaiTaxId implements ValidationRule
{
    /**
     * Validate a 13-digit Thai tax identification number including its checksum.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match('/\A\d{13}\z/', $value) !== 1) {
            $fail('branch::messages.thai_tax_id_format')->translate();

            return;
        }

        $sum = 0;
        for ($position = 0; $position < 12; $position++) {
            $sum += (int) $value[$position] * (13 - $position);
        }

        if ((11 - $sum % 11) % 10 !== (int) $value[12]) {
            $fail('branch::messages.thai_tax_id_checksum')->translate();
        }
    }
}
