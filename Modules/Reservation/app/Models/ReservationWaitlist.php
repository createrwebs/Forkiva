<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Reservation\Database\Factories\ReservationWaitlistFactory;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $customer_id
 * @property string|null $phone_country_iso_code
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_national_phone
 * @property string|null $customer_email
 * @property Carbon $preferred_date
 * @property string|null $preferred_start_time
 * @property string|null $preferred_end_time
 * @property int $guest_count
 * @property int|null $estimated_wait_minutes
 * @property int $priority
 * @property ReservationWaitlistStatus $status
 * @property int|null $converted_reservation_id
 * @property string|null $occasion
 * @property string|null $special_requests
 * @property string|null $internal_notes
 * @property Carbon|null $notified_at
 * @property Carbon|null $converted_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Reservation|null $convertedReservation
 */
class ReservationWaitlist extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'customer_id',
        'phone_country_iso_code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'preferred_date',
        'preferred_start_time',
        'preferred_end_time',
        'guest_count',
        'estimated_wait_minutes',
        'priority',
        'status',
        'converted_reservation_id',
        'occasion',
        'special_requests',
        'internal_notes',
        'created_by',
        'notified_at',
        'converted_at',
        'cancelled_at',
        'expires_at',
        'expired_at',
    ];

    /**
     * Ordered export column definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'customer_name', 'label' => 'reservation::reservation_waitlists.table.customer_name'],
            ['path' => 'customer_phone', 'label' => 'reservation::reservation_waitlists.table.customer_phone'],
            ['path' => 'preferred_date', 'label' => 'reservation::reservation_waitlists.table.preferred_date', 'type' => 'date'],
            ['path' => 'preferred_start_time', 'label' => 'reservation::reservation_waitlists.table.preferred_start_time', 'type' => 'time'],
            ['path' => 'preferred_end_time', 'label' => 'reservation::reservation_waitlists.table.preferred_end_time', 'type' => 'time'],
            ['path' => 'guest_count', 'label' => 'reservation::reservation_waitlists.table.guest_count'],
            ['path' => 'status', 'label' => 'reservation::reservation_waitlists.table.status'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationWaitlistFactory
    {
        return ReservationWaitlistFactory::new();
    }

    /**
     * Get linked customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get converted reservation.
     */
    public function convertedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'converted_reservation_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
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
     * Get allowed filters.
     *
     * @return list<string>
     */
    public function allowedFilterKeys(): array
    {
        return ['search', 'branch_id', 'status', 'preferred_date', 'from', 'to'];
    }

    /**
     * Scope query by search text.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('customer_name', 'like', "%$value%")
            ->orWhere('customer_phone', 'like', "%$value%")
            ->orWhere('customer_email', 'like', "%$value%");
    }

    /**
     * Get sortable attributes.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return ['preferred_date', 'preferred_start_time', 'guest_count', 'priority', 'created_at'];
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => ReservationWaitlistStatus::class,
            'notified_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }
}
