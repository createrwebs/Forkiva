<?php

namespace Modules\Printer\Factories\PrintContents;

use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\Printer\Models\Printer;

/**
 * Expands order items into individual cup/box sticker payloads (1/N .. N/N)
 * for Thai cafes, bubble tea shops, and delivery packaging.
 */
class PrintLabelContentFactory implements PrintContentFactoryInterface
{
    /** {@inheritDoc} */
    public function relations(): array
    {
        return [
            'branch',
            'products',
            'products.options',
            'products.options.values',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Order $order): array
    {
        $items = $order->products->map(function (OrderProduct $product): array {
            $options = $product->options
                ->flatMap(fn ($option) => $option->values->pluck('name'))
                ->filter()
                ->values()
                ->all();

            return [
                'name' => (string) $product->name,
                'quantity' => (int) $product->quantity,
                'options' => $options,
            ];
        })->all();

        return [
            'order_reference_no' => (string) $order->reference_no,
            'order_type' => (string) ($order->type?->value ?? ''),
            'created_at' => (string) ($order->created_at?->format('Y-m-d H:i') ?? ''),
            'labels' => $this->expandLabels([
                'order_reference_no' => (string) $order->reference_no,
                'order_type' => (string) ($order->type?->value ?? ''),
                'created_at' => (string) ($order->created_at?->format('Y-m-d H:i') ?? ''),
                'items' => $items,
            ]),
        ];
    }

    /** {@inheritDoc} */
    public function printers(int|array $specificIds): array|Printer|null
    {
        $register = PosRegister::query()
            ->whereHas('billPrinter', fn ($query) => $query->whereNotNull('options->qintrix_id'))
            ->with([
                'billPrinter' => fn ($query) => $query->whereNotNull('options->qintrix_id'),
            ])
            ->where('id', $specificIds)
            ->first();

        return $register?->billPrinter;
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return list<array<string, mixed>>
     */
    public function expandLabels(array $orderPayload): array
    {
        $items = $orderPayload['items'] ?? [];
        $totalCount = 0;

        foreach ($items as $item) {
            $totalCount += max(1, (int) ($item['quantity'] ?? 1));
        }

        $labels = [];
        $currentIndex = 0;

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));

            for ($i = 0; $i < $qty; $i++) {
                $currentIndex++;
                $labels[] = [
                    'order_reference_no' => (string) ($orderPayload['order_reference_no'] ?? ''),
                    'order_type' => (string) ($orderPayload['order_type'] ?? ''),
                    'created_at' => (string) ($orderPayload['created_at'] ?? ''),
                    'sequence' => "{$currentIndex}/{$totalCount}",
                    'item_name' => (string) ($item['name'] ?? ''),
                    'options' => array_values($item['options'] ?? []),
                ];
            }
        }

        return $labels;
    }
}
