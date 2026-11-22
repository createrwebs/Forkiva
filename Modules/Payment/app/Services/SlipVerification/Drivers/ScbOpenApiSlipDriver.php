<?php

namespace Modules\Payment\Services\SlipVerification\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\SlipVerificationDriverInterface;
use Modules\Payment\Data\ParsedThaiSlipQr;
use Modules\Payment\Data\SlipVerificationResult;
use Modules\Payment\Exceptions\SlipVerificationException;

/**
 * Direct SCB Open API slip verification driver using the merchant's own
 * SCB Developers credentials (no third-party middleman API).
 */
class ScbOpenApiSlipDriver implements SlipVerificationDriverInterface
{
    /** {@inheritDoc} */
    public function verify(ParsedThaiSlipQr $slip, array $config = []): SlipVerificationResult
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.partners.scb/partners'), '/');
        $apiKey = (string) ($config['api_key'] ?? '');
        $apiSecret = (string) ($config['api_secret'] ?? '');

        if ($apiKey === '' || $apiSecret === '') {
            throw new SlipVerificationException(422, 'MISSING_BANK_CREDENTIALS', 'SCB Open API credentials are not configured.');
        }

        $tokenResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'resourceOwnerId' => $apiKey,
            'requestUId' => (string) Str::uuid(),
            'accept-language' => 'EN',
        ])->post("{$baseUrl}/v1/oauth/token", [
            'applicationKey' => $apiKey,
            'applicationSecret' => $apiSecret,
        ]);

        if (! $tokenResponse->successful() || empty($tokenResponse->json('data.accessToken'))) {
            throw new SlipVerificationException(502, 'BANK_AUTH_FAILED', 'Failed to authenticate with SCB Open API.');
        }

        $accessToken = (string) $tokenResponse->json('data.accessToken');

        $verifyResponse = Http::withHeaders([
            'authorization' => "Bearer {$accessToken}",
            'resourceOwnerId' => $apiKey,
            'requestUId' => (string) Str::uuid(),
            'accept-language' => 'EN',
        ])->get("{$baseUrl}/v1/payment/billpayment/transactions/{$slip->transRef}", [
            'sendingBank' => $slip->sendingBankCode,
        ]);

        if (! $verifyResponse->successful() || empty($verifyResponse->json('data'))) {
            throw new SlipVerificationException(422, 'SLIP_NOT_FOUND', 'Slip transaction could not be verified with SCB Open API.');
        }

        $data = (array) $verifyResponse->json('data');
        $receiver = $data['receiver']['proxy']['value']
            ?? $data['receiver']['account']['value']
            ?? null;

        return new SlipVerificationResult(
            provider: 'scb_open_api',
            transRef: (string) ($data['transRef'] ?? $slip->transRef),
            sendingBankCode: (string) ($data['sendingBank'] ?? $slip->sendingBankCode),
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            senderName: isset($data['sender']['displayName']) ? (string) $data['sender']['displayName'] : null,
            receiverAccount: $receiver !== null ? (string) $receiver : null,
            rawResponse: $data,
        );
    }
}
