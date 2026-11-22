<?php

namespace Modules\Report\Services\Report;

use Illuminate\Http\Request;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Models\Export;
use Modules\Export\Services\Export\ExportServiceInterface;
use Modules\Report\Contracts\ReportInterface;
use Modules\Report\Enums\ExportMethod;
use Modules\Report\ReportManager;

class ReportService implements ReportServiceInterface
{
    /**
     * Create a report service instance.
     */
    public function __construct(protected ExportServiceInterface $exportService) {}

    /** {@inheritDoc} */
    public function renderReport(Request $request, string $key): array
    {
        $report = $this->resolveReport($key);

        return $report->render($request);
    }

    /** {@inheritDoc} */
    public function export(Request $request, string $key, ExportMethod $method): Export
    {
        $this->resolveReport($key);
        $filters = $request->input('filters', []);
        $sorts = $request->input('sorts', []);
        $context = $request->input('context', []);
        $filters = is_array($filters) ? $filters : [];
        $sorts = is_array($sorts) ? $sorts : [];
        $context = is_array($context) ? $context : [];
        $context['report'] = $key;
        $requestedContextKey = $request->input('context_key');
        $contextKey = is_string($requestedContextKey) && $requestedContextKey !== '' && strlen($requestedContextKey) <= 255
            ? $requestedContextKey
            : 'report:'.$key.':'.hash('sha256', json_encode([$filters, $sorts]));

        return $this->exportService->queue(
            resource: 'reports',
            format: ExportFormat::from($method->value),
            filters: $filters,
            sorts: $sorts,
            context: $context,
            contextKey: $contextKey,
        );
    }

    /**
     * Resolve an authorized fresh report instance.
     */
    private function resolveReport(string $key): ReportInterface
    {
        $reportManager = ReportManager::getInstance();

        abort_unless(
            $reportManager->reportExists($key),
            404,
            __('report::messages.report_not_exists', ['report' => $key])
        );

        $report = $reportManager->registeredReports($key);

        abort_unless(
            auth()->user()->can($report->permission()),
            403,
            __('admin::messages.action_unauthorized')
        );

        return $report;
    }
}
