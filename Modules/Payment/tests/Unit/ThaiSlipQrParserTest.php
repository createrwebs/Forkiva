<?php

namespace Modules\Payment\Tests\Unit;

use InvalidArgumentException;
use Modules\Payment\Support\ThaiSlipQrParser;
use PHPUnit\Framework\TestCase;

class ThaiSlipQrParserTest extends TestCase
{
    public function test_parses_valid_bot_mini_qr_slip_payload(): void
    {
        $payload = ThaiSlipQrParser::build('014', '202611220140000123456');

        $parsed = ThaiSlipQrParser::parse($payload);

        $this->assertSame('000001', $parsed->apiId);
        $this->assertSame('014', $parsed->sendingBankCode);
        $this->assertSame('SCB', $parsed->sendingBankShortName);
        $this->assertSame('202611220140000123456', $parsed->transRef);
        $this->assertSame('TH', $parsed->countryCode);
    }

    public function test_rejects_tampered_crc_checksum(): void
    {
        $payload = ThaiSlipQrParser::build('004', 'REF99887766');
        $tampered = substr($payload, 0, -4).'FFFF';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CRC');

        ThaiSlipQrParser::parse($tampered);
    }

    public function test_rejects_non_th_country_code_or_missing_trans_ref(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThaiSlipQrParser::parse('000201010211');
    }

    public function test_maps_known_thai_bank_codes(): void
    {
        $this->assertSame('BBL', ThaiSlipQrParser::bankShortName('002'));
        $this->assertSame('KBANK', ThaiSlipQrParser::bankShortName('004'));
        $this->assertSame('KTB', ThaiSlipQrParser::bankShortName('006'));
        $this->assertSame('TTB', ThaiSlipQrParser::bankShortName('011'));
        $this->assertSame('SCB', ThaiSlipQrParser::bankShortName('014'));
        $this->assertSame('BAY', ThaiSlipQrParser::bankShortName('025'));
        $this->assertSame('GSB', ThaiSlipQrParser::bankShortName('030'));
        $this->assertSame('UNKNOWN', ThaiSlipQrParser::bankShortName('999'));
    }
}
