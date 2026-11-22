<?php

namespace Modules\Order\Contracts;

interface DeliveryAggregatorAdapterInterface
{
    public function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool;

    /**
     * Normalize an external delivery platform order payload into Forkiva's canonical structure.
     *
     * @param  array<string, mixed>  $payload
     * @return array{channel: string, external_order_id: string, gross_total: string, items: list<array{sku: string, name: string, quantity: int, unit_price: string}>}
     */
    public function normalizeOrder(array $payload): array;
}
