<?php

namespace Modules\Inventory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\Api\V1\CorrectStockMovementRequest;
use Modules\Inventory\Http\Requests\Api\V1\SaveStockMovementRequest;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Inventory\Transformers\Api\V1\StockMovementResource;
use Modules\Inventory\Transformers\Api\V1\StockMovementShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;

class StockMovementController extends Controller
{
    /**
     * Create a new instance of StockMovementController
     */
    public function __construct(protected StockMovementServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of StockMovement models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: StockMovementResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters(
                    auth()->user()->assignedToBranch()
                        ? auth()->user()->branch_id
                        : $request->input('filters')['branch_id'] ?? null
                )
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
     * This method stores the provided data into storage for the StockMovement model.
     */
    public function store(SaveStockMovementRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new StockMovementResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * Correct a committed stock movement through reversal and replacement.
     */
    public function correct(CorrectStockMovementRequest $request, int $id): JsonResponse
    {
        $result = $this->service->correct(
            id: $id,
            quantity: (float) $request->validated('quantity'),
            reason: $request->validated('reason'),
        );

        return ApiResponse::success([
            'original' => new StockMovementShowResource($this->service->show($result->original->id)),
            'reversal' => new StockMovementResource($result->reversal),
            'replacement' => new StockMovementResource($result->replacement),
        ], __('inventory::messages.stock_movement_corrected'));
    }

    /**
     * This method retrieves and returns a single StockMovement model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new StockMovementShowResource($this->service->show($id))
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
