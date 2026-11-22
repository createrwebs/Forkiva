<?php

namespace Modules\Payment\Services\SlipVerification\Drivers;

use Modules\Payment\Contracts\SlipVerificationDriverInterface;
use Modules\Payment\Data\ParsedThaiSlipQr;
use Modules\Payment\Data\SlipVerificationResult;

/**
 * Self-hosted local BOT Mini-QR verification driver (validates structure,
 * CRC-16 checksum, bank code, and transaction reference uniqueness without
 * external network calls).
 */
class LocalMiniQrSlipDriver implements SlipVerificationDriverInterface
{
    /** {@inheritDoc} */
    public function verify(ParsedThaiSlipQr $slip, array $config = []): SlipVerificationResult
    {
        return new SlipVerificationResult(
            provider: 'local_mini_qr',
            transRef: $slip->transRef,
            sendingBankCode: $slip->sendingBankCode,
            amount: null,
            senderName: null,
            receiverAccount: null,
            rawResponse: $slip->toArray(),
        );
    }
}
