<?php

namespace Modules\Reservation\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Models\Reservation;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-07 10:00:00');
        $this->user = User::factory()->create(['is_active' => true]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reservation_analytics_routes_require_permission(): void
    {
        $this->getJson('/api/v1/reservation-analytics/filters')->assertForbidden();
        $this->getJson('/api/v1/reservation-analytics')->assertForbidden();
    }

    public function test_filters_expose_dashboard_presets_and_metadata(): void
    {
        $this->grantPermission();

        $this->getJson('/api/v1/reservation-analytics/filters')
            ->assertOk()
            ->assertJsonPath('body.defaults.branch_id', null)
            ->assertJsonPath('body.defaults.from', '2026-09-01')
            ->assertJsonPath('body.defaults.to', '2026-09-30')
            ->assertJsonPath('body.default_filters.branch_id', null)
            ->assertJsonPath('body.default_filters.from', '2026-09-01')
            ->assertJsonPath('body.default_filters.to', '2026-09-30')
            ->assertJsonPath('body.meta.timezone', config('app.timezone'))
            ->assertJsonCount(4, 'body.presets');
    }

    public function test_analytics_returns_new_demand_widgets_and_summary_metrics(): void
    {
        $this->grantPermission();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->reservation($branch, '2026-09-07', '18:00:00', 2, ReservationStatus::Cancelled);
        $this->reservation($branch, '2026-09-08', '18:30:00', 4, ReservationStatus::Completed);
        $this->reservation($branch, '2026-09-12', '20:00:00', 10, ReservationStatus::Confirmed);

        $response = $this->getJson('/api/v1/reservation-analytics?'.http_build_query([
            'filters' => [
                'branch_id' => $branch->id,
                'from' => '2026-09-07',
                'to' => '2026-09-13',
            ],
            'widgets' => [
                'total_reservations',
                'total_guests',
                'average_party_size',
                'cancellation_rate',
                'completion_rate',
                'reservation_trend',
                'peak_hours',
                'weekday_demand',
                'party_size_distribution',
            ],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $branch->id)
            ->assertJsonPath('body.widgets.total_reservations.key', 'total_reservations')
            ->assertJsonPath('body.widgets.total_reservations.permission', 'admin.reservations.analytics')
            ->assertJsonPath('body.widgets.total_reservations.authorized', true)
            ->assertJsonPath('body.widgets.total_reservations.data.value', 3)
            ->assertJsonPath('body.widgets.total_guests.data.value', 16)
            ->assertJsonPath('body.widgets.average_party_size.data.value', 5.33)
            ->assertJsonPath('body.widgets.cancellation_rate.data.value', 33.33)
            ->assertJsonPath('body.widgets.completion_rate.data.value', 33.33)
            ->assertJsonCount(3, 'body.widgets.reservation_trend.data')
            ->assertJsonPath('body.widgets.reservation_trend.data.0.label', 'Sep 7')
            ->assertJsonPath('body.widgets.peak_hours.data.0.hour', 18)
            ->assertJsonPath('body.widgets.peak_hours.data.0.reservations_count', 2)
            ->assertJsonPath('body.widgets.weekday_demand.data.0.reservations_count', 1)
            ->assertJsonPath('body.widgets.weekday_demand.data.5.reservations_count', 1)
            ->assertJsonPath('body.widgets.party_size_distribution.data.0.reservations_count', 1)
            ->assertJsonPath('body.widgets.party_size_distribution.data.1.reservations_count', 1)
            ->assertJsonPath('body.widgets.party_size_distribution.data.4.reservations_count', 1);
    }

    public function test_assigned_user_cannot_read_another_branch_analytics(): void
    {
        $this->grantPermission();
        $assignedBranch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        $this->reservation($assignedBranch, '2026-09-07', '18:00:00', 2, ReservationStatus::Confirmed);
        $this->reservation($otherBranch, '2026-09-07', '19:00:00', 4, ReservationStatus::Confirmed);
        $this->user->forceFill(['branch_id' => $assignedBranch->id])->save();
        $this->actingAs($this->user->fresh());

        $this->getJson('/api/v1/reservation-analytics?'.http_build_query([
            'branch_id' => $otherBranch->id,
            'from' => '2026-09-07',
            'to' => '2026-09-07',
        ]))->assertNotFound();

        $this->getJson('/api/v1/reservation-analytics?'.http_build_query([
            'from' => '2026-09-07',
            'to' => '2026-09-07',
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $assignedBranch->id)
            ->assertJsonPath('body.meta.branch_locked', true)
            ->assertJsonPath('body.widgets.total_reservations.data.value', 1);
    }

    public function test_analytics_calculates_only_requested_widgets(): void
    {
        $this->grantPermission();

        $this->getJson('/api/v1/reservation-analytics?'.http_build_query([
            'widgets' => ['total_reservations'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.total_reservations.data.value', 0)
            ->assertJsonMissingPath('body.widgets.reservation_trend');

        $this->getJson('/api/v1/reservation-analytics?'.http_build_query([
            'widgets' => ['unknown_widget'],
        ]))->assertUnprocessable();
    }

    protected function reservation(
        Branch $branch,
        string $date,
        string $time,
        int $guests,
        ReservationStatus $status,
    ): Reservation {
        $start = Carbon::parse("{$date} {$time}");

        return Reservation::factory()->create([
            'branch_id' => $branch->id,
            'reservation_date' => $date,
            'start_time' => $time,
            'end_time' => $start->copy()->addMinutes(90)->format('H:i:s'),
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(90),
            'guest_count' => $guests,
            'status' => $status,
            'table_assignment_status' => TableAssignmentStatus::Unassigned,
        ]);
    }

    protected function grantPermission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->firstOrCreate([
            'name' => 'admin.reservations.analytics',
            'guard_name' => 'api',
        ]);
        $this->user->givePermissionTo('admin.reservations.analytics');
    }
}
