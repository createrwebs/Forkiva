<?php

namespace Modules\Pos\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Pos\Http\Requests\Api\V1\SavePosCashMovementRequest;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Transformers\Api\V1\PosCashMovementResource;
use Modules\Pos\Transformers\Api\V1\PosCashMovementShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class PosCashMovementController extends Controller
{
    /**
     * Create a new instance of PosCashMovementController
     */
    public function __construct(protected PosCashMovementServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of PosCashMovement models.
     */
    public function index(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');

        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: [
                    ...$request->input('filters', []),
                    'pos_session_id' => $sessionId,
                ],
                sorts: $request->input('sorts', []),
            ),
            resource: PosCashMovementResource::class,
            filters: $request->input('with_filters')
                ? $this->service->getStructureFilters(
                    auth()->user()->assignedToBranch()
                        ? auth()->user()->branch_id
                        : $request->input('filters')['branch_id'] ?? null,
                    $sessionId
                )
                : null,
            extra: [
                'export_methods' => ExportType::toArrayTrans(),
                'export_api_uri' => '/v1/exports/'.$this->service->exportResourceKey(),
                'export_permission' => $this->service->exportPermission(),
                'export_resource' => $this->service->exportResourceKey(),
                'export_context' => array_filter([
                    'session_id' => $sessionId ? (int) $sessionId : null,
                ]),
            ]
        );
    }

    /**
     * This method stores the provided data into storage for the PosCashMovement model.
     *
     * @throws Throwable
     */
    public function store(SavePosCashMovementRequest $request): JsonResponse
    {
        $this->service->store($request->validated());

        return ApiResponse::created(resource: $this->service->label());
    }

    /**
     * This method retrieves and returns a single PosCashMovement model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PosCashMovementShowResource($this->service->show($id))
        );
    }
}
