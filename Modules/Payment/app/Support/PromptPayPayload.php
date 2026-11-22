<?php

namespace Modules\Payment\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Build PromptPay (EMVCo) QR payloads for Thai bank-app collection.
 *
 * The payload is a deterministic TLV string; amounts must already be rounded
 * to THB subunits by the caller so the QR never implies a different charge.
 */
final class PromptPayPayload
{
    private const AID = 'A000000677010111';

    /**
     * Generate a payload for a Thai phone number (0xxxxxxxxx) or 13-digit
     * national/tax ID. A null amount produces a static reusable QR.
     */
    public static function generate(string $target, ?string $amount = null): string
    {
        $payload = self::tlv('00', '01')
            .self::tlv('01', $amount === null ? '11' : '12')
            .self::tlv('29', self::merchantAccountInfo($target))
            .self::tlv('53', '764');

        if ($amount !== null) {
            $payload .= self::tlv('54', self::formatAmount($amount));
        }

        $payload .= self::tlv('58', 'TH').'6304';

        return $payload.self::crc16($payload);
    }

    /**
     * Encode the PromptPay merchant account information sub-tags.
     */
    private static function merchantAccountInfo(string $target): string
    {
        $normalized = preg_replace('/[\s\-]/', '', $target) ?? '';

        if (preg_match('/\A0\d{9}\z/', $normalized) === 1) {
            return self::tlv('00', self::AID).self::tlv('01', '0066'.substr($normalized, 1));
        }

        if (preg_match('/\A\d{13}\z/', $normalized) === 1) {
            return self::tlv('00', self::AID).self::tlv('02', $normalized);
        }

        throw new InvalidArgumentException('PromptPay target must be a Thai phone number or a 13-digit national/tax ID.');
    }

    /**
     * Format the amount with exactly two decimals without float rounding drift.
     */
    private static function formatAmount(string $amount): string
    {
        try {
            $decimal = BigDecimal::of($amount);
        } catch (MathException) {
            throw new InvalidArgumentException('PromptPay amount must be a positive numeric value.');
        }

        if ($decimal->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('PromptPay amount must be a positive numeric value.');
        }

        try {
            return (string) $decimal->toScale(2, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw new InvalidArgumentException('PromptPay amount must not exceed two decimal places.');
        }
    }

    private static function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * CRC-16/CCITT-FALSE over the payload including the trailing "6304".
     */
    private static function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        for ($index = 0; $index < strlen($payload); $index++) {
            $crc ^= ord($payload[$index]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
