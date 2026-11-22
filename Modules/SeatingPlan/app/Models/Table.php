<?php

namespace Modules\SeatingPlan\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Order\Models\Order;
use Modules\SeatingPlan\Database\Factories\TableFactory;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Events\TableUpdateStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasOrder;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Support\Traits\HasUuid;
use Modules\Translation\Traits\Translatable;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $name
 * @property int $floor_id
 * @property-read  Floor $floor
 * @property int $zone_id
 * @property-read  Zone $zone
 * @property-read TableMerge $merges
 * @property int|null $assigned_waiter_id
 * @property-read  User|null $waiter
 * @property int|null $current_merge_id
 * @property-read  TableMerge|null $currentMerge
 * @property string|null $table_qr_token
 * @property Carbon|null $table_qr_token_regenerated_at
 * @property int $capacity
 * @property TableStatus $status
 * @property TableShape $shape
 * @property-read  Order[] $orders
 * @property-read  Order $activeOrder
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder availableMerge()
 */
class Table extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasOrder,
        HasSortBy,
        HasTagsCache,
        HasUuid,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'floor_id',
        'zone_id',
        'assigned_waiter_id',
        'current_merge_id',
        'name',
        'capacity',
        'status',
        'shape',
        'table_qr_token',
        'table_qr_token_regenerated_at',
        self::ACTIVE_COLUMN_NAME,
        self::BRANCH_COLUMN_NAME,
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
            ['path' => 'name', 'label' => 'seatingplan::tables.table_columns.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'floor.name', 'label' => 'seatingplan::tables.table_columns.floor', 'with' => ['floor:id,name']],
            ['path' => 'zone.name', 'label' => 'seatingplan::tables.table_columns.zone', 'with' => ['zone:id,name']],
            ['path' => 'waiter.name', 'label' => 'seatingplan::tables.table_columns.waiter', 'with' => ['waiter:id,name']],
            ['path' => 'capacity', 'label' => 'seatingplan::tables.table_columns.capacity'],
            ['path' => 'status', 'label' => 'seatingplan::tables.table_columns.status'],
            ['path' => 'shape', 'label' => 'seatingplan::tables.table_columns.shape'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    protected static function newFactory(): TableFactory
    {
        return TableFactory::new();
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::creating(function (Table $table) {
            $table->table_qr_token ??= static::generateQrToken();
            $table->table_qr_token_regenerated_at ??= now();
        });
    }

    /**
     * Generate QR token.
     */
    public static function generateQrToken(): string
    {
        return Str::random(64);
    }

    /**
     * Regenerate the QR token and invalidate old table QR links.
     */
    public function regenerateQrToken(): bool
    {
        return $this->forceFill([
            'table_qr_token' => static::generateQrToken(),
            'table_qr_token_regenerated_at' => now(),
        ])->save();
    }

    /**
     * Get a list of active tables.
     */
    public static function list(?int $branchId = null, ?int $floorId = null, ?int $zoneId = null): Collection
    {
        return Cache::tags('tables')
            ->rememberForever(
                makeCacheKey([
                    'tables',
                    is_null($branchId) ? 'all' : "branch-{$branchId}",
                    is_null($floorId) ? 'all' : "floor-{$floorId}",
                    is_null($zoneId) ? 'all' : "zone-{$zoneId}",
                    'list',
                ]),
                fn () => static::select('id', 'name', 'capacity')
                    ->withOutGlobalBranchPermission()
                    ->withoutGlobalActive()
                    ->where('is_active', true)
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->when(! is_null($floorId), fn ($query) => $query->where('floor_id', $floorId))
                    ->when(! is_null($zoneId), fn ($query) => $query->where('zone_id', $zoneId))
                    ->get()
                    ->map(fn (Table $table) => [
                        'id' => $table->id,
                        'name' => $table->name,
                        'capacity' => $table->capacity,
                    ])
            );
    }

    /**
     * Get floor
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get zone
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class)
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
            'floor',
            'floor_id',
            'zone_id',
            'status',
            'shape',
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

    /**
     * Get all table available merge
     */
    public function scopeAvailableMerge(Builder $query): void
    {
        $query
            ->whereNull('current_merge_id')
            ->whereIn('status', [
                TableStatus::Available,
                TableStatus::Occupied,
                TableStatus::Reserved,
            ]);
    }

    /**
     * Get merges
     */
    public function merges(): HasMany
    {
        return $this->hasMany(TableMerge::class);
    }

    /**
     * Get current merge
     */
    public function currentMerge(): BelongsTo
    {
        return $this->belongsTo(TableMerge::class, 'current_merge_id');
    }

    /**
     * Get assigned waiter
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_waiter_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get table orders
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get table active order
     */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->withOutGlobalBranchPermission()
            ->activeOrders();
    }

    /**
     * Store status log
     */
    public function storeStatusLog(TableStatus $status, ?int $changedById = null, ?string $note = null): TableStatusLog
    {
        return $this->statusLogs()
            ->create([
                'status' => $status,
                'changed_by' => $changedById,
                'note' => $note,
            ]);
    }

    /**
     * Get table status logs
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(TableStatusLog::class)->orderBy('id', 'desc');
    }

    /**
     * Update the table status and dispatch the module event for logging and notifications.
     */
    public function updateStatusWithEvent(TableStatus $status, ?int $changedById = null, ?string $note = null): void
    {
        if ($this->status !== $status) {
            $this->update(['status' => $status]);

            event(new TableUpdateStatus(
                table: $this,
                status: $status,
                changedById: $changedById,
                note: $note,
            ));
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'shape' => TableShape::class,
            'table_qr_token_regenerated_at' => 'datetime',
            self::ACTIVE_COLUMN_NAME => 'boolean',
            self::ORDER_COLUMN_NAME => 'int',
        ];
    }
}
