<?php

namespace Modules\Order\Support;

use JsonException;

final class CheckoutPricingFingerprint
{
    /**
     * Bind the payable breakdown, excluding catalogue metadata and localized display strings.
     * Input selections and checkout ownership are verified independently by CheckoutConfirmation.
     *
     * @throws JsonException
     */
    public static function hash(array $pricing): string
    {
        $lines = array_map(fn (array $line) => [
            'id' => (string) ($line['line_id'] ?? $line['id']),
            'qty' => $line['qty'],
            'unitPrice' => self::money($line['unitPrice']),
            'subtotal' => self::money($line['subtotal']),
            'taxTotal' => self::money($line['taxTotal']),
            'total' => self::money($line['total']),
            'taxes' => self::adjustments($line['taxes'] ?? []),
        ], $pricing['items'] ?? []);
        usort($lines, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return hash('sha256', json_encode(CheckoutInput::canonical([
            'items' => $lines,
            'quantity' => $pricing['quantity'] ?? null,
            'subtotal' => self::money($pricing['subTotal']),
            'total' => self::money($pricing['total']),
            'taxes' => self::adjustments($pricing['taxes'] ?? []),
            'charges' => self::adjustments($pricing['charges'] ?? []),
            'discount' => [
                'id' => $pricing['discount']['id'] ?? null,
                'type' => $pricing['discount']['type']['id'] ?? null,
                'value' => isset($pricing['discount']['value']) ? self::money($pricing['discount']['value']) : null,
            ],
        ]), JSON_THROW_ON_ERROR));
    }

    /**
     * Compare amounts at their currency precision; binary float noise is not a price change.
     */
    private static function money(array $money): array
    {
        $precision = (int) $money['precision'];

        return [
            'currency' => $money['currency'],
            'precision' => $precision,
            'amount' => number_format((float) $money['amount'], $precision, '.', ''),
        ];
    }

    /**
     * Preserve individual taxes and fees even when their changes cancel out in the total.
     */
    private static function adjustments(array $rows): array
    {
        $rows = array_map(fn (array $row) => [
            'id' => (string) ($row['code'] ?? $row['id']),
            'rate' => isset($row['rate']) ? (float) $row['rate'] : null,
            'type' => $row['type'] ?? null,
            'scope' => $row['scope'] ?? null,
            'amount' => self::money($row['amount']),
        ], $rows);
        usort($rows, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return $rows;
    }
}
