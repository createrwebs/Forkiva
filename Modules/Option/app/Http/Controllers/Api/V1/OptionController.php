<?php

namespace Modules\Option\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Option\Http\Requests\Api\V1\SaveOptionRequest;
use Modules\Option\Services\Option\OptionServiceInterface;
use Modules\Option\Transformers\Api\V1\OptionResource;
use Modules\Option\Transformers\Api\V1\OptionShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class OptionController extends Controller
{
    /**
     * Create a new instance of OptionController
     */
    public function __construct(protected OptionServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Option models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: OptionResource::class,
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
     * This method retrieves and returns a single Option model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new OptionShowResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Option model.
     */
    public function store(SaveOptionRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new OptionResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Option model.
     */
    public function update(SaveOptionRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new OptionResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Option model based on the provided ids.
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
    public function getFormMeta(): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta());
    }
}
