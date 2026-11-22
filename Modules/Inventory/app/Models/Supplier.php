<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Database\Factories\SupplierFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $vat_tin
 * @property string|null $tax_branch_code
 * @property string|null $phone
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Supplier extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'vat_tin',
        'tax_branch_code',
        self::BRANCH_COLUMN_NAME,
    ];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'inventory::suppliers.table.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'phone', 'label' => 'inventory::suppliers.table.phone'],
            ['path' => 'email', 'label' => 'inventory::suppliers.table.email'],
            ['path' => 'address', 'label' => 'inventory::suppliers.table.address'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all suppliers.
     */
    public static function list(?int $branchId = null): Collection
    {
        return Cache::tags('suppliers')
            ->rememberForever(
                makeCacheKey(
                    [
                        'suppliers',
                        is_null($branchId) ? 'all' : "branch-{$branchId}",
                        'list',
                    ],
                    false
                ),
                fn () => static::select('id', 'name')
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->get()
                    ->map(fn (Supplier $supplier) => [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                    ])
            );
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->like('name', $value)
                ->orLike('phone', $value)
                ->orLike('email', $value);
        });
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'email',
            'phone',
        ];
    }
}
