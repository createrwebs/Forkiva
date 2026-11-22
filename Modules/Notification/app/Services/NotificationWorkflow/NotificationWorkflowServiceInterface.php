<?php

namespace Modules\Notification\Services\NotificationWorkflow;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notification\Models\NotificationWorkflow;

interface NotificationWorkflowServiceInterface
{
    /**
     * Get paginated notification workflows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Get a notification workflow by key.
     */
    public function show(string $key): NotificationWorkflow;

    /**
     * Update workflow channel recipient matrix.
     *
     * @param  array<string, array<string, bool>>  $channels
     */
    public function update(string $key, array $channels): NotificationWorkflow;
}
