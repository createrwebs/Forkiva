<?php

namespace Modules\Invoice\Services\OrderChargeInvoicePolicy;

use Illuminate\Support\Facades\Log;
use Modules\Order\Enums\OrderChargeCode;
use Modules\Order\Models\OrderCharge;

class OrderChargeInvoicePolicyResolverService implements OrderChargeInvoicePolicyResolverServiceInterface
{
    /** {@inheritDoc} */
    public function shouldRefundOnFullOrderReversal(OrderCharge $charge): bool
    {
        $code = $this->resolveKnownCode($charge, true);

        return ! is_null($code);
    }

    /**
     * Resolve the known enum-backed charge code and optionally log unknown ones.
     */
    private function resolveKnownCode(OrderCharge $charge, bool $logUnknown): ?OrderChargeCode
    {
        $code = OrderChargeCode::tryFrom($charge->code);

        if (is_null($code) && $logUnknown) {
            Log::warning('Unknown order charge code encountered during invoice policy resolution.', [
                'order_charge_id' => $charge->id,
                'order_id' => $charge->order_id,
                'code' => $charge->code,
            ]);
        }

        return $code;
    }

    /** {@inheritDoc} */
    public function taxMetadata(OrderCharge $charge): array
    {
        if (! $this->isTaxable($charge)) {
            return [];
        }

        return is_array($charge->tax_metadata) ? $charge->tax_metadata : [];
    }

    /** {@inheritDoc} */
    public function isTaxable(OrderCharge $charge): bool
    {
        $code = $this->resolveKnownCode($charge, false);

        if (is_null($code)) {
            return false;
        }

        return $charge->taxable;
    }
}
