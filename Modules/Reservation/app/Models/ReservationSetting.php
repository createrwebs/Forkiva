<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Reservation\Database\Factories\ReservationSettingFactory;
use Modules\Reservation\Support\TimeInput;
use Modules\Support\Eloquent\Model;
use Modules\Support\Enums\Day;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $branch_id
 * @property bool $reservations_enabled
 * @property bool $auto_confirm_enabled
 * @property int $default_duration_minutes
 * @property int $min_duration_minutes
 * @property int $max_duration_minutes
 * @property int $buffer_minutes
 * @property int $min_advance_minutes
 * @property int $max_advance_days
 * @property int $slot_interval_minutes
 * @property int $min_guests
 * @property int $max_guests
 * @property int $large_party_threshold
 * @property bool $large_party_requires_approval
 * @property bool $allow_walk_in
 * @property bool $pending_blocks_capacity
 * @property bool $require_table_on_create
 * @property bool $require_table_on_confirm
 * @property bool $require_table_on_arrival
 * @property bool $require_table_on_seating
 * @property bool $cancellation_allowed
 * @property bool $require_cancellation_reason
 * @property int|null $cancellation_deadline_minutes
 * @property int $grace_period_minutes
 * @property bool $auto_no_show_enabled
 * @property int $pending_expiry_minutes
 * @property bool $deposit_enabled
 * @property bool $deposit_required_for_large_party
 * @property string|null $deposit_type
 * @property string|float|int|null $deposit_amount
 * @property bool $deposit_refundable
 * @property int|null $deposit_payment_deadline_minutes
 * @property bool $reminder_enabled
 * @property int $reminder_before_minutes
 * @property bool $waitlist_enabled
 * @property int|null $waitlist_expiry_minutes
 * @property array|null $working_hours
 * @property int|null $created_by
 * @property-read Branch $branch
 * @property-read User|null $createdBy
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReservationSetting extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'reservations_enabled',
        'auto_confirm_enabled',
        'default_duration_minutes',
        'min_duration_minutes',
        'max_duration_minutes',
        'buffer_minutes',
        'min_advance_minutes',
        'max_advance_days',
        'slot_interval_minutes',
        'min_guests',
        'max_guests',
        'large_party_threshold',
        'large_party_requires_approval',
        'allow_walk_in',
        'pending_blocks_capacity',
        'require_table_on_create',
        'require_table_on_confirm',
        'require_table_on_arrival',
        'require_table_on_seating',
        'cancellation_allowed',
        'require_cancellation_reason',
        'cancellation_deadline_minutes',
        'grace_period_minutes',
        'auto_no_show_enabled',
        'pending_expiry_minutes',
        'deposit_enabled',
        'deposit_required_for_large_party',
        'deposit_type',
        'deposit_amount',
        'deposit_refundable',
        'deposit_payment_deadline_minutes',
        'reminder_enabled',
        'reminder_before_minutes',
        'waitlist_enabled',
        'waitlist_expiry_minutes',
        'working_hours',
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
            ['path' => 'reservations_enabled', 'label' => 'reservation::reservation_settings.table.reservations_enabled', 'type' => 'boolean'],
            ['path' => 'default_duration_minutes', 'label' => 'reservation::reservation_settings.table.default_duration_minutes'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Normalize working hours payload against default week structure.
     *
     * @param  array<string, mixed>|null  $workingHours
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeWorkingHours(?array $workingHours): array
    {
        $defaults = self::defaultWorkingHours();

        return collect($defaults)
            ->mapWithKeys(function (array $defaultHours, string $day) use ($workingHours): array {
                $dayHours = $workingHours[$day] ?? [];

                return [
                    $day => [
                        'enabled' => (bool) ($dayHours['enabled'] ?? $defaultHours['enabled']),
                        'open' => self::normalizeWorkingHourTime($dayHours['open'] ?? $defaultHours['open']),
                        'close' => self::normalizeWorkingHourTime($dayHours['close'] ?? $defaultHours['close']),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Get default working hours payload.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaultWorkingHours(): array
    {
        return collect(Day::values())
            ->mapWithKeys(fn (string $day) => [
                $day => [
                    'enabled' => true,
                    'open' => '09:00 AM',
                    'close' => '11:00 PM',
                ],
            ])
            ->all();
    }

    /**
     * Normalize a working hour time value into 12-hour format.
     */
    protected static function normalizeWorkingHourTime(mixed $value): string
    {
        return TimeInput::format($value, 'h:i A') ?: '09:00 AM';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationSettingFactory
    {
        return ReservationSettingFactory::new();
    }

    /**
     * Get branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get allowed filter keys.
     *
     * @return list<string>
     */
    public function allowedFilterKeys(): array
    {
        return ['search', 'branch_id', 'created_by', 'reservations_enabled', 'from', 'to'];
    }

    /**
     * Scope query by search text.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('branch', fn (Builder $branchQuery) => $branchQuery->like('name', $value));
    }

    /**
     * Get sortable attributes.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'branch_id',
            'created_by',
            'reservations_enabled',
            'default_duration_minutes',
        ];
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservations_enabled' => 'boolean',
            'auto_confirm_enabled' => 'boolean',
            'large_party_requires_approval' => 'boolean',
            'allow_walk_in' => 'boolean',
            'pending_blocks_capacity' => 'boolean',
            'require_table_on_create' => 'boolean',
            'require_table_on_confirm' => 'boolean',
            'require_table_on_arrival' => 'boolean',
            'require_table_on_seating' => 'boolean',
            'cancellation_allowed' => 'boolean',
            'require_cancellation_reason' => 'boolean',
            'auto_no_show_enabled' => 'boolean',
            'waitlist_enabled' => 'boolean',
            'deposit_enabled' => 'boolean',
            'deposit_required_for_large_party' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'deposit_refundable' => 'boolean',
            'reminder_enabled' => 'boolean',
            'working_hours' => 'array',
        ];
    }
}
