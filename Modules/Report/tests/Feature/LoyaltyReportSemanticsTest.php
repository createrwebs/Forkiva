<?php

namespace Modules\Report\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Models\Branch;
use Modules\Loyalty\Enums\LoyaltyTransactionType;
use Modules\Loyalty\Models\LoyaltyProgram;
use Modules\Loyalty\Models\LoyaltyReward;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Report\Reports\Loyalty\InactiveLoyaltyCustomersReport;
use Modules\Report\Reports\Loyalty\LeastUsedRewardsReport;
use Modules\Report\Reports\Loyalty\LoyaltyLastActivityReport;
use Modules\Report\Reports\Loyalty\MostRedeemedRewardsReport;
use Modules\Report\Reports\Loyalty\NoRedemptionsReport;
use Modules\Report\Reports\Loyalty\RewardsByProgramReport;
use Modules\Report\Reports\Loyalty\RewardsByTierReport;
use Modules\Report\Reports\Loyalty\TopCustomersByPointsReport;
use Modules\User\Models\User;
use Tests\TestCase;

class LoyaltyReportSemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 12)->startOfDay());
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));
    }

    public function test_points_are_totalled_per_customer_without_merging_identical_names(): void
    {
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $customer = User::factory()->create(['name' => 'Same name']);
        $other = User::factory()->create(['name' => 'Same name']);
        $this->membership($customer, $first, 100, 40);
        $this->membership($customer, $second, 200, 80, ['created_at' => '2026-09-02 00:00:00']);
        $this->membership($other, $first, 250, 90);
        $this->membership($customer, $first, 900, 900, ['deleted_at' => now()]);

        $report = app(TopCustomersByPointsReport::class);
        $rows = $report->query(new Request)->get();
        $this->assertCount(2, $rows);
        $this->assertSame([300, 250], $rows->pluck('lifetime_points')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([120, 90], $rows->pluck('points_balance')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([$customer->id, $other->id], $rows->pluck('customer_id')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(2, $report->data(new Request)['pagination']['total']);
        $this->assertSame(2, $report->data($this->request(['group_by_date' => 'days']))['pagination']['total']);
        $selected = $report->query($this->request(['loyalty_program_id' => $first->id]))->get();
        $this->assertSame([250, 100], $selected->pluck('lifetime_points')->map(fn ($n) => (int) $n)->all());
        $dated = $report->query($this->request(['from' => '2026-09-02', 'to' => '2026-09-02']))->get();
        $this->assertCount(1, $dated);
        $this->assertSame(200, (int) $dated->first()->lifetime_points);
    }

    public function test_no_redemptions_excludes_a_customer_who_redeemed_in_another_selected_membership(): void
    {
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $redeemer = User::factory()->create(['name' => 'Redeemer']);
        $never = User::factory()->create(['name' => 'Never redeemed']);
        $redeemed = $this->membership($redeemer, $first, 100, 50);
        $this->membership($redeemer, $second, 200, 200);
        $unused = $this->membership($never, $first, 40, 40);
        $this->membership($never, $second, 60, 60);
        $this->transaction($redeemed, 'redeem', '2026-09-03 00:00:00');
        $this->transaction($unused, 'earn', '2026-09-03 00:00:00');
        $this->transaction($unused, 'bonus', '2026-09-04 00:00:00');
        $this->transaction($unused, 'redeem', '2026-09-05 00:00:00', ['deleted_at' => now()]);

        $report = app(NoRedemptionsReport::class);
        $rows = $report->data(new Request)['data'];
        $this->assertSame([['customer_name' => 'Never redeemed', 'lifetime_points' => 100]], $rows);
        $selected = $report->query($this->request(['loyalty_program_id' => $second->id]))->get()->keyBy('customer_name');
        $this->assertCount(2, $selected);
        $this->assertSame(200, (int) $selected['Redeemer']->lifetime_points);
        $this->assertSame(60, (int) $selected['Never redeemed']->lifetime_points);
    }

    public function test_last_activity_handles_multiple_memberships_timestamp_ties_and_no_transactions(): void
    {
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $customer = User::factory()->create(['name' => 'Active']);
        $one = $this->membership($customer, $first, 100, 50);
        $two = $this->membership($customer, $second, 100, 50, ['created_at' => '2026-09-02 00:00:00']);
        $this->membership(User::factory()->create(['name' => 'No activity']), $first, 0, 0);
        $this->transaction($one, 'earn', '2026-09-03 10:00:00');
        $this->transaction($one, 'redeem', '2026-09-03 10:00:00');
        $this->transaction($two, 'bonus', '2026-09-04 10:00:00');
        $this->transaction($two, 'expire', '2026-09-05 10:00:00', ['deleted_at' => now()]);

        $report = app(LoyaltyLastActivityReport::class);
        $rows = $report->query(new Request)->get();
        $this->assertCount(2, $rows);
        $rows = $rows->keyBy('customer_name');
        $this->assertSame('bonus', $rows['Active']->last_transaction_type);
        $this->assertSame('2026-09-04 10:00:00', $rows['Active']->last_earned_date);
        $this->assertSame('2026-09-03 10:00:00', $rows['Active']->last_redeemed_date);
        $this->assertNull($report->resource($rows['No activity'])['last_transaction_type']);
        $this->assertSame(2, $report->data(new Request)['pagination']['total']);
        $selected = $report->query($this->request(['loyalty_program_id' => $first->id]))->get();
        $this->assertCount(2, $selected);
        $selected = $selected->keyBy('customer_name');
        $this->assertSame('redeem', $selected['Active']->last_transaction_type);
        $this->assertSame(LoyaltyTransactionType::Redeem->trans(), $report->resource($selected['Active'])['last_transaction_type']);
        $dated = $report->query($this->request(['from' => '2026-09-02', 'to' => '2026-09-02']))->get();
        $this->assertCount(1, $dated);
        $this->assertSame('bonus', $dated->first()->last_transaction_type);
        $this->assertNull($dated->first()->last_redeemed_date);
    }

    public function test_inactivity_uses_the_latest_activity_and_membership_creation_as_fallback(): void
    {
        $this->travel(30)->days();
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $customer = User::factory()->create(['name' => 'Active']);
        $one = $this->membership($customer, $first, 100, 50, [
            'last_earned_at' => '2026-09-02 00:00:00', 'last_redeemed_at' => '2026-09-06 00:00:00',
        ]);
        $two = $this->membership($customer, $second, 100, 50);
        $this->membership(User::factory()->create(['name' => 'No activity']), $first, 0, 0);
        $this->transaction($one, 'earn', '2026-09-02 00:00:00');
        $this->transaction($two, 'bonus', '2026-09-09 00:00:00');
        $this->transaction($two, 'expire', '2026-09-11 00:00:00', ['deleted_at' => now()]);
        $report = app(InactiveLoyaltyCustomersReport::class);
        $rows = $report->query(new Request)->get();
        $this->assertCount(2, $rows);
        $rows = $rows->keyBy('customer_name');
        $this->assertSame('2026-09-09 00:00:00', $rows['Active']->last_activity_date);
        $this->assertEquals(33, $report->resource($rows['Active'])['days_inactive']);
        $this->assertEquals(41, $report->resource($rows['No activity'])['days_inactive']);
        $selected = $report->query($this->request(['loyalty_program_id' => $first->id]))->get()->keyBy('customer_name');
        $this->assertSame('2026-09-06 00:00:00', $selected['Active']->last_activity_date);
    }

    public function test_inactivity_requires_more_than_thirty_days_after_the_latest_selected_activity(): void
    {
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $cutoff = now()->subDays(30);
        $enrollment = ['created_at' => now()->subDays(60)];

        foreach (['Older' => -1, 'Exactly thirty days' => 0, 'More recent' => 1] as $name => $seconds) {
            $member = $this->membership(User::factory()->create(['name' => $name]), $first, 100, 50, $enrollment);
            $this->transaction($member, 'earn', $cutoff->copy()->addSeconds($seconds)->toDateTimeString());
        }

        $customer = User::factory()->create(['name' => 'Active in another program']);
        $oldMembership = $this->membership($customer, $first, 100, 50, $enrollment);
        $recentMembership = $this->membership($customer, $second, 100, 50, $enrollment);
        $this->transaction($oldMembership, 'earn', now()->subDays(40)->toDateTimeString());
        $this->transaction($recentMembership, 'earn', now()->subDays(2)->toDateTimeString());
        $this->membership(User::factory()->create(['name' => 'Never active, old enrollment']), $first, 0, 0, $enrollment);
        $this->membership(User::factory()->create(['name' => 'New enrollment']), $first, 0, 0);

        $report = app(InactiveLoyaltyCustomersReport::class);
        $all = $report->data(new Request);
        $this->assertEqualsCanonicalizing(['Older', 'Never active, old enrollment'], array_column($all['data'], 'customer_name'));
        $this->assertSame(2, $all['pagination']['total']);

        $selected = $report->data($this->request(['loyalty_program_id' => $first->id]));
        $this->assertEqualsCanonicalizing(
            ['Older', 'Never active, old enrollment', 'Active in another program'],
            array_column($selected['data'], 'customer_name'),
        );
        $this->assertSame(3, $selected['pagination']['total']);
    }

    public function test_reward_catalogues_keep_distinct_rewards_with_identical_names(): void
    {
        $first = LoyaltyProgram::factory()->create(['name' => 'Same program']);
        $second = LoyaltyProgram::factory()->create(['name' => 'Same program']);
        $tierOne = LoyaltyTier::factory()->create(['loyalty_program_id' => $first->id, 'name' => ['en' => 'Same tier']]);
        $tierTwo = LoyaltyTier::factory()->create(['loyalty_program_id' => $second->id, 'name' => ['en' => 'Same tier']]);
        $this->reward($first, $tierOne);
        $this->reward($first, $tierOne);
        $this->reward($second, $tierTwo);
        foreach ([RewardsByProgramReport::class, RewardsByTierReport::class] as $class) {
            $report = app($class);
            $this->assertSame(3, $report->data(new Request)['pagination']['total'], $class);
            $this->assertSame(2, $report->data($this->request(['loyalty_program_id' => $first->id]))['pagination']['total'], $class);
            $this->assertSame(0, $report->data($this->request(['to' => '2026-08-31']))['pagination']['total'], $class);
        }
    }

    public function test_reward_rankings_preserve_counts_points_and_program_and_date_filters(): void
    {
        $first = LoyaltyProgram::factory()->create();
        $second = LoyaltyProgram::factory()->create();
        $customer = User::factory()->create();
        $one = $this->membership($customer, $first, 100, 50);
        $two = $this->membership($customer, $second, 100, 50);
        $rewardOne = $this->reward($first);
        $rewardTwo = $this->reward($second);
        $this->gift($one, $rewardOne, '2026-09-02 00:00:00', 40);
        $this->gift($one, $rewardOne, '2026-09-03 23:59:59', 60);
        $this->gift($two, $rewardTwo, '2026-09-03 00:00:00', 80);
        $this->gift($one, $rewardOne, null, 900);
        $this->gift($one, $rewardOne, '2026-09-03 00:00:00', 900, ['deleted_at' => now()]);
        $filters = ['from' => '2026-09-02', 'to' => '2026-09-03'];
        foreach ([LeastUsedRewardsReport::class, MostRedeemedRewardsReport::class] as $class) {
            $report = app($class);
            $rows = $report->query($this->request($filters))->get();
            $this->assertCount(2, $rows);
            $expected = $class === LeastUsedRewardsReport::class ? [1, 2] : [2, 1];
            $this->assertSame($expected, $rows->pluck('total_redemptions')->map(fn ($n) => (int) $n)->all());
            $this->assertSame(2, $report->data($this->request($filters))['pagination']['total']);
            $selected = $report->query($this->request($filters + ['loyalty_program_id' => $first->id]))->get();
            $this->assertCount(1, $selected);
            $this->assertSame(2, (int) $selected->first()->total_redemptions);
            if ($class === MostRedeemedRewardsReport::class) {
                $this->assertSame(100, (int) $selected->first()->total_points_spent);
            }
            $daily = $report->query($this->request(['from' => '2026-09-03', 'to' => '2026-09-03']))->get();
            $this->assertSame(2, (int) $daily->sum('total_redemptions'));
        }
    }

    private function request(array $filters): Request
    {
        return new Request(['filters' => $filters]);
    }

    private function membership(User $customer, LoyaltyProgram $program, int $lifetime, int $balance, array $extra = []): int
    {
        return DB::table('loyalty_customers')->insertGetId($extra + [
            'customer_id' => $customer->id, 'loyalty_program_id' => $program->id,
            'lifetime_points' => $lifetime, 'points_balance' => $balance,
            'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 00:00:00',
        ]);
    }

    private function transaction(int $membership, string $type, string $date, array $extra = []): void
    {
        DB::table('loyalty_transactions')->insert($extra + [
            'loyalty_customer_id' => $membership, 'type' => $type, 'points' => $type === 'redeem' ? -10 : 10,
            'created_at' => $date, 'updated_at' => $date,
        ]);
    }

    private function reward(LoyaltyProgram $program, ?LoyaltyTier $tier = null): LoyaltyReward
    {
        return LoyaltyReward::factory()->create([
            'loyalty_program_id' => $program->id, 'loyalty_tier_id' => $tier?->id,
            'name' => ['en' => 'Same reward'], 'type' => 'discount', 'points_cost' => 100,
            'created_at' => '2026-09-01 00:00:00',
        ]);
    }

    private function gift(int $membership, LoyaltyReward $reward, ?string $date, int $points, array $extra = []): void
    {
        DB::table('loyalty_gifts')->insert($extra + [
            'loyalty_customer_id' => $membership, 'loyalty_program_id' => $reward->loyalty_program_id,
            'loyalty_reward_id' => $reward->id, 'type' => 'discount', 'status' => $date ? 'used' : 'available',
            'used_at' => $date, 'points_spent' => $points, 'created_at' => '2026-09-01 00:00:00',
        ]);
    }
}
