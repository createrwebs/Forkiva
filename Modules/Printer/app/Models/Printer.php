<?php

namespace Modules\Printer\Models;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
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
 * @property array|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Printer extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'options',
        self::ACTIVE_COLUMN_NAME,
        self::BRANCH_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'printer::printers.table.name'],
            ['path' => 'options.qintrix_id', 'label' => 'printer::printers.table.qintrix_id'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all printers.
     */
    public static function list(?int $branchId = null): Collection
    {
        return Cache::tags('printers')
            ->rememberForever(
                makeCacheKey(
                    [
                        'printers',
                        is_null($branchId) ? 'all' : "branch-$branchId",
                        'list',
                    ],
                    false
                ),
                fn () => static::select('id', 'name')
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->get()
                    ->map(fn (Printer $printer) => [
                        'id' => $printer->id,
                        'name' => $printer->name,
                    ])
            );
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Map a Printer model to the agent-facing printer_config structure.
     */
    public function mapPrinterConfig(): array
    {
        return [
            'qintrix_id' => data_get($this->options, 'qintrix_id'),
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
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
            'options' => 'array',
            self::ACTIVE_COLUMN_NAME => 'boolean',
        ];
    }
}
