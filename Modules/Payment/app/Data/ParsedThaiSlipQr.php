<?php

namespace Modules\Payment\Data;

final readonly class ParsedThaiSlipQr
{
    public function __construct(
        public string $apiId,
        public string $sendingBankCode,
        public string $sendingBankShortName,
        public string $transRef,
        public string $countryCode,
        public string $rawPayload,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'api_id' => $this->apiId,
            'sending_bank_code' => $this->sendingBankCode,
            'sending_bank_short_name' => $this->sendingBankShortName,
            'trans_ref' => $this->transRef,
            'country_code' => $this->countryCode,
        ];
    }
}
