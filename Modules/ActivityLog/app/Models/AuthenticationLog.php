<?php

namespace Modules\ActivityLog\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasUserAgent;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $user_agent
 * @property Carbon $login_at
 * @property Carbon $logout_at
 * @property User $authenticatable
 */
class AuthenticationLog extends \Yadahan\AuthenticationLog\AuthenticationLog implements ModelExportDefinitionInterface
{
    use HasFilters, HasSortBy, HasUserAgent;

    /**
     * Default date column
     */
    protected static string $defaultDateColumn = 'login_at';

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'authenticatable.name', 'label' => 'activitylog::authentication_logs.table.user', 'with' => ['authenticatable:id,name,email,username']],
            ['path' => 'authenticatable.email', 'label' => 'user::users.table.email', 'with' => ['authenticatable:id,name,email,username']],
            ['path' => 'authenticatable.username', 'label' => 'user::users.table.username', 'with' => ['authenticatable:id,name,email,username']],
            ['path' => 'ip_address', 'label' => 'activitylog::authentication_logs.table.ip_address'],
            ['path' => 'user_agent', 'label' => 'activitylog::authentication_logs.table.user_agent'],
            ['path' => 'login_at', 'label' => 'activitylog::authentication_logs.table.login_at', 'type' => 'datetime'],
            ['path' => 'logout_at', 'label' => 'activitylog::authentication_logs.table.logout_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get default allowed filters
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->like('ip_address', $value)
                ->orWhereHas('authenticatable', function (Builder $query) use ($value) {
                    $query->like('name', $value)
                        ->orLike('email', $value)
                        ->orLike('username', $value);
                });
        });
    }

    /** {@inheritDoc} */
    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'ip_address',
            'login_at',
            'logout_at',
        ];
    }
}
