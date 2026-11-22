<?php

namespace Modules\Report\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Export\Transformers\Api\V1\ExportResource;
use Modules\Report\Enums\ExportMethod;
use Modules\Report\Services\Report\ReportServiceInterface;
use Modules\Support\ApiResponse;

class ReportController extends Controller
{
    /**
     * Create a new instance of ReportController
     */
    public function __construct(protected ReportServiceInterface $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $report): JsonResponse
    {
        return ApiResponse::success($this->service->renderReport($request, $report));
    }

    /**
     * Export report
     */
    public function export(Request $request, string $report, ExportMethod $method): JsonResponse
    {
        return ApiResponse::created(
            body: (new ExportResource($this->service->export($request, $report, $method)))->resolve(),
            resource: __('export::exports.export'),
            message: __('export::exports.messages.queued'),
        );
    }
}
