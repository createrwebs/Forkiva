<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Notification\Http\Requests\Api\V1\StoreNotificationPushTokenRequest;
use Modules\Notification\Services\NotificationPushToken\NotificationPushTokenServiceInterface;
use Modules\Notification\Transformers\Api\V1\NotificationPushTokenResource;
use Modules\Support\ApiResponse;

class NotificationPushTokenController extends Controller
{
    /**
     * Create a new NotificationPushTokenController instance.
     */
    public function __construct(protected NotificationPushTokenServiceInterface $service) {}

    /**
     * Store or update one push token for the authenticated user.
     */
    public function store(StoreNotificationPushTokenRequest $request): JsonResponse
    {
        return ApiResponse::success(new NotificationPushTokenResource(
            $this->service->store($request->validated())
        ));
    }

    /**
     * Remove one push token for the authenticated user.
     */
    public function destroy(int $id): JsonResponse
    {
        return ApiResponse::destroyed(
            destroyed: $this->service->destroy($id),
            resource: __('notification::notifications.push_token')
        );
    }
}
