<?php

namespace Modules\Payment\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Branch\Models\Branch;
use Modules\Payment\Exceptions\SlipVerificationException;
use Modules\Payment\Services\SlipVerification\SlipVerificationService;
use Modules\Payment\Support\ThaiSlipQrParser;
use Tests\TestCase;

class SlipVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_mini_qr_driver_verifies_valid_slip_and_blocks_duplicate_replay(): void
    {
        $branch = Branch::factory()->create(['promptpay_id' => '0812345678', 'currency' => 'THB']);
        $qr = ThaiSlipQrParser::build('004', 'KBANK202611220001');

        $service = app(SlipVerificationService::class);
        $verification = $service->verify(
            branch: $branch,
            qrPayload: $qr,
            expectedAmount: '250.00',
            driver: 'local_mini_qr'
        );

        $this->assertSame('verified', $verification->status);
        $this->assertSame('004', $verification->sending_bank_code);
        $this->assertSame('KBANK202611220001', $verification->trans_ref);
        $this->assertSame('250.00', (string) $verification->amount);

        // Replay attack with the same slip must be rejected with 409.
        try {
            $service->verify(
                branch: $branch,
                qrPayload: $qr,
                expectedAmount: '250.00',
                driver: 'local_mini_qr'
            );
            $this->fail('Duplicate slip replay should have been rejected.');
        } catch (SlipVerificationException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('DUPLICATE_SLIP', $exception->errorCode);
        }
    }

    public function test_scb_open_api_driver_verifies_exact_amount_and_receiver_without_third_party(): void
    {
        Http::fake([
            '*/v1/oauth/token' => Http::response([
                'data' => ['accessToken' => 'scb-test-token'],
            ], 200),
            '*/v1/payment/billpayment/transactions/*' => Http::response([
                'data' => [
                    'transRef' => 'SCB202611220099',
                    'sendingBank' => '014',
                    'amount' => '349.50',
                    'sender' => ['displayName' => 'Mr. Somchai'],
                    'receiver' => [
                        'proxy' => ['value' => '0812345678'],
                        'account' => ['value' => 'xxx-x-x5678-x'],
                    ],
                ],
            ], 200),
        ]);

        $branch = Branch::factory()->create(['promptpay_id' => '0812345678', 'currency' => 'THB']);
        $qr = ThaiSlipQrParser::build('014', 'SCB202611220099');

        $verification = app(SlipVerificationService::class)->verify(
            branch: $branch,
            qrPayload: $qr,
            expectedAmount: '349.50',
            driver: 'scb_open_api',
            driverConfig: [
                'base_url' => 'https://api-sandbox.partners.scb/partners/sandbox',
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ]
        );

        $this->assertSame('verified', $verification->status);
        $this->assertSame('Mr. Somchai', $verification->sender_name);
        $this->assertSame('349.50', (string) $verification->amount);
    }

    public function test_scb_open_api_driver_rejects_one_satang_amount_mismatch(): void
    {
        Http::fake([
            '*/v1/oauth/token' => Http::response(['data' => ['accessToken' => 'tok']], 200),
            '*/v1/payment/billpayment/transactions/*' => Http::response([
                'data' => [
                    'transRef' => 'SCB202611220100',
                    'sendingBank' => '014',
                    'amount' => '349.49',
                    'receiver' => ['proxy' => ['value' => '0812345678']],
                ],
            ], 200),
        ]);

        $branch = Branch::factory()->create(['promptpay_id' => '0812345678', 'currency' => 'THB']);
        $qr = ThaiSlipQrParser::build('014', 'SCB202611220100');

        try {
            app(SlipVerificationService::class)->verify(
                branch: $branch,
                qrPayload: $qr,
                expectedAmount: '349.50',
                driver: 'scb_open_api',
                driverConfig: [
                    'base_url' => 'https://api-sandbox.partners.scb/partners/sandbox',
                    'api_key' => 'test-key',
                    'api_secret' => 'test-secret',
                ]
            );
            $this->fail('Amount mismatch should have been rejected.');
        } catch (SlipVerificationException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('AMOUNT_MISMATCH', $exception->errorCode);
        }
    }
}
