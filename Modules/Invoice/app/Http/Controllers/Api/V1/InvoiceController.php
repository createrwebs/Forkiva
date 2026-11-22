<?php

namespace Modules\Invoice\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Invoice\Services\Invoice\InvoiceServiceInterface;
use Modules\Invoice\Transformers\Api\V1\InvoiceResource;
use Modules\Invoice\Transformers\Api\V1\ShowInvoiceResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class InvoiceController extends Controller
{
    /**
     * Create a new instance of InvoiceController
     */
    public function __construct(protected InvoiceServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Invoice models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: InvoiceResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters()
                : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ]
        );
    }

    /**
     * This method retrieves and returns a single Invoice model based on the provided identifier.
     */
    public function show(int|string $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ShowInvoiceResource($this->service->show($id))
        );
    }
}
