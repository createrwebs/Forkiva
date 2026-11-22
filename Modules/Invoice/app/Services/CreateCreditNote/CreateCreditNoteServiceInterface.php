<?php

namespace Modules\Invoice\Services\CreateCreditNote;

use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Models\Invoice;
use Modules\Order\Models\Order;
use Throwable;

interface CreateCreditNoteServiceInterface
{
    /**
     * Create invoice credit note
     *
     * @throws Throwable
     */
    public function create(Order $order, InvoicePurpose $purpose = InvoicePurpose::Return): ?Invoice;
}
