<?php

namespace Modules\Printer\Factories\PrintContents;

use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Models\OrderTax;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\Printer\Factories\OrderResourceFactory;
use Modules\Printer\Models\Printer;

class PrintWaiterContentFactory implements PrintContentFactoryInterface
{
    /** {@inheritDoc} */
    public function relations(): array
    {
        return [
            'customer',
            'products',
            'products.taxes',
            'taxes',
            'table:id,name',
            'discount',
            'charges',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Order $order): array
    {
        return [
            'order' => OrderResourceFactory::order($order),
            'customer' => OrderResourceFactory::customer($order),
            'table' => ! is_null($order->table) ? OrderResourceFactory::table($order->table) : null,
            'discount' => ! is_null($order->discount) ? OrderResourceFactory::discount($order->discount) : null,
            'taxes' => $order->taxes->map(fn (OrderTax $orderTax) => OrderResourceFactory::tax($orderTax)),
            'tax_summary' => OrderResourceFactory::orderTaxSummary(
                $order->products->flatMap->taxes->concat($order->taxes)
            ),
            'products' => $order->products->map(fn (OrderProduct $product) => OrderResourceFactory::product($product)),
            'charges' => $order->charges->map(fn ($charge) => OrderResourceFactory::charge($charge)),
        ];
    }

    /** {@inheritDoc} */
    public function printers(int|array $specificIds): array|Printer|null
    {
        $register = PosRegister::query()
            ->whereHas('waiterPrinter', fn ($query) => $query->whereNotNull('options->qintrix_id'))
            ->with([
                'waiterPrinter' => fn ($query) => $query->whereNotNull('options->qintrix_id'),
            ])
            ->where('id', $specificIds)
            ->first();

        return $register?->waiterPrinter;
    }
}
