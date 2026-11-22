<?php

namespace Modules\Product\Services\Retail;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Decodes and builds Thai retail EAN-13 weighted / price-embedded barcodes (prefixes 20-29).
 */
class RetailBarcodeService
{
    /**
     * Build a 13-digit weighted/price-embedded EAN-13 barcode:
     * PP (2-digit prefix 20-29) + SSSSS (5-digit SKU) + VVVVV (5-digit value * 100) + C (check digit).
     */
    public function buildWeightedEan13(string $prefix, string $skuCode, string $value): string
    {
        if (preg_match('/\A2\d\z/', $prefix) !== 1 || preg_match('/\A\d{5}\z/', $skuCode) !== 1) {
            throw new InvalidArgumentException('Prefix must be 20-29 and SKU code must be 5 digits.');
        }

        $minor = BigDecimal::of($value)
            ->toScale(2, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toBigInteger()
            ->toInt();

        if ($minor < 0 || $minor > 99999) {
            throw new InvalidArgumentException('Embedded value out of range for 5-digit EAN-13.');
        }

        $body = $prefix.$skuCode.str_pad((string) $minor, 5, '0', STR_PAD_LEFT);

        return $body.$this->ean13CheckDigit($body);
    }

    /**
     * Decode a weighted/price-embedded EAN-13 barcode, returning null if not a valid 20-29 EAN-13.
     *
     * @return array{prefix: string, sku_code: string, embedded_value: string}|null
     */
    public function decodeWeightedEan13(string $barcode): ?array
    {
        $barcode = trim($barcode);

        if (preg_match('/\A2\d{12}\z/', $barcode) !== 1) {
            return null;
        }

        $body = substr($barcode, 0, 12);
        if ($barcode[12] !== $this->ean13CheckDigit($body)) {
            return null;
        }

        $prefix = substr($barcode, 0, 2);
        $skuCode = substr($barcode, 2, 5);
        $rawMinor = (int) substr($barcode, 7, 5);
        $value = (string) BigDecimal::of($rawMinor)->dividedBy(100, 2, RoundingMode::UNNECESSARY);

        return [
            'prefix' => $prefix,
            'sku_code' => $skuCode,
            'embedded_value' => $value,
        ];
    }

    private function ean13CheckDigit(string $body12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $body12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
