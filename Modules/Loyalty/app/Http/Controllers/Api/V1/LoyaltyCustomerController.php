<?php

namespace Modules\Loyalty\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Loyalty\Services\LoyaltyCustomer\LoyaltyCustomerServiceInterface;
use Modules\Loyalty\Transformers\Api\V1\LoyaltyCustomerResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class LoyaltyCustomerController extends Controller
{
    /**
     * Create a new instance of LoyaltyCustomerController
     */
    public function __construct(protected LoyaltyCustomerServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of LoyaltyCustomer models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: LoyaltyCustomerResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters($request->input('filters')['loyalty_program_id'] ?? null)
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
     * This method retrieves and returns a single LoyaltyCustomer model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new LoyaltyCustomerResource($this->service->show($id))
        );
    }
}
