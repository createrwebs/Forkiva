<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case Seated = 'seated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case NoShow = 'no_show';
    case Expired = 'expired';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.reservation_statuses';
    }

    /**
     * Get statuses that block capacity for availability checks.
     *
     * @return array<int, string>
     */
    public static function blockingValues(bool $includePending = true): array
    {
        return array_values(array_filter([
            $includePending ? self::Pending->value : null,
            self::Confirmed->value,
            self::Arrived->value,
            self::Seated->value,
        ]));
    }

    /**
     * Determine if this status is terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::Rejected,
            self::NoShow,
            self::Expired,
        ], true);
    }

    /**
     * Determine if reservation details can be edited in this status.
     */
    public function allowsEditing(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Confirmed,
            self::Arrived,
        ], true);
    }

    /**
     * Determine if table assignment can be changed in this status.
     */
    public function allowsTableAssignment(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Confirmed,
            self::Arrived,
        ], true);
    }

    /**
     * Determine if status can be changed from this status.
     */
    public function allowsStatusChange(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Get valid next statuses from the current status.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Confirmed,
                self::Cancelled,
                self::Rejected,
                self::Expired,
            ],
            self::Confirmed => [
                self::Arrived,
                self::Seated,
                self::Cancelled,
                self::NoShow,
            ],
            self::Arrived => [
                self::Seated,
                self::Cancelled,
                self::NoShow,
            ],
            self::Seated => [
                self::Completed,
            ],
            default => [],
        };
    }

    /**
     * Get valid next status values from the current status.
     *
     * @return array<int, string>
     */
    public function allowedTransitionValues(): array
    {
        return array_map(fn (self $status) => $status->value, $this->allowedTransitions());
    }

    /**
     * Determine if this status can transition to the target status.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Get the permission required for a user-initiated transition.
     *
     * Expiration is system-owned and is intentionally unavailable through the
     * authenticated reservation status endpoint.
     */
    public function transitionPermission(): ?string
    {
        return match ($this) {
            self::Confirmed => 'admin.reservations.confirm',
            self::Rejected => 'admin.reservations.reject',
            self::Cancelled => 'admin.reservations.cancel',
            self::Arrived => 'admin.reservations.mark_arrived',
            self::Seated => 'admin.reservations.seat',
            self::Completed => 'admin.reservations.complete',
            self::NoShow => 'admin.reservations.mark_no_show',
            self::Pending, self::Expired => null,
        };
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => '#F59E0B',
            self::Confirmed => '#2563EB',
            self::Arrived => '#0EA5E9',
            self::Seated => '#7C3AED',
            self::Completed => '#16A34A',
            self::Cancelled => '#DC2626',
            self::Rejected => '#991B1B',
            self::NoShow => '#6B7280',
            self::Expired => '#475569',
        };
    }

    /**
     * Get enum as translated array value.
     *
     * @return array<string, mixed>
     */
    public function toTrans(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->trans(),
            'color' => $this->color(),
        ];
    }

    /**
     * Get enum values with translations.
     *
     * @param  array<int, string>  $except
     * @return array<int, array<string, mixed>>
     */
    public static function toArrayTrans(array $except = []): array
    {
        return collect(self::cases())
            ->reject(fn (self $case) => in_array($case->value, $except, true))
            ->map(fn (self $case) => $case->toTrans())
            ->values()
            ->all();
    }
}
