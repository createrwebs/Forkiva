<?php

namespace Modules\Notification\Services\NotificationTemplate;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notification\Models\NotificationTemplate;

interface NotificationTemplateServiceInterface
{
    /**
     * Get notification templates.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, string>>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show one notification template.
     */
    public function show(string $key): NotificationTemplate;

    /**
     * Update template metadata and messages.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $key, array $data): NotificationTemplate;
}
