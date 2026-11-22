<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Notification\Http\Requests\Api\V1\UpdateNotificationWorkflowRequest;
use Modules\Notification\Services\NotificationWorkflow\NotificationWorkflowServiceInterface;
use Modules\Notification\Transformers\Api\V1\NotificationWorkflowResource;
use Modules\Notification\Transformers\Api\V1\NotificationWorkflowShowResource;
use Modules\Support\ApiResponse;

class NotificationWorkflowController extends Controller
{
    /**
     * Create a new NotificationWorkflowController instance.
     */
    public function __construct(protected NotificationWorkflowServiceInterface $service) {}

    /**
     * Display a listing of notification workflows.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                filters: $request->input('filters', []),
                sorts: $request->input('sorts', []),
            ),
            resource: NotificationWorkflowResource::class,
        );
    }

    /**
     * Display the specified notification workflow.
     */
    public function show(string $key): JsonResponse
    {
        return ApiResponse::success(
            body: new NotificationWorkflowShowResource($this->service->show($key))
        );
    }

    /**
     * Update workflow recipient matrix.
     */
    public function update(UpdateNotificationWorkflowRequest $request, string $key): JsonResponse
    {
        return ApiResponse::updated(
            body: new NotificationWorkflowShowResource($this->service->update(
                key: $key,
                channels: $request->validated('channels')
            )),
            resource: __('notification::notification_workflows.notification_workflow')
        );
    }
}
