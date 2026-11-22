<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Support\Enums\ExportType;
use Modules\User\Http\Requests\Api\V1\SaveCustomerRequest;
use Modules\User\Services\Customer\CustomerServiceInterface;
use Modules\User\Transformers\Api\V1\UserResource;

class CustomerController extends Controller
{
    /**
     * Create a new instance of UserController
     */
    public function __construct(protected CustomerServiceInterface $service) {}

    /**
     * This method retrieves and returns a list of User models.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: UserResource::class,
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
     * This method retrieves and returns a single User model based on the provided identifier.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new UserResource($this->service->show($id))
        );
    }

    /**
     * This method stores the provided data into storage for the User model.
     */
    public function store(SaveCustomerRequest $request): JsonResponse
    {
        return ApiResponse::created(
            body: new UserResource($this->service->store($request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method updates the provided data for the User model.
     */
    public function update(SaveCustomerRequest $request, int $id): JsonResponse
    {
        return ApiResponse::updated(
            body: new UserResource($this->service->update($id, $request->validated())),
            resource: $this->service->label()
        );
    }

    /**
     * This method deletes the User model based on the provided ids.
     */
    public function destroy(string $ids): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($ids),
            resource: $this->service->label(),
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
