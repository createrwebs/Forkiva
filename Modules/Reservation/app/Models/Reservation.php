<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Invoice\Models\Invoice;
use Modules\Order\Models\Order;
use Modules\Reservation\Database\Factories\ReservationFactory;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property string|null $phone_country_iso_code
 * @property string $reservation_number
 * @property ReservationType $reservation_type
 * @property ReservationStatus $status
 * @property TableAssignmentStatus $table_assignment_status
 * @property TableAssignmentMode $table_assignment_mode
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $seated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $no_show_at
 * @property string $start_time
 * @property string $end_time
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property int $duration_minutes
 * @property int $guest_count
 * @property int|null $adult_count
 * @property int|null $child_count
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_national_phone
 * @property string|null $customer_email
 * @property string|null $occasion
 * @property string|null $special_requests
 * @property string|null $internal_notes
 * @property string|null $cancellation_reason
 * @property string|null $rejection_reason
 * @property string|null $no_show_reason
 * @property ReservationPaymentStatus $payment_status
 * @property bool $deposit_required
 * @property Money $deposit_amount
 * @property Money $deposit_paid_amount
 * @property string $deposit_currency
 * @property string $deposit_currency_rate
 * @property int|null $external_reference
 * @property int|null $order_id
 * @property int|null $invoice_id
 * @property int|null $deposit_transactions_count
 * @property Carbon|null $reservation_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $seated_by
 * @property-read User|null $customer
 * @property-read Collection<int, Table> $tables
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, ReservationStatusLog> $statusLogs
 * @property-read ReservationSetting|null $reservationSetting
 *
 * @method static Builder|self activeForAvailability(bool $includePending = true)
 */
class Reservation extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes,
        Translatable;

    /**
     * Default date column.
     */
    public static string $defaultDateColumn = 'reservation_date';

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = [
        'table_name',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'customer_id',
        'order_id',
        'invoice_id',
        'phone_country_iso_code',
        'reservation_number',
        'reservation_type',
        'status',
        'table_assignment_status',
        'table_assignment_mode',
        'reservation_date',
        'start_time',
        'end_time',
        'start_at',
        'end_at',
        'duration_minutes',
        'guest_count',
        'adult_count',
        'child_count',
        'customer_name',
        'customer_phone',
        'customer_email',
        'occasion',
        'special_requests',
        'internal_notes',
        'cancellation_reason',
        'rejection_reason',
        'no_show_reason',
        'payment_status',
        'deposit_required',
        'deposit_amount',
        'deposit_currency',
        'deposit_currency_rate',
        'confirmed_at',
        'arrived_at',
        'seated_at',
        'completed_at',
        'cancelled_at',
        'rejected_at',
        'no_show_at',
        'expired_at',
        'created_by',
        'updated_by',
        'confirmed_by',
        'cancelled_by',
        'seated_by',
        'completed_by',
        'external_reference',
    ];

    /**
     * Ordered export column definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'reservation_number', 'label' => 'reservation::reservations.table.reservation_number'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'customer_name', 'label' => 'reservation::reservations.table.customer_name'],
            ['path' => 'customer_phone', 'label' => 'reservation::reservations.table.customer_phone'],
            ['path' => 'reservation_date', 'label' => 'reservation::reservations.table.reservation_date', 'type' => 'date'],
            ['path' => 'start_time', 'label' => 'reservation::reservations.table.start_time'],
            ['path' => 'guest_count', 'label' => 'reservation::reservations.table.guest_count'],
            ['path' => 'status', 'label' => 'reservation::reservations.table.status'],
            ['path' => 'table_assignment_status', 'label' => 'reservation::reservations.table.table_assignment_status'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    /**
     * Get reservation customer account when linked.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get reservation table pivot rows.
     */
    public function reservationTables(): HasMany
    {
        return $this->hasMany(ReservationTable::class);
    }

    /**
     * Get status transition logs.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReservationStatusLog::class)->latest('changed_at');
    }

    /**
     * Get scheduled reminders.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(ReservationReminder::class);
    }

    /**
     * Get append-only deposit ledger transactions.
     */
    public function depositTransactions(): HasMany
    {
        return $this->hasMany(ReservationDepositTransaction::class)->orderBy('id');
    }

    /**
     * Get the branch reservation policy that governs this reservation.
     */
    public function reservationSetting(): HasOne
    {
        return $this->hasOne(ReservationSetting::class, 'branch_id', 'branch_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get the order linked to the reservation deposit application.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get all orders owned by the reservation.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get the invoice linked to the reservation deposit application.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get formatted customer phone.
     */
    public function customerPhone(): Attribute
    {
        return Attribute::make(
            get: fn ($phone) => ! is_null($phone) && ! is_null($this->phone_country_iso_code)
                ? phone($phone, $this->phone_country_iso_code)->formatInternational()
                : $phone,
        );
    }

    /**
     * Get customer phone in national format.
     */
    public function customerNationalPhone(): Attribute
    {
        return Attribute::get(
            fn () => ! is_null($this->getRawOriginal('customer_phone')) && ! is_null($this->phone_country_iso_code)
                ? phone($this->getRawOriginal('customer_phone'), $this->phone_country_iso_code)->formatNational()
                : null
        );
    }

    /**
     * Get allowed filter keys.
     *
     * @return list<string>
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'branch_id',
            'status',
            'reservation_type',
            'table_assignment_status',
            'reservation_date',
            'customer_phone',
        ];
    }

    /**
     * Scope query by search text.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $builder) use ($value) {
            $builder
                ->where('reservation_number', 'like', "%$value%")
                ->orWhere('customer_name', 'like', "%$value%")
                ->orWhere('customer_phone', 'like', "%$value%")
                ->orWhere('customer_email', 'like', "%$value%");
        });
    }

    /**
     * Scope records that block table availability.
     */
    public function scopeActiveForAvailability(Builder $query, bool $includePending = true): void
    {
        $query->whereIn('status', ReservationStatus::blockingValues($includePending));
    }

    /**
     * Determine if the reservation has assigned tables.
     */
    public function hasAssignedTables(): bool
    {
        return $this->tables()->exists();
    }

    /**
     * Get assigned seating-plan tables.
     */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(Table::class, 'reservation_tables')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive();
    }

    /**
     * Get deposit amount as money.
     */
    public function depositAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount ?? 0, $this->deposit_currency));
    }

    /**
     * Get paid deposit amount as money.
     */
    public function depositPaidAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount ?? 0, $this->deposit_currency));
    }

    /**
     * Get sortable attributes.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'reservation_number',
            'reservation_date',
            'start_at',
            'status',
            'guest_count',
            'branch_id',
            'customer_name',
            'customer_phone',
            'start_time',
            'table_assignment_status',
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
            'reservation_type' => ReservationType::class,
            'status' => ReservationStatus::class,
            'table_assignment_status' => TableAssignmentStatus::class,
            'table_assignment_mode' => TableAssignmentMode::class,
            'payment_status' => ReservationPaymentStatus::class,
            'reservation_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'seated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
            'no_show_at' => 'datetime',
            'expired_at' => 'datetime',
            'deposit_required' => 'boolean',
            'deposit_currency_rate' => 'decimal:8',
        ];
    }
}
