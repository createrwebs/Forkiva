<?php

namespace Modules\Option\Models;

use Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Option\Database\Factories\OptionFactory;
use Modules\Option\Enums\OptionType;
use Modules\Product\Services\ProductIngredient\ProductIngredientService;
use Modules\Product\Services\ProductIngredient\ProductIngredientServiceInterface;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasOrder;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property OptionType $type
 * @property bool $is_required
 * @property bool $is_global
 * @property Collection $values
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder globals()
 */
class Option extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasOrder,
        HasSortBy,
        HasTagsCache,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'type',
        'is_required',
        'is_global',
        self::BRANCH_COLUMN_NAME,
        self::ORDER_COLUMN_NAME,
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
            ['path' => 'name', 'label' => 'option::options.table.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'type', 'label' => 'option::options.table.type'],
            ['path' => 'is_required', 'label' => 'option::options.table.is_required', 'type' => 'boolean'],
            ['path' => 'is_global', 'label' => 'option::options.table.is_global', 'type' => 'boolean'],
            ['path' => 'values_count', 'label' => 'option::options.table.values_count', 'with_count' => 'values'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all options global.
     */
    public static function listGlobal(?int $branchId = null): Collection
    {
        return Cache::tags('options')
            ->rememberForever(
                makeCacheKey([
                    'options',
                    is_null($branchId) ? 'all' : "branch-$branchId",
                    'global',
                    'list',
                ]),
                fn () => static::select('id', 'name')
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->globals()
                    ->get()
                    ->map(fn (Option $option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                    ])
            );
    }

    protected static function newFactory(): OptionFactory
    {
        return OptionFactory::new();
    }

    /**
     * Scope a query to only include global options.
     */
    public function scopeGlobals(Builder $query): void
    {
        $query->where('is_global', true);
    }

    /**
     * Save values for the option.
     *
     *
     * @throws Throwable
     */
    public function saveValues(array $values = []): void
    {
        /** @var ProductIngredientService $productIngredientService */
        $productIngredientService = app(ProductIngredientServiceInterface::class);

        $ids = $this->getDeleteCandidates($values);

        if ($ids->isNotEmpty()) {
            $this->values()->whereIn('id', $ids)->delete();
        }

        $counter = 0;

        foreach (array_reset_index($values) as $attributes) {
            $ingredients = $attributes['ingredients'] ?? null;
            $attributes = Arr::except($attributes, ['ingredients']);

            $attributes += [
                'order' => ++$counter,
                'branch_id' => $this->branch_id,
            ];

            /** @var OptionValue $value */
            $value = isset($attributes['id'])
                ? $this->values()
                    ->updateOrCreate(
                        [
                            'id' => $attributes['id'],
                            'branch_id' => $this->branch_id,
                        ],
                        $attributes
                    )
                : $this->values()->create(Arr::except($attributes, ['id']));

            if (! is_null($ingredients)) {
                $productIngredientService->syncForOptionValue($value->id, $this->branch_id, $ingredients);
            }
        }
    }

    /**
     * Get delete candidates
     */
    private function getDeleteCandidates(array $values): Collection
    {
        return $this->values()
            ->pluck('id')
            ->diff(Arr::pluck($values, 'id'));
    }

    /**
     * Get the values for the option.
     *
     * @return mixed
     */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)
            ->with(['branch:id,currency'])
            ->orderBy('order');
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'type',
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
        $query->whereLikeTranslation('name', $value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'is_required' => 'boolean',
            'type' => OptionType::class,
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'type',
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
