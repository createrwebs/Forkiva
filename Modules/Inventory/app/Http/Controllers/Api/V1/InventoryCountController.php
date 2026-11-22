<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\Api\V1\CancelInventoryCountRequest;
use Modules\Inventory\Http\Requests\Api\V1\SaveInventoryCountRequest;
use Modules\Inventory\Services\InventoryCount\InventoryCountServiceInterface;
use Modules\Inventory\Transformers\Api\V1\InventoryCountResource;
use Modules\Inventory\Transformers\Api\V1\InventoryCountShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class InventoryCountController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected InventoryCountServiceInterface $service) {}

    /**
     * Display a paginated inventory count list.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: InventoryCountResource::class,
            filters: $request->boolean('with_filters') ? $this->service->getStructureFilters() : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
            ],
        );
    }

    /**
     * Display one inventory count.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new InventoryCountShowResource($this->service->show($id)));
    }

    /**
     * Store a draft inventory count.
     *
     * @throws Throwable
     */
    public function store(SaveInventoryCountRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new InventoryCountResource($this->service->store($request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Update a draft inventory count.
     *
     * @throws Throwable
     */
    public function update(SaveInventoryCountRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new InventoryCountResource($this->service->update($id, $request->validated())),
            resource: $this->service->label(),
        );
    }

    /**
     * Delete draft inventory counts.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
        );
    }

    /**
     * Approve an inventory count and create variance movements.
     *
     * @throws Throwable
     */
    public function approve(int $id): JsonResponse
    {
        return ApiResponse::success(
            new InventoryCountResource($this->service->approve($id)),
            __('inventory::messages.inventory_count_approved'),
        );
    }

    /**
     * Cancel a draft inventory count.
     *
     * @throws Throwable
     */
    public function cancel(CancelInventoryCountRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            new InventoryCountResource($this->service->cancel($id, $request->validated('reason'))),
            __('inventory::messages.inventory_count_cancelled'),
        );
    }

    /**
     * Get form metadata.
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getFormMeta($request->integer('branch_id') ?: null));
    }
}
