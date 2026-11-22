<?php

namespace Modules\Branch\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Modules\Branch\Rules\ThaiTaxId;
use Tests\TestCase;

class ThaiTaxIdTest extends TestCase
{
    public function test_a_checksum_valid_thirteen_digit_id_passes(): void
    {
        $validator = Validator::make(
            ['vat_tin' => $this->withValidChecksum('123456789012')],
            ['vat_tin' => [new ThaiTaxId]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_a_wrong_checksum_digit_fails(): void
    {
        $valid = $this->withValidChecksum('123456789012');
        $wrong = substr($valid, 0, 12).((int) $valid[12] === 9 ? '0' : (string) ((int) $valid[12] + 1));

        $validator = Validator::make(['vat_tin' => $wrong], ['vat_tin' => [new ThaiTaxId]]);

        $this->assertTrue($validator->fails());
    }

    public function test_non_thirteen_digit_values_fail(): void
    {
        foreach (['12345678901', '12345678901234', '123456789012a'] as $value) {
            $validator = Validator::make(['vat_tin' => $value], ['vat_tin' => [new ThaiTaxId]]);

            $this->assertTrue($validator->fails(), "Expected failure for {$value}");
        }
    }

    public function test_empty_values_are_left_to_other_rules(): void
    {
        $validator = Validator::make(['vat_tin' => ''], ['vat_tin' => [new ThaiTaxId]]);

        $this->assertTrue($validator->passes());
    }

    private function withValidChecksum(string $twelveDigits): string
    {
        $sum = 0;
        for ($position = 0; $position < 12; $position++) {
            $sum += (int) $twelveDigits[$position] * (13 - $position);
        }

        return $twelveDigits.(string) ((11 - $sum % 11) % 10);
    }
}
