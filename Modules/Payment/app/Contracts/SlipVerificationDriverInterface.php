<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\Data\ParsedThaiSlipQr;
use Modules\Payment\Data\SlipVerificationResult;

interface SlipVerificationDriverInterface
{
    /**
     * Verify a parsed BOT Mini-QR slip against the driver's authoritative source.
     *
     * @param  array<string, mixed>  $config
     */
    public function verify(ParsedThaiSlipQr $slip, array $config = []): SlipVerificationResult;
}
