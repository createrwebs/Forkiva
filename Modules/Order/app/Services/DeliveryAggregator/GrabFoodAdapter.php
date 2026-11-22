<?php

namespace Modules\Order\Services\DeliveryAggregator;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Order\Contracts\DeliveryAggregatorAdapterInterface;

class GrabFoodAdapter implements DeliveryAggregatorAdapterInterface
{
    public function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** {@inheritDoc} */
    public function normalizeOrder(array $payload): array
    {
        $items = [];

        foreach ($payload['items'] ?? [] as $item) {
            // GrabFood sends prices in minor currency units (satang).
            $unitPrice = (string) BigDecimal::of((string) ($item['price'] ?? 0))
                ->dividedBy(100, 2, RoundingMode::HALF_UP);

            $items[] = [
                'sku' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => $unitPrice,
            ];
        }

        $subtotalMinor = (int) ($payload['price']['subtotal'] ?? 0);
        $grossTotal = (string) BigDecimal::of((string) $subtotalMinor)
            ->dividedBy(100, 2, RoundingMode::HALF_UP);

        return [
            'channel' => 'grabfood',
            'external_order_id' => (string) ($payload['orderID'] ?? ''),
            'gross_total' => $grossTotal,
            'items' => $items,
        ];
    }
}
