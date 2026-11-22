<?php

namespace Modules\Invoice\Services\TaxBuilder;

use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;

interface TaxBuilderServiceInterface
{
    /**
     * Create line taxes
     */
    public function createLineTaxes(Invoice $invoice, InvoiceLine $line, Collection $taxes): void;

    /**
     * Create invoice for taxes
     *
     * @param  Collection<int,int>|null  $orderProductIds
     */
    public function createInvoiceTaxes(Invoice $invoice, Collection $orders, ?Collection $orderProductIds = null, bool $fullOrderReversal = false): void;
}
