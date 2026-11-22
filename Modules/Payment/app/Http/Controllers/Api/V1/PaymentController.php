<?php

namespace Modules\Payment\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Payment\Services\Payment\PaymentServiceInterface;
use Modules\Payment\Transformers\Api\V1\PaymentResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class PaymentController extends Controller
{
    /**
     * Create a new instance of PaymentController
     */
    public function __construct(protected PaymentServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Payment models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: PaymentResource::class,
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
     * This method retrieves and returns a single Payment model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PaymentResource($this->service->show($id))
        );
    }
}
