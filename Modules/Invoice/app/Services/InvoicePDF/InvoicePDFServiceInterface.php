<?php

namespace Modules\Invoice\Services\InvoicePDF;

use Illuminate\Http\Response;
use Throwable;

interface InvoicePDFServiceInterface
{
    /**
     * View invoice as pdf file
     *
     * @throws Throwable
     */
    public function view(string $uuid): Response;

    /**
     * Download invoice as pdf file
     *
     * @throws Throwable
     */
    public function download(string $uuid): Response;
}
