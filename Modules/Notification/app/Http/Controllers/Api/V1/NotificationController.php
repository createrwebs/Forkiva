<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Notification\Enums\NotificationContext;
use Modules\Notification\Http\Requests\Api\V1\IndexNotificationRequest;
use Modules\Notification\Services\NotificationCenter\NotificationCenterServiceInterface;
use Modules\Notification\Transformers\Api\V1\NotificationResource;
use Modules\Support\ApiResponse;

class NotificationController extends Controller
{
    /**
     * Create a new NotificationController instance.
     */
    public function __construct(protected NotificationCenterServiceInterface $service) {}

    /**
     * Display a paginated listing of current-user notifications.
     */
    public function index(IndexNotificationRequest $request): JsonResponse
    {
        return ApiResponse::pagination(
            paginator: $this->service->get(
                notifiable: $request->user(),
                filters: [
                    'search' => $request->validated('search'),
                    'status' => $request->validated('status', 'all'),
                    'per_page' => (int) $request->validated('per_page', 15),
                    'app_context' => $request->validated('app_context'),
                ],
            ),
            resource: NotificationResource::class,
        );
    }

    /**
     * Display one current-user notification.
     */
    public function show(Request $request, string $notificationId): JsonResponse
    {
        return ApiResponse::success(
            new NotificationResource(
                $this->service->findOrFail($request->user(), $notificationId, $this->contextFilters($request))
            )
        );
    }

    /**
     * Get current-user notification summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $this->contextFilters($request);

        return ApiResponse::success([
            'unread_count' => $this->service->unreadCount($request->user(), $filters),
            'recent' => NotificationResource::collection($this->service->recent($request->user(), filters: $filters)),
        ]);
    }

    /**
     * Extract optional notification context filters.
     *
     * @return array<string, string>
     */
    protected function contextFilters(Request $request): array
    {
        $appContext = (string) $request->query('app_context', '');

        if (! in_array($appContext, NotificationContext::values(), true)) {
            return [];
        }

        return ['app_context' => $appContext];
    }

    /**
     * Mark one notification as read.
     */
    public function read(Request $request, string $notificationId): JsonResponse
    {
        return ApiResponse::updated(
            body: new NotificationResource($this->service->markRead($request->user(), $notificationId, $this->contextFilters($request))),
            resource: __('notification::notifications.notification')
        );
    }

    /**
     * Mark all unread notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $filters = $this->contextFilters($request);

        $this->service->markAllRead($request->user(), $filters);

        return ApiResponse::updated(
            body: [
                'unread_count' => $this->service->unreadCount($request->user(), $filters),
            ],
            resource: __('notification::notifications.notifications')
        );
    }
}
