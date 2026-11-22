<?php

namespace Modules\Invoice\Services\InvoicePartyBuilder;

use Modules\Branch\Models\Branch;
use Modules\Invoice\Models\InvoiceParty;
use Modules\User\Models\User;

interface InvoicePartyBuilderServiceInterface
{
    /**
     * Create seller party
     */
    public function createSeller(Branch $branch): InvoiceParty;

    /**
     * Create buyer party
     */
    public function createBuyer(?User $customer): ?InvoiceParty;
}
