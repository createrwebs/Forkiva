<?php

namespace Modules\Discount\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Discount\Http\Requests\Api\V1\SaveDiscountRequest;
use Modules\Discount\Services\Discount\DiscountServiceInterface;
use Modules\Discount\Transformers\Api\V1\DiscountResource;
use Modules\Discount\Transformers\Api\V1\ShowDiscountResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class DiscountController extends Controller
{
    /**
     * Create a new instance of DiscountController
     */
    public function __construct(protected DiscountServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Discount models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: DiscountResource::class,
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
     * This method retrieves and returns a single Discount model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ShowDiscountResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Discount model.
     */
    public function store(SaveDiscountRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new DiscountResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Discount model.
     */
    public function update(SaveDiscountRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new DiscountResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Discount model based on the provided ids.
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
