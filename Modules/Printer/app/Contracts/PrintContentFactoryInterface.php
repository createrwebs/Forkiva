<?php

namespace Modules\Printer\Contracts;

use Modules\Order\Models\Order;
use Modules\Printer\Models\Printer;

interface PrintContentFactoryInterface
{
    /**
     * Get order relations
     */
    public function relations(): array;

    /**
     * Parse order data
     */
    public function resource(Order $order): array;

    /**
     * Get printers
     */
    public function printers(int|array $specificIds): array|Printer|null;
}
