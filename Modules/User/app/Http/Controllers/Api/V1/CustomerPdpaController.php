<?php

namespace Modules\User\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\User\Services\CustomerPdpa\CustomerPdpaServiceInterface;
use Modules\User\Transformers\Api\V1\UserResource;

class CustomerPdpaController extends Controller
{
    public function __construct(protected CustomerPdpaServiceInterface $service) {}

    /**
     * Export the personal data held about a customer (PDPA right to access).
     */
    public function export(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: $this->service->exportPersonalData($id)
        );
    }

    /**
     * Erase a customer's personal data (PDPA right to erasure).
     */
    public function anonymize(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: new UserResource($this->service->anonymize($id))
        );
    }
}
