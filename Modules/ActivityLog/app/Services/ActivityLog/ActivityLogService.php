<?php

namespace Modules\ActivityLog\Services\ActivityLog;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\ActivityLog\Models\ActivityLog;
use Modules\Support\GlobalStructureFilters;

class ActivityLogService implements ActivityLogServiceInterface
{
    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return ActivityLog::query()
            ->with(['causer'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'activity_logs';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.activity_logs.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return ActivityLog::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): ActivityLog
    {
        return ActivityLog::query()->with(['subject', 'causer'])->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $data = ActivityLog::list();

        return [
            [
                'key' => 'ip',
                'label' => __('activitylog::activity_logs.filters.ip'),
                'type' => 'text',
            ],
            [
                'key' => 'batch_uuid',
                'label' => __('activitylog::activity_logs.filters.batch_uuid'),
                'type' => 'text',
            ],
            [
                'key' => 'causer_user',
                'label' => __('activitylog::activity_logs.filters.causer_user'),
                'type' => 'select',
                'options' => $data->map(fn (array $activityLog) => [
                    'id' => $activityLog['user_id'],
                    'name' => $activityLog['user_name'],
                ])
                    ->filter(fn ($u) => $u['id'] !== null)
                    ->unique('id')->values(),
            ],
            [
                'key' => 'event',
                'label' => __('activitylog::activity_logs.filters.event'),
                'type' => 'select',
                'multiple' => true,
                'options' => $data->pluck('event')
                    ->filter()
                    ->unique()
                    ->map(function ($item) {
                        return [
                            'id' => $item,
                            'name' => __("activitylog::activity_logs.events.$item"),
                        ];
                    })->values(),
            ],
            [
                'key' => 'log_name',
                'label' => __('activitylog::activity_logs.filters.log_name'),
                'type' => 'select',
                'multiple' => true,
                'options' => $data->pluck('log_name')
                    ->filter()
                    ->unique()
                    ->map(fn ($item) => ['id' => $item, 'name' => $item])->values(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate());
    }
}
