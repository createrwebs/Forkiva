<?php

namespace Modules\Payment\Support;

use InvalidArgumentException;
use Modules\Payment\Data\ParsedThaiSlipQr;

/**
 * Self-hosted Bank of Thailand (BOT) Slip Verify Mini-QR parser & builder.
 *
 * Implements EMVCo TLV decoding and CRC-16/CCITT-FALSE verification without
 * any third-party slip verification service dependency.
 */
final class ThaiSlipQrParser
{
    private const BOT_SLIP_API_ID = '000001';

    /** @var array<string, string> */
    private const BANK_CODES = [
        '002' => 'BBL',
        '004' => 'KBANK',
        '006' => 'KTB',
        '011' => 'TTB',
        '014' => 'SCB',
        '017' => 'CITI',
        '020' => 'SCBT',
        '022' => 'CIMBT',
        '024' => 'UOB',
        '025' => 'BAY',
        '030' => 'GSB',
        '033' => 'GHB',
        '034' => 'BAAC',
        '066' => 'ISBT',
        '067' => 'TISCO',
        '069' => 'KKP',
        '070' => 'ICBC',
        '071' => 'TCRB',
        '073' => 'LHB',
    ];

    /**
     * Parse and validate a Thai bank transfer slip Mini-QR payload.
     */
    public static function parse(string $payload): ParsedThaiSlipQr
    {
        $payload = trim($payload);

        if (strlen($payload) < 20) {
            throw new InvalidArgumentException('Invalid Thai slip Mini-QR payload length.');
        }

        $topTags = self::decodeTlv($payload);

        if (! isset($topTags['91']) || strlen($topTags['91']) !== 4) {
            throw new InvalidArgumentException('Missing CRC tag (91) in Thai slip Mini-QR payload.');
        }

        $bodyWithoutCrc = substr($payload, 0, -4);
        $expectedCrc = self::crc16($bodyWithoutCrc);

        if (strtoupper($topTags['91']) !== $expectedCrc) {
            throw new InvalidArgumentException('Invalid CRC-16 checksum in Thai slip Mini-QR payload.');
        }

        $countryCode = $topTags['51'] ?? '';
        if ($countryCode !== 'TH') {
            throw new InvalidArgumentException('Unsupported slip country code; expected TH.');
        }

        if (! isset($topTags['00'])) {
            throw new InvalidArgumentException('Missing BOT slip metadata tag (00).');
        }

        $meta = self::decodeTlv($topTags['00']);
        $apiId = $meta['00'] ?? '';
        $sendingBankCode = $meta['01'] ?? '';
        $transRef = $meta['02'] ?? '';

        if ($apiId !== self::BOT_SLIP_API_ID) {
            throw new InvalidArgumentException('Invalid BOT slip API identifier.');
        }

        if (preg_match('/\A\d{3}\z/', $sendingBankCode) !== 1 || $transRef === '') {
            throw new InvalidArgumentException('Missing sending bank code or transaction reference in slip QR.');
        }

        return new ParsedThaiSlipQr(
            apiId: $apiId,
            sendingBankCode: $sendingBankCode,
            sendingBankShortName: self::bankShortName($sendingBankCode),
            transRef: $transRef,
            countryCode: $countryCode,
            rawPayload: $payload,
        );
    }

    /**
     * Build a compliant BOT Mini-QR payload (useful for testing and local verification).
     */
    public static function build(string $sendingBankCode, string $transRef, string $countryCode = 'TH'): string
    {
        $meta = self::tlv('00', self::BOT_SLIP_API_ID)
            .self::tlv('01', $sendingBankCode)
            .self::tlv('02', $transRef);

        $body = self::tlv('00', $meta)
            .self::tlv('51', $countryCode)
            .'9104';

        return $body.self::crc16($body);
    }

    public static function bankShortName(string $bankCode): string
    {
        return self::BANK_CODES[$bankCode] ?? 'UNKNOWN';
    }

    /**
     * @return array<string, string>
     */
    private static function decodeTlv(string $data): array
    {
        $tags = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset + 4 <= $length) {
            $tag = substr($data, $offset, 2);
            $lenStr = substr($data, $offset + 2, 2);

            if (! ctype_digit($lenStr)) {
                throw new InvalidArgumentException("Malformed TLV length at offset {$offset}.");
            }

            $valueLen = (int) $lenStr;
            $offset += 4;

            if ($offset + $valueLen > $length) {
                throw new InvalidArgumentException("Truncated TLV value for tag {$tag}.");
            }

            $tags[$tag] = substr($data, $offset, $valueLen);
            $offset += $valueLen;
        }

        if ($offset !== $length) {
            throw new InvalidArgumentException('Trailing unparsed bytes in TLV payload.');
        }

        return $tags;
    }

    private static function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private static function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        for ($index = 0; $index < strlen($payload); $index++) {
            $crc ^= ord($payload[$index]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
