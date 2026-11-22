<?php

namespace Modules\Payment\Services\SlipVerification\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\SlipVerificationDriverInterface;
use Modules\Payment\Data\ParsedThaiSlipQr;
use Modules\Payment\Data\SlipVerificationResult;
use Modules\Payment\Exceptions\SlipVerificationException;

/**
 * Direct KBank Open API slip verification driver using the merchant's own
 * KBank API Portal credentials (no third-party middleman API).
 */
class KBankOpenApiSlipDriver implements SlipVerificationDriverInterface
{
    /** {@inheritDoc} */
    public function verify(ParsedThaiSlipQr $slip, array $config = []): SlipVerificationResult
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://openapi.kasikornbank.com'), '/');
        $consumerId = (string) ($config['consumer_id'] ?? '');
        $consumerSecret = (string) ($config['consumer_secret'] ?? '');

        if ($consumerId === '' || $consumerSecret === '') {
            throw new SlipVerificationException(422, 'MISSING_BANK_CREDENTIALS', 'KBank Open API credentials are not configured.');
        }

        $tokenResponse = Http::withBasicAuth($consumerId, $consumerSecret)
            ->asForm()
            ->post("{$baseUrl}/v2/oauth/token", ['grant_type' => 'client_credentials']);

        if (! $tokenResponse->successful() || empty($tokenResponse->json('access_token'))) {
            throw new SlipVerificationException(502, 'BANK_AUTH_FAILED', 'Failed to authenticate with KBank Open API.');
        }

        $accessToken = (string) $tokenResponse->json('access_token');

        $verifyResponse = Http::withToken($accessToken)
            ->post("{$baseUrl}/v1/verslip/kbank/verify", [
                'rqUID' => (string) Str::uuid(),
                'rqDt' => now()->toIso8601String(),
                'data' => [
                    'sendingBank' => $slip->sendingBankCode,
                    'transRef' => $slip->transRef,
                ],
            ]);

        if (! $verifyResponse->successful() || empty($verifyResponse->json('data'))) {
            throw new SlipVerificationException(422, 'SLIP_NOT_FOUND', 'Slip transaction could not be verified with KBank Open API.');
        }

        $data = (array) $verifyResponse->json('data');

        return new SlipVerificationResult(
            provider: 'kbank_open_api',
            transRef: (string) ($data['transRef'] ?? $slip->transRef),
            sendingBankCode: (string) ($data['sendingBank'] ?? $slip->sendingBankCode),
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            senderName: isset($data['sender']['displayName']) ? (string) $data['sender']['displayName'] : null,
            receiverAccount: isset($data['receiver']['proxy']['value']) ? (string) $data['receiver']['proxy']['value'] : null,
            rawResponse: $data,
        );
    }
}
