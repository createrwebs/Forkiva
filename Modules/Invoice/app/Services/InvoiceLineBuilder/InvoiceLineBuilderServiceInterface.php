<?php

namespace Modules\Invoice\Services\InvoiceLineBuilder;

use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;

interface InvoiceLineBuilderServiceInterface
{
    /**
     * Create invoice lines for the provided orders.
     *
     * @return Collection<int,InvoiceLine>
     */
    public function createLines(Invoice $invoice, Collection $orders, bool $includeCharges = true): Collection;

    /**
     * Create invoice lines for order products only.
     *
     * @param  Collection<int,int>|null  $productIds
     * @return Collection<int,InvoiceLine>
     */
    public function createProductLines(Invoice $invoice, Collection $orders, ?Collection $productIds = null): Collection;

    /**
     * Create invoice lines for order charges.
     *
     * @param  Collection<int,int>|null  $chargeIds
     * @return Collection<int,InvoiceLine>
     */
    public function createChargeLines(Invoice $invoice, Collection $orders, ?Collection $chargeIds = null): Collection;
}
