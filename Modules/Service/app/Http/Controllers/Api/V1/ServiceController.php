<?php

namespace Modules\Service\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Service\Http\Requests\Api\V1\UpdateServiceSettingsRequest;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;
use Modules\Support\ApiResponse;

class ServiceController extends Controller
{
    /**
     * Create a new ServiceController instance.
     */
    public function __construct(protected ServiceRegistryServiceInterface $service) {}

    /**
     * List service categories and services.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->service->all());
    }

    /**
     * Show one service definition and current settings.
     */
    public function show(string $category, string $service): JsonResponse
    {
        return ApiResponse::success($this->service->show($category, $service));
    }

    /**
     * Update service settings.
     */
    public function update(UpdateServiceSettingsRequest $request, string $category, string $service): JsonResponse
    {
        return ApiResponse::updated(
            body: $this->service->update($category, $service, $request->validated()),
            resource: __('service::services.service')
        );
    }
}
