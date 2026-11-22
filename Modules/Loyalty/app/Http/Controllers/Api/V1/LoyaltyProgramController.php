<?php

namespace Modules\Loyalty\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Loyalty\Http\Requests\Api\V1\SaveLoyaltyProgramRequest;
use Modules\Loyalty\Services\LoyaltyProgram\LoyaltyProgramServiceInterface;
use Modules\Loyalty\Transformers\Api\V1\LoyaltyProgramResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class LoyaltyProgramController extends Controller
{
    /**
     * Create a new instance of LoyaltyProgramController
     */
    public function __construct(protected LoyaltyProgramServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of LoyaltyProgram models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: LoyaltyProgramResource::class,
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
     * This method retrieves and returns a single LoyaltyProgram model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new LoyaltyProgramResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the LoyaltyProgram model.
     */
    public function store(SaveLoyaltyProgramRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new LoyaltyProgramResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the LoyaltyProgram model.
     */
    public function update(SaveLoyaltyProgramRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new LoyaltyProgramResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the LoyaltyProgram model based on the provided ids.
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
