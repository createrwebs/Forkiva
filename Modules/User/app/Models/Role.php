<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;
use Modules\User\Enums\DefaultRole;
use Spatie\Permission\Models\Role as BaseRole;

/**
 * @property string $name
 * @property string $display_name
 * @property bool|int $built_in
 *
 * @method static Builder|self builtIn(bool $operator = true)
 * @method static Builder|self notBuiltIn()
 *
 * @mixin Builder
 */
class Role extends BaseRole implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasCreatedBy,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        Translatable;

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['display_name'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'display_name', 'label' => 'user::roles.table.name'],
            ['path' => 'name', 'label' => 'user::roles.table.slug'],
            ['path' => 'role_type', 'label' => 'user::roles.table.role_type'],
            ['path' => 'permissions_count', 'label' => 'user::roles.table.permissions_count', 'with_count' => 'permissions'],
            ['path' => 'users_count', 'label' => 'user::roles.table.users_count', 'with_count' => 'users'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    public static function booted(): void
    {
        static::creating(function (Role $role) {
            if (empty($role->name)) {
                $role->name = static::getSlugName($role->display_name);
            }
        });
    }

    /**
     * Get slug name
     */
    public static function getSlugName(string|array|null $displayName): string
    {
        return uniqid(Str::slug(is_array($displayName)
                ? (isset($displayName[setting('default_locale')])
                    ? $displayName[setting('default_locale')]
                    : $displayName[array_key_first($displayName)])
                : $displayName).'_');
    }

    /**
     * Get a list of all roles.
     */
    public static function list(bool $forBranch = false, bool $withCustomer = false): Collection
    {
        return Cache::tags('roles')
            ->rememberForever(
                makeCacheKey([
                    'roles',
                    $forBranch ? 'for_branch' : '',
                    $withCustomer ? 'all' : 'without_customer',
                    'list',
                ]),
                function () use ($forBranch, $withCustomer) {
                    $roles = static::select('id', 'display_name', 'name')
                        ->when(! $withCustomer, fn ($query) => $query->whereNot('name', DefaultRole::Customer->value))
                        ->get();
                    if ($forBranch) {
                        $roles = $roles->filter(fn (Role $role) => in_array($role->name, DefaultRole::getBranchAvailableRoles()));
                    }

                    return $roles->map(fn (Role $role) => [
                        'id' => $role->id,
                        'key' => $role->name,
                        'name' => $role->display_name,
                    ])->values();
                });
    }

    /**
     * Scope a query to where Built-In.
     *
     * @noinspection PhpUnused
     */
    public function scopeBuiltIn(Builder $query, bool $operator = true): void
    {
        $query->where('built_in', $operator);
    }

    /**
     * Scope a query to where not Built-In.
     *
     * @noinspection PhpUnused
     */
    public function scopeNotBuiltIn(Builder $query): void
    {
        /** @var self $query */
        $query->builtIn(false);
    }

    /**
     * Determine if a role is build-in
     */
    public function isBuiltIn(): bool
    {
        return (bool) $this->built_in;
    }

    /**
     * Human-readable role type.
     */
    public function getRoleTypeAttribute(): string
    {
        return $this->isBuiltIn()
            ? __('user::roles.role_types.built_in')
            : __('user::roles.role_types.custom');
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'built_in',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->whereLikeTranslation('display_name', $value);
        });
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
        ];
    }
}
