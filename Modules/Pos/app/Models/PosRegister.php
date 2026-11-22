<?php

namespace Modules\Pos\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Pos\Database\Factories\PosRegisterFactory;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Printer\Models\Printer;
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
 * @property string $note
 * @property string $code
 * @property int|null $last_session_id
 * @property-read PosSession|null $lastSession
 * @property-read PosSession|null $openSession
 * @property int|null $invoice_printer_id
 * @property Printer|null $invoicePrinter
 * @property int|null $bill_printer_id
 * @property Printer|null $billPrinter
 * @property int|null $waiter_printer_id
 * @property Printer|null $waiterPrinter
 * @property int|null $delivery_printer_id
 * @property Printer|null $deliveryPrinter
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PosRegister extends Model implements ModelExportDefinitionInterface
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
        'code',
        'note',
        'invoice_printer_id',
        'bill_printer_id',
        'waiter_printer_id',
        'delivery_printer_id',
        self::BRANCH_COLUMN_NAME,
        self::ACTIVE_COLUMN_NAME,
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'name', 'label' => 'pos::pos_registers.table.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'code', 'label' => 'pos::pos_registers.table.code'],
            ['path' => 'invoicePrinter.name', 'label' => 'pos::pos_registers.table.invoice_printer', 'with' => ['invoicePrinter:id,name']],
            ['path' => 'billPrinter.name', 'label' => 'pos::pos_registers.table.bill_printer', 'with' => ['billPrinter:id,name']],
            ['path' => 'deliveryPrinter.name', 'label' => 'pos::pos_registers.table.delivery_printer', 'with' => ['deliveryPrinter:id,name']],
            ['path' => 'waiterPrinter.name', 'label' => 'pos::pos_registers.table.waiter_printer', 'with' => ['waiterPrinter:id,name']],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name', 'note'];

    /**
     * Get a list of all/by branch pos registers.
     */
    public static function list(?int $branchId = null, bool $withLastSession = false): Collection
    {
        $loadRegisters = fn () => static::select('id', 'name', 'code')
            ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
            ->when($withLastSession, fn ($query) => $query->with('openSession:id,pos_register_id'))
            ->get()
            ->map(function (PosRegister $posRegister) use ($withLastSession) {
                $data = [
                    'id' => $posRegister->id,
                    'name' => "$posRegister->name ($posRegister->code)",
                ];

                if ($withLastSession && ! is_null($posRegister->openSession)) {
                    $data['session'] = ['id' => $posRegister->openSession->id];
                }

                return $data;
            });

        // Session status must stay live across opening, closing, and seeded data.
        if ($withLastSession) {
            return $loadRegisters();
        }

        return Cache::tags('pos_registers')->rememberForever(
            makeCacheKey([
                'pos_registers',
                is_null($branchId) ? 'all' : "branch-$branchId",
                'with_out_last_session',
                'list',
            ]),
            $loadRegisters
        );
    }

    /**
     * Get active session from session records, including registers with a stale last-session pointer.
     */
    public static function activeSession(int $registerId, ?string $action = null): ?PosSession
    {
        $posRegister = static::query()
            ->with('openSession.branch:id,currency')
            ->findOrFail($registerId);

        abort_if(
            ! is_null($action) && is_null($posRegister->openSession),
            400,
            __('pos::messages.no_active_session', ['action' => $action])
        );

        return $posRegister->openSession;
    }

    protected static function newFactory(): PosRegisterFactory
    {
        return PosRegisterFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
            self::ACTIVE_COLUMN_NAME,
            'invoice_printer_id',
            'bill_printer_id',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('name', $value)
            ->orLike('code', $value);
    }

    /**
     * Get invoice printer
     */
    public function invoicePrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'invoice_printer_id');
    }

    /**
     * Get bill printer
     */
    public function billPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'bill_printer_id');
    }

    /**
     * Get waiter printer
     */
    public function waiterPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'waiter_printer_id');
    }

    /**
     * Get delivery printer
     */
    public function deliveryPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'delivery_printer_id');
    }

    /**
     * Resolve the currently open session independently of the historical pointer.
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(PosSession::class)
            ->withOutGlobalBranchPermission()
            ->where('status', PosSessionStatus::Open)
            ->latest('id');
    }

    /**
     * Get last session
     */
    public function lastSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'last_session_id')
            ->withOutGlobalBranchPermission();
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'code',
            'invoice_printer_id',
            'bill_printer_id',
            self::ACTIVE_COLUMN_NAME,
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
