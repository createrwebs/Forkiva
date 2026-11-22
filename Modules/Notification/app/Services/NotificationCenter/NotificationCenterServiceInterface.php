<?php

namespace Modules\Notification\Services\NotificationCenter;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationCenterServiceInterface
{
    /**
     * Get paginated notifications for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function get(object $notifiable, array $filters = []): LengthAwarePaginator;

    /**
     * Get one notification by id for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function find(object $notifiable, string $id, array $filters = []): ?DatabaseNotification;

    /**
     * Get one notification by id for the authenticated notifiable or fail.
     *
     * @param  array<string, mixed>  $filters
     */
    public function findOrFail(object $notifiable, string $id, array $filters = []): DatabaseNotification;

    /**
     * Get unread count for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function unreadCount(object $notifiable, array $filters = []): int;

    /**
     * Get recent notifications for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, DatabaseNotification>
     */
    public function recent(object $notifiable, int $limit = 5, array $filters = []): array;

    /**
     * Mark one notification as read for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function markRead(object $notifiable, string $id, array $filters = []): DatabaseNotification;

    /**
     * Mark all unread notifications as read for the authenticated notifiable.
     *
     * @param  array<string, mixed>  $filters
     */
    public function markAllRead(object $notifiable, array $filters = []): int;
}
