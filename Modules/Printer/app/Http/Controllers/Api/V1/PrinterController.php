<?php

namespace Modules\Printer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Printer\Http\Requests\Api\V1\SavePrinterRequest;
use Modules\Printer\Services\Printer\PrinterServiceInterface;
use Modules\Printer\Transformers\Api\V1\PrinterResource;
use Modules\Printer\Transformers\Api\V1\PrinterShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class PrinterController extends Controller
{
    /**
     * Create a new instance of PrinterController
     */
    public function __construct(protected PrinterServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Printer models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: PrinterResource::class,
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
     * This method retrieves and returns a single Printer model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PrinterShowResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Printer model.
     */
    public function store(SavePrinterRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new PrinterResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Printer model.
     */
    public function update(SavePrinterRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new PrinterResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Printer model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label()
        );
    }

    /**
     * Get form meta
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getFormMeta(
                $request->integer('branch_id') ?: null
            )
        );
    }
}
