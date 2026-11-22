<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Models\ReservationSetting;
use Modules\User\Models\User;

class ReservationSettingFactory extends Factory
{
    /**
     * The factory model.
     *
     * @var class-string<ReservationSetting>
     */
    protected $model = ReservationSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()
                ->withoutGlobalActive()
                ->whereNotIn('id', ReservationSetting::query()->pluck('branch_id'))
                ->inRandomOrder()
                ->value('id') ?? Branch::factory(),
            'created_by' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'reservations_enabled' => true,
            'auto_confirm_enabled' => false,
            'default_duration_minutes' => 90,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 240,
            'buffer_minutes' => 15,
            'min_advance_minutes' => 60,
            'max_advance_days' => 30,
            'slot_interval_minutes' => 15,
            'min_guests' => 1,
            'max_guests' => 30,
            'large_party_threshold' => 8,
            'large_party_requires_approval' => true,
            'allow_walk_in' => true,
            'pending_blocks_capacity' => true,
            'require_table_on_create' => false,
            'require_table_on_confirm' => false,
            'require_table_on_arrival' => false,
            'require_table_on_seating' => true,
            'cancellation_allowed' => true,
            'require_cancellation_reason' => true,
            'cancellation_deadline_minutes' => 120,
            'grace_period_minutes' => 15,
            'auto_no_show_enabled' => false,
            'pending_expiry_minutes' => 0,
            'deposit_enabled' => false,
            'deposit_required_for_large_party' => false,
            'deposit_type' => 'fixed',
            'deposit_amount' => 0,
            'deposit_refundable' => true,
            'deposit_payment_deadline_minutes' => null,
            'reminder_enabled' => true,
            'reminder_before_minutes' => 120,
            'waitlist_enabled' => true,
            'waitlist_expiry_minutes' => null,
            'working_hours' => ReservationSetting::defaultWorkingHours(),
        ];
    }
}
