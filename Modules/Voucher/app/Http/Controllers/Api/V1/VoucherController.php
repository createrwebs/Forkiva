<?php

namespace Modules\Voucher\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Modules\Voucher\Http\Requests\Api\V1\SaveVoucherRequest;
use Modules\Voucher\Services\Voucher\VoucherServiceInterface;
use Modules\Voucher\Transformers\Api\V1\ShowVoucherResource;
use Modules\Voucher\Transformers\Api\V1\VoucherResource;

class VoucherController extends Controller
{
    /**
     * Create a new instance of VoucherController
     */
    public function __construct(protected VoucherServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of Voucher models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: VoucherResource::class,
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
     * This method retrieves and returns a single Voucher model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new ShowVoucherResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the Voucher model.
     */
    public function store(SaveVoucherRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new VoucherResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the Voucher model.
     */
    public function update(SaveVoucherRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new VoucherResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the Voucher model based on the provided ids.
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
