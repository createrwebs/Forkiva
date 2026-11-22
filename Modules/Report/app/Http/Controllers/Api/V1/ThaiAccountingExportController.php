<?php

namespace Modules\Report\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\Report\Services\AccountingExport\ThaiAccountingExportService;

class ThaiAccountingExportController extends Controller
{
    public function __construct(private ThaiAccountingExportService $exportService) {}

    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'format' => ['required', 'in:peak,flowaccount,express'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.issued_at' => ['required', 'string'],
            'rows.*.invoice_number' => ['required', 'string'],
            'rows.*.subtotal' => ['required', 'numeric'],
            'rows.*.tax_total' => ['required', 'numeric'],
            'rows.*.total' => ['required', 'numeric'],
        ]);

        $format = (string) $validated['format'];
        $csv = $this->exportService->export($format, $validated['rows']);
        $filename = "thai-accounting-{$format}-".now()->format('Ymd').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
