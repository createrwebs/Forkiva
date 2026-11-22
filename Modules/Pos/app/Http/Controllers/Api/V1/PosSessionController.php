<?php

namespace Modules\Pos\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Pos\Http\Requests\Api\V1\CloseSessionRequest;
use Modules\Pos\Http\Requests\Api\V1\ClosingPreviewRequest;
use Modules\Pos\Http\Requests\Api\V1\OpenSessionRequest;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\Pos\Transformers\Api\V1\ClosingPreviewResource;
use Modules\Pos\Transformers\Api\V1\PosSessionResource;
use Modules\Pos\Transformers\Api\V1\PosSessionShowResource;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Throwable;

class PosSessionController extends Controller
{
    /**
     * Create a new instance of PosSessionController
     */
    public function __construct(protected PosSessionServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of PosSession models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: PosSessionResource::class,
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
     * This method retrieves and returns a single PosSession model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PosSessionShowResource($this->service->show($id))
        );
    }

    /**
     * Closing Preview
     *
     * @throws Throwable
     */
    public function closingPreview(ClosingPreviewRequest $request, int $id): JsonResponse
    {
        $declared = $request->validated('declared_cash');

        return ApiResponse::success(body: new ClosingPreviewResource(
            $this->service->closingPreview($id, $declared === null ? null : (string) $declared)
        ));
    }

    /**
     * Open pos sessions
     *
     * @throws Throwable
     */
    public function open(OpenSessionRequest $request): JsonResponse
    {
        return ApiResponse::success(
            body: new PosSessionResource($this->service->open($request->validated())),
            message: __('pos::messages.success_opened_session')
        );
    }

    /**
     * Close pos session
     *
     * @throws Throwable
     */
    public function close(CloseSessionRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new PosSessionResource($this->service->close($id, $request->validated())),
            message: __('pos::messages.success_closed_session')
        );
    }

    /**
     * Get form meta
     */
    public function getFormMeta(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getFormMeta(
                branchId: $request->integer('branch_id') ?: null,
            )
        );
    }
}
