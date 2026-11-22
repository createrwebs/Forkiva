<?php

namespace Modules\Report\Services\Report;

use Illuminate\Http\Request;
use Modules\Export\Models\Export;
use Modules\Report\Enums\ExportMethod;

interface ReportServiceInterface
{
    /**
     * Get report instance
     */
    public function renderReport(Request $request, string $key): array;

    /**
     * Get report instance
     */
    public function export(Request $request, string $key, ExportMethod $method): Export;
}
