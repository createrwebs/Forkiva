<?php

namespace Modules\Printer\Factories;

use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Printer\Contracts\PrintContentFactoryInterface;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Factories\PrintContents\PrintBillContentFactory;
use Modules\Printer\Factories\PrintContents\PrintInvoiceContentFactory;
use Modules\Printer\Factories\PrintContents\PrintKitchenContentFactory;
use Modules\Printer\Factories\PrintContents\PrintLabelContentFactory;
use Modules\Printer\Factories\PrintContents\PrintWaiterContentFactory;

class PrintContentFactory
{
    /**
     * Resolve factory
     */
    public static function resolve(PrintContentType $type): PrintContentFactoryInterface
    {
        $factory = match ($type) {
            PrintContentType::Bill => new PrintBillContentFactory,
            PrintContentType::Invoice => new PrintInvoiceContentFactory,
            PrintContentType::Kitchen => new PrintKitchenContentFactory,
            PrintContentType::Delivery => app(DeliveryFeatureInterface::class)->deliveryPrintFactory(),
            PrintContentType::Waiter => new PrintWaiterContentFactory,
            PrintContentType::Label => new PrintLabelContentFactory,
        };

        abort_if(is_null($factory), 404);

        return $factory;
    }
}
