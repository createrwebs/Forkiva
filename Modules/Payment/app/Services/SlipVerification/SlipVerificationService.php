<?php

namespace Modules\Payment\Services\SlipVerification;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Modules\Branch\Models\Branch;
use Modules\Payment\Contracts\SlipVerificationDriverInterface;
use Modules\Payment\Exceptions\SlipVerificationException;
use Modules\Payment\Models\SlipVerification;
use Modules\Payment\Services\SlipVerification\Drivers\KBankOpenApiSlipDriver;
use Modules\Payment\Services\SlipVerification\Drivers\LocalMiniQrSlipDriver;
use Modules\Payment\Services\SlipVerification\Drivers\ScbOpenApiSlipDriver;
use Modules\Payment\Support\ThaiSlipQrParser;

class SlipVerificationService
{
    /**
     * Verify a Thai bank transfer slip QR payload, enforce anti-replay and
     * exact BigDecimal amount matching, and persist an immutable audit record.
     *
     * @param  array<string, mixed>  $driverConfig
     */
    public function verify(
        Branch $branch,
        string $qrPayload,
        string $expectedAmount,
        ?int $orderId = null,
        string $driver = 'local_mini_qr',
        array $driverConfig = []
    ): SlipVerification {
        try {
            $parsed = ThaiSlipQrParser::parse($qrPayload);
        } catch (InvalidArgumentException $exception) {
            throw new SlipVerificationException(422, 'INVALID_SLIP_QR', $exception->getMessage());
        }

        $expectedDecimal = BigDecimal::of($expectedAmount)->toScale(2, RoundingMode::HALF_UP);
        if ($expectedDecimal->isLessThanOrEqualTo(0)) {
            throw new SlipVerificationException(422, 'INVALID_AMOUNT', 'Expected amount must be positive.');
        }

        // Fast-path replay check before making any external bank API call.
        $alreadyUsed = SlipVerification::query()
            ->where('branch_id', $branch->id)
            ->where('trans_ref', $parsed->transRef)
            ->exists();

        if ($alreadyUsed) {
            throw new SlipVerificationException(409, 'DUPLICATE_SLIP', 'This transfer slip has already been used.');
        }

        $result = $this->resolveDriver($driver)->verify($parsed, $driverConfig);

        if ($result->amount !== null) {
            $actualDecimal = BigDecimal::of($result->amount)->toScale(2, RoundingMode::HALF_UP);

            if (! $actualDecimal->isEqualTo($expectedDecimal)) {
                throw new SlipVerificationException(
                    422,
                    'AMOUNT_MISMATCH',
                    "Slip amount ({$actualDecimal}) does not match expected amount ({$expectedDecimal})."
                );
            }
        }

        if ($result->receiverAccount !== null && ! empty($branch->promptpay_id)) {
            $expectedTarget = preg_replace('/\D/', '', (string) $branch->promptpay_id) ?? '';
            $actualTarget = preg_replace('/\D/', '', $result->receiverAccount) ?? '';

            if ($expectedTarget !== '' && $actualTarget !== '' && ! str_ends_with($expectedTarget, substr($actualTarget, -4))) {
                throw new SlipVerificationException(422, 'RECEIVER_MISMATCH', 'Slip receiver account does not match branch PromptPay account.');
            }
        }

        try {
            return SlipVerification::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'order_id' => $orderId,
                'provider' => $result->provider,
                'sending_bank_code' => $result->sendingBankCode,
                'trans_ref' => $result->transRef,
                'qr_payload_hash' => hash('sha256', $parsed->rawPayload),
                'amount' => (string) ($result->amount !== null ? BigDecimal::of($result->amount)->toScale(2, RoundingMode::HALF_UP) : $expectedDecimal),
                'sender_name' => $result->senderName,
                'receiver_account' => $result->receiverAccount,
                'status' => 'verified',
                'raw_response' => $result->rawResponse,
                'verified_at' => now(),
            ]);
        } catch (QueryException) {
            throw new SlipVerificationException(409, 'DUPLICATE_SLIP', 'This transfer slip has already been used.');
        }
    }

    private function resolveDriver(string $driver): SlipVerificationDriverInterface
    {
        return match ($driver) {
            'local_mini_qr' => new LocalMiniQrSlipDriver,
            'scb_open_api' => new ScbOpenApiSlipDriver,
            'kbank_open_api' => new KBankOpenApiSlipDriver,
            default => throw new SlipVerificationException(422, 'UNSUPPORTED_DRIVER', "Unsupported slip verification driver [{$driver}]."),
        };
    }
}
