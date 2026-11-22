<?php

namespace Modules\Printer\Factories\PrintContents;

use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Models\OrderTax;
use Modules\Payment\Models\Payment;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\Printer\Factories\OrderResourceFactory;
use Modules\Printer\Models\Printer;

class PrintBillContentFactory implements PrintContentFactoryInterface
{
    /** {@inheritDoc} */
    public function relations(): array
    {
        return [
            'branch',
            'customer',
            'products',
            'products.taxes',
            'taxes',
            'charges',
            'payments',
            'table:id,name',
            'discount',
        ];
    }

    /** {@inheritDoc} */
    public function resource(Order $order): array
    {
        return [
            'order' => OrderResourceFactory::order($order),
            'branch' => OrderResourceFactory::branch($order->branch),
            'customer' => OrderResourceFactory::customer($order),
            'table' => ! is_null($order->table) ? OrderResourceFactory::table($order->table) : null,
            'discount' => ! is_null($order->discount) ? OrderResourceFactory::discount($order->discount) : null,
            'payments' => $order->payments->map(fn (Payment $p) => OrderResourceFactory::payment($p)),
            'taxes' => $order->taxes->map(fn (OrderTax $orderTax) => OrderResourceFactory::tax($orderTax)),
            'tax_summary' => OrderResourceFactory::orderTaxSummary(
                $order->products->flatMap->taxes->concat($order->taxes)
            ),
            'charges' => $order->charges->map(fn ($charge) => OrderResourceFactory::charge($charge)),
            'products' => $order->products->map(fn (OrderProduct $product) => OrderResourceFactory::product($product)),
            'currency_subunit' => data_get(OrderResourceFactory::order($order), 'currency_subunit'),
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
}
