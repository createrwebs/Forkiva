<?php

namespace Modules\Invoice\Services\InvoiceTotalsCalculator;

use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;

interface InvoiceTotalsCalculatorServiceInterface
{
    /**
     * Apply totals
     */
    public function applyTotals(Invoice $invoice, Collection $lines): void;
}
