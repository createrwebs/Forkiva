<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Notification\Http\Requests\Api\V1\UpdateNotificationTemplateRequest;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateServiceInterface;
use Modules\Notification\Transformers\Api\V1\NotificationTemplateResource;
use Modules\Notification\Transformers\Api\V1\NotificationTemplateShowResource;
use Modules\Support\ApiResponse;

class NotificationTemplateController extends Controller
{
    /**
     * Create a new NotificationTemplateController instance.
     */
    public function __construct(protected NotificationTemplateServiceInterface $service) {}

    /**
     * Display a listing of notification templates.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: NotificationTemplateResource::class,
        );
    }

    /**
     * Display the specified notification template.
     */
    public function show(string $key): JsonResponse
    {
        return ApiResponse::success(new NotificationTemplateShowResource($this->service->show($key)));
    }

    /**
     * Update the specified notification template.
     */
    public function update(UpdateNotificationTemplateRequest $request, string $key): JsonResponse
    {
        return ApiResponse::updated(
            body: new NotificationTemplateShowResource($this->service->update($key, $request->validated())),
            resource: __('notification::notification_templates.notification_template')
        );
    }
}
