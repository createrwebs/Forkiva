<?php

namespace Modules\ActivityLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Support\Traits\HasUserAgent;
use Modules\User\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * @method static Builder|self whereIp(string $ip)
 * @method static Builder|self whereBatchUuid(string $batchUuid)
 * @method static Builder|self whereEvent(string $event)
 * @method static Builder|self whereLogName(string $logName)
 * @method static Builder|self whereCauserUser(int $id)
 */
class ActivityLog extends Activity implements ModelExportDefinitionInterface
{
    use HasFilters,
        HasSortBy,
        HasTagsCache,
        HasUserAgent;

    /**
     * Get cached activity-log filter options.
     */
    public static function list(): Collection
    {
        return Cache::tags(['activity_log', 'users'])->rememberForever(
            makeCacheKey(['activity-logs', 'list'], false),
            fn () => static::query()
                ->from('activity_log as activity_logs')
                ->select([
                    'activity_logs.event',
                    'activity_logs.log_name',
                    'users.id as user_id',
                    'users.name as user_name',
                ])
                ->leftJoin('users', function ($join): void {
                    $join->on('activity_logs.causer_id', '=', 'users.id')
                        ->where('activity_logs.causer_type', User::class);
                })
                ->distinct()
                ->get()
                ->map(fn (ActivityLog $activityLog): array => [
                    'event' => $activityLog->event,
                    'log_name' => $activityLog->log_name,
                    'user_id' => $activityLog->user_id,
                    'user_name' => $activityLog->user_name,
                ])
        );
    }

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'causer.name', 'label' => 'activitylog::activity_logs.show.user', 'with' => ['causer']],
            ['path' => 'log_name', 'label' => 'activitylog::activity_logs.table.log_name'],
            ['path' => 'event', 'label' => 'activitylog::activity_logs.table.event'],
            ['path' => 'description_text', 'label' => 'activitylog::activity_logs.show.description'],
            ['path' => 'subject_text', 'label' => 'activitylog::activity_logs.table.subject'],
            ['path' => 'subject_type', 'label' => 'activitylog::activity_logs.show.subject_type'],
            ['path' => 'subject_id', 'label' => 'activitylog::activity_logs.show.subject_id'],
            ['path' => 'ip_address', 'label' => 'activitylog::activity_logs.table.ip'],
            ['path' => 'http_method', 'label' => 'activitylog::activity_logs.show.http_method'],
            ['path' => 'properties.info.user_agent', 'label' => 'activitylog::activity_logs.show.agent'],
            ['path' => 'batch_uuid', 'label' => 'activitylog::activity_logs.filters.batch_uuid'],
            ['path' => 'created_at', 'label' => 'activitylog::activity_logs.table.logged_at', 'type' => 'datetime'],
        ];
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'ip',
            'batch_uuid',
            'causer_user',
            'event',
            'log_name',
            'from',
            'to',
        ];
    }

    /**
     * Scope a query to get by log name.
     */
    public function scopeWhereLogName(Builder $query, string|array $logName): void
    {
        $query->whereIn('log_name', Arr::wrap($logName));
    }

    /**
     * Scope a query to get by Causer User
     *
     * @noinspection PhpUnused
     */
    public function scopeWhereCauserUser(Builder $query, int $id): void
    {
        $query->where('causer_id', $id)->where('causer_type', User::class);
    }

    /** {@inheritDoc} */
    public function getUserAgent(): ?string
    {
        return $this->properties['info']['user_agent'] ?? null;
    }

    /**
     * Exportable subject text accessor.
     */
    public function getSubjectTextAttribute(): string
    {
        return $this->getSubjectText();
    }

    /**
     * Get subject text
     */
    public function getSubjectText(): string
    {
        $parse = $this->parseSubject();
        $subjectId = $this->subject_id ? " #{$this->subject_id}" : '';

        return trim("{$parse['model_text']}{$subjectId}");
    }

    /**
     * Parse subject info
     */
    public function parseSubject(): array
    {
        $subjectType = (string) $this->subject_type;

        if ($subjectType === '') {
            return [
                'module' => null,
                'model' => null,
                'model_text' => '-',
            ];
        }

        $pieces = array_values(array_filter(explode('\\', $subjectType)));
        $model = str(class_basename($subjectType))->snake()->lower();
        $module = isset($pieces[1]) ? mb_strtolower($pieces[1]) : null;
        $translationKey = $module ? "$module::{$model->plural()}.$model" : null;
        $modelText = $translationKey ? __($translationKey) : null;

        if (! $modelText || $modelText === $translationKey) {
            $modelText = str(class_basename($subjectType))->headline()->toString();
        }

        return [
            'module' => $module,
            'model' => $model,
            'model_text' => $modelText,
        ];
    }

    /**
     * Exportable translated description accessor.
     */
    public function getDescriptionTextAttribute(): string
    {
        return __($this->description, array_map(fn ($param) => __($param), $this->properties['trans_params'] ?? []));
    }

    /**
     * Exportable IP accessor.
     */
    public function getIpAddressAttribute(): ?string
    {
        return $this->properties['info']['ip'] ?? null;
    }

    /**
     * Exportable HTTP method accessor.
     */
    public function getHttpMethodAttribute(): ?string
    {
        return $this->properties['info']['http_method'] ?? null;
    }

    /**
     * The attributes that can be used for sorting.
     */
    protected function getSortableAttributes(): array
    {
        return [
            'log_name',
            'subject_id',
            'subject_type',
            'event',
        ];
    }

    /**
     * Scope a query to get by ip.
     *
     * @noinspection PhpUnused
     */
    protected function scopeWhereIp(Builder $query, string $ip): void
    {
        $query->where('properties->info->ip', $ip);
    }

    /**
     * Scope a query to get by batch uuid.
     *
     * @noinspection PhpUnused
     */
    protected function scopeWhereBatchUuid(Builder $query, string $batchUuid): void
    {
        $query->where('batch_uuid', $batchUuid);
    }

    /**
     * Scope a query to get by event.
     *
     * @noinspection PhpUnused
     */
    protected function scopeWhereEvent(Builder $query, string|array $event): void
    {
        $query->whereIn('event', Arr::wrap($event));
    }
}
