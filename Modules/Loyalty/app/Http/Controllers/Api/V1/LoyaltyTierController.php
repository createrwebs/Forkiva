<?php

namespace Modules\Loyalty\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Loyalty\Http\Requests\Api\V1\SaveLoyaltyTierRequest;
use Modules\Loyalty\Services\LoyaltyTier\LoyaltyTierServiceInterface;
use Modules\Loyalty\Transformers\Api\V1\LoyaltyTierResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class LoyaltyTierController extends Controller
{
    /**
     * Create a new instance of LoyaltyTierController
     */
    public function __construct(protected LoyaltyTierServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of LoyaltyTier models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: LoyaltyTierResource::class,
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
     * This method retrieves and returns a single LoyaltyTier model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new LoyaltyTierResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the LoyaltyTier model.
     */
    public function store(SaveLoyaltyTierRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new LoyaltyTierResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the LoyaltyTier model.
     */
    public function update(SaveLoyaltyTierRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new LoyaltyTierResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the LoyaltyTier model based on the provided ids.
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
