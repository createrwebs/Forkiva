<?php

namespace Modules\Payment\Tests\Unit;

use InvalidArgumentException;
use Modules\Payment\Support\PromptPayPayload;
use PHPUnit\Framework\TestCase;

class PromptPayPayloadTest extends TestCase
{
    public function test_phone_target_is_normalized_to_international_format(): void
    {
        $payload = PromptPayPayload::generate('0812345678');

        $this->assertStringStartsWith('000201', $payload);
        $this->assertStringContainsString('010211', $payload);
        $this->assertStringContainsString('0016A000000677010111', $payload);
        $this->assertStringContainsString('01130066812345678', $payload);
        $this->assertStringContainsString('5303764', $payload);
        $this->assertStringContainsString('53037645802TH', $payload);
        $this->assertTrue($this->hasValidCrc($payload));
    }

    public function test_tax_id_target_uses_the_national_id_sub_tag(): void
    {
        $payload = PromptPayPayload::generate('1234567890123');

        $this->assertStringContainsString('02131234567890123', $payload);
        $this->assertTrue($this->hasValidCrc($payload));
    }

    public function test_amount_produces_a_dynamic_qr_with_two_decimals(): void
    {
        $payload = PromptPayPayload::generate('0812345678', '100.5');

        $this->assertStringContainsString('010212', $payload);
        $this->assertStringContainsString('5406100.50', $payload);
        $this->assertTrue($this->hasValidCrc($payload));
    }

    public function test_formatted_target_separators_are_accepted(): void
    {
        $this->assertSame(
            PromptPayPayload::generate('0812345678'),
            PromptPayPayload::generate('081-234-5678')
        );
    }

    public function test_invalid_targets_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PromptPayPayload::generate('12345');
    }

    public function test_non_positive_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PromptPayPayload::generate('0812345678', '0');
    }

    public function test_amounts_with_excess_precision_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PromptPayPayload::generate('0812345678', '10.555');
    }

    public function test_non_numeric_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PromptPayPayload::generate('0812345678', 'abc');
    }

    /**
     * Independently recompute CRC-16/CCITT-FALSE so a regression in the
     * generator's checksum cannot pass unnoticed.
     */
    private function hasValidCrc(string $payload): bool
    {
        $body = substr($payload, 0, -4);
        $expected = strtoupper(substr($payload, -4));
        $crc = 0xFFFF;

        foreach (str_split($body) as $character) {
            $crc ^= ord($character) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT)) === $expected;
    }
}
