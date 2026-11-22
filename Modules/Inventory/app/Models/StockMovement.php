<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Database\Factories\StockMovementFactory;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property-read Ingredient $ingredient
 * @property int|null $unit_id
 * @property-read Unit|null $unit
 * @property int|null $reversal_of_id
 * @property-read StockMovement|null $originalMovement
 * @property-read Collection<int, StockMovement> $reversals
 * @property StockMovementType $type
 * @property int|null $source_id
 * @property string|null $source_type
 * @property string|null $idempotency_key
 * @property string $quantity
 * @property Money|null $unit_cost
 * @property Money|null $total_cost
 * @property string|null $currency
 * @property bool $legacy_cost_snapshot_missing
 * @property string $cost_snapshot_source
 * @property Carbon|null $cost_snapshot_remediated_at
 * @property string|null $note
 * @property float|null $currency_rate
 * @property string|null $currency_rate_source
 * @property Carbon|null $currency_rate_reviewed_at
 * @property Carbon|null $occurred_at
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class StockMovement extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
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
        'ingredient_id',
        'unit_id',
        'type',
        'quantity',
        'unit_cost',
        'currency',
        'currency_rate',
        'currency_rate_source',
        'currency_rate_reviewed_at',
        'note',
        'source_id',
        'source_type',
        'idempotency_key',
        'reversal_of_id',
        'occurred_at',
        'committed_at',
        self::BRANCH_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name', 'symbol'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'ingredient.name', 'label' => 'inventory::stock_movements.table.ingredient_name', 'with' => ['ingredient:id,name,unit_id']],
            ['path' => 'unit.symbol', 'label' => 'inventory::stock_movements.table.unit_symbol', 'with' => ['unit:id,symbol']],
            ['path' => 'type', 'label' => 'inventory::stock_movements.table.type'],
            ['path' => 'quantity', 'label' => 'inventory::stock_movements.table.quantity'],
            ['path' => 'unit_cost', 'label' => 'inventory::stock_movements.table.unit_cost', 'type' => 'money'],
            ['path' => 'total_cost', 'label' => 'inventory::stock_movements.table.total_cost', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'inventory::stock_movements.table.currency'],
            ['path' => 'currency_rate', 'label' => 'inventory::stock_movements.table.currency_rate'],
            ['path' => 'cost_snapshot_source', 'label' => 'inventory::stock_movements.table.cost_snapshot_source'],
            ['path' => 'source_id', 'label' => 'inventory::stock_movements.table.source_id'],
            ['path' => 'source_type', 'label' => 'inventory::stock_movements.table.source_type'],
            ['path' => 'source_name', 'label' => 'inventory::stock_movements.table.source_name'],
            ['path' => 'note', 'label' => 'inventory::stock_movements.table.note'],
            ['path' => 'originalMovement.id', 'label' => 'inventory::stock_movements.table.original_movement', 'with' => ['originalMovement:id']],
            ['path' => 'committed_at', 'label' => 'inventory::stock_movements.table.committed_at', 'type' => 'datetime'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    /**
     * Boot the model and protect committed movement history.
     */
    protected static function booted(): void
    {
        static::updating(function (StockMovement $movement): void {
            if ($movement->getOriginal('committed_at')) {
                throw new LogicException(__('inventory::messages.committed_stock_movement_immutable'));
            }
        });

        static::deleting(function (StockMovement $movement): void {
            if ($movement->committed_at) {
                throw new LogicException(__('inventory::messages.committed_stock_movement_immutable'));
            }
        });
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'ingredient_id',
            'type',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value): void {
            $query
                ->whereHas('ingredient', function (Builder $ingredientQuery) use ($value): void {
                    $ingredientQuery->whereLikeTranslation('name', $value);
                })
                ->orWhere('source_type', 'like', "%$value%")
                ->orWhere('source_id', 'like', "%$value%")
                ->orWhere('idempotency_key', 'like', "%$value%")
                ->orWhere('note', 'like', "%$value%");
        });
    }

    /**
     * Get an ingredient model
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the stock unit frozen when this movement was committed.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * Get the committed movement reversed by this movement.
     */
    public function originalMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get movements that reverse this committed movement.
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get source name.
     */
    public function sourceName(): Attribute
    {
        return Attribute::get(fn () => $this->getSourceName());
    }

    /**
     * Get source name
     */
    public function getSourceName(): ?string
    {
        if (is_null($this->source_type)) {
            return null;
        }

        $sourceType = trim($this->source_type, '\\');

        if (! str_contains($sourceType, '\\')) {
            $accountingSourceKey = "accounting::enums.source_types.$sourceType";
            $accountingSourceName = __($accountingSourceKey);

            return $accountingSourceName !== $accountingSourceKey
                ? $accountingSourceName
                : str($sourceType)->headline()->toString();
        }

        $parts = explode('\\', $sourceType);
        $source = str(class_basename($sourceType))->snake()->plural();
        $module = str($parts[1] ?? '')->snake();
        $translationKey = "$module::$source.$source";
        $sourceName = __($translationKey);

        return $sourceName !== $translationKey
            ? $sourceName
            : str(class_basename($sourceType))->headline()->toString();
    }

    /**
     * Get the immutable unit-cost snapshot as money.
     */
    public function unitCost(): Attribute
    {
        return Attribute::get(function ($amount): ?Money {
            if (is_null($amount)) {
                return null;
            }

            return new Money((float) $amount, $this->currency ?: setting('default_currency', 'JOD'));
        });
    }

    /**
     * Get the frozen total movement cost as money.
     */
    public function totalCost(): Attribute
    {
        return Attribute::get(function (): ?Money {
            $unitCost = $this->getRawOriginal('unit_cost');

            return is_null($unitCost)
                ? null
                : new Money(
                    (float) $unitCost * (float) $this->getRawOriginal('quantity'),
                    $this->currency ?: setting('default_currency', 'JOD'),
                );
        });
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'branch_id',
            'ingredient_id',
            'type',
            'quantity',
            'unit_cost',
            'currency',
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
            'type' => StockMovementType::class,
            'quantity' => 'decimal:6',
            'currency_rate' => 'decimal:8',
            'currency_rate_reviewed_at' => 'datetime',
            'legacy_cost_snapshot_missing' => 'boolean',
            'cost_snapshot_remediated_at' => 'datetime',
            'occurred_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }
}
