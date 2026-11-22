<?php

namespace Modules\Order\Services\OrderPayment;

use Throwable;

interface OrderPaymentServiceInterface
{
    /**
     * Get add payment meta
     */
    public function getPaymentMeta(int|string $id): array;

    /**
     * Build a PromptPay QR payload for the outstanding balance, or for a
     * validated partial amount, using the branch PromptPay identity.
     */
    public function getPromptPayQr(int|string $id, ?string $amount = null): array;

    /**
     * Add payment
     *
     * @throws Throwable
     */
    public function storePayment(string|int $id, array $data): void;
}
