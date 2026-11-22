<?php

namespace Modules\Printer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Printer\Http\Requests\Api\V1\SavePrintAgentRequest;
use Modules\Printer\Services\Agent\PrintAgentServiceInterface;
use Modules\Printer\Transformers\Api\V1\PrintAgentResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class PrintAgentController extends Controller
{
    /**
     * Create a new instance of PrintAgentController
     */
    public function __construct(protected PrintAgentServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of print agent models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: PrintAgentResource::class,
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
     * This method retrieves and returns a single print agent model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PrintAgentResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the print agent model.
     */
    public function store(SavePrintAgentRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new PrintAgentResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the print agent model.
     */
    public function update(SavePrintAgentRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new PrintAgentResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the print agent model based on the provided ids.
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
