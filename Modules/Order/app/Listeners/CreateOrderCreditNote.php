<?php

namespace Modules\Order\Listeners;

use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Services\CreateCreditNote\CreateCreditNoteServiceInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderVoided;
use Throwable;

class CreateOrderCreditNote
{
    /**
     * Handle the event.
     *
     * @throws Throwable
     */
    public function handle(OrderVoided $event): void
    {
        $order = $event->order;

        if ($order->payment_status->isPaid()) {
            $purpose = $event->status === OrderStatus::Cancelled
                ? InvoicePurpose::Cancellation
                : InvoicePurpose::Return;

            app(CreateCreditNoteServiceInterface::class)->create($order, $purpose);
        }
    }
}
