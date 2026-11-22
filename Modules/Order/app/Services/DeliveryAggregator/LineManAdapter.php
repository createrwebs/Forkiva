<?php

namespace Modules\Order\Services\DeliveryAggregator;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Order\Contracts\DeliveryAggregatorAdapterInterface;

class LineManAdapter implements DeliveryAggregatorAdapterInterface
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

        foreach ($payload['order_items'] ?? [] as $item) {
            $unitPrice = (string) BigDecimal::of((string) ($item['unit_price'] ?? '0'))
                ->toScale(2, RoundingMode::HALF_UP);

            $items[] = [
                'sku' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['title'] ?? ''),
                'quantity' => max(1, (int) ($item['qty'] ?? 1)),
                'unit_price' => $unitPrice,
            ];
        }

        $grossTotal = (string) BigDecimal::of((string) ($payload['total_amount'] ?? '0'))
            ->toScale(2, RoundingMode::HALF_UP);

        return [
            'channel' => 'lineman',
            'external_order_id' => (string) ($payload['order_no'] ?? ''),
            'gross_total' => $grossTotal,
            'items' => $items,
        ];
    }
}
