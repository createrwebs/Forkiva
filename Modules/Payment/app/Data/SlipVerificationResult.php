<?php

namespace Modules\Payment\Data;

final readonly class SlipVerificationResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $provider,
        public string $transRef,
        public string $sendingBankCode,
        public ?string $amount = null,
        public ?string $senderName = null,
        public ?string $receiverAccount = null,
        public array $rawResponse = [],
    ) {}
}
