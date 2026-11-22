<?php

namespace Modules\Menu\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Menu\Database\Factories\OnlineMenuFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property int|null $menu_id
 * @property-read Menu|null $menu
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class OnlineMenu extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'menu_id',
        'slug',
        self::BRANCH_COLUMN_NAME,
        self::ACTIVE_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'menu::online_menus.table.name'],
            ['path' => 'slug', 'label' => 'menu::online_menus.table.slug'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'menu.name', 'label' => 'menu::online_menus.table.menu', 'with' => ['menu:id,name']],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Find by Slug
     */
    public static function findBySlug(string $slug, array $with = []): ?self
    {
        return static::where('slug', $slug)
            ->with($with)
            ->withOutGlobalBranchPermission()
            ->first();
    }

    protected static function newFactory(): OnlineMenuFactory
    {
        return OnlineMenuFactory::new();
    }

    /**
     * Set Slug
     */
    public function slug(): Attribute
    {
        return Attribute::set(fn ($slug) => str($slug)->slug()->toString());
    }

    /**
     * Get menu
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'menu_id',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('name', $value);
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'menu_id',
            'slug',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::ACTIVE_COLUMN_NAME => 'boolean',
        ];
    }
}
