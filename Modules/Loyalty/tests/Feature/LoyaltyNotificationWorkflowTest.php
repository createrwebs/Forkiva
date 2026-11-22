<?php

namespace Modules\Loyalty\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\Loyalty\Enums\LoyaltyRewardType;
use Modules\Loyalty\Events\LoyaltyRewardRedeemed;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Loyalty\Models\LoyaltyProgram;
use Modules\Loyalty\Models\LoyaltyReward;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\Loyalty\LoyaltyServiceInterface;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class LoyaltyNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_earning_points_sends_loyalty_points_changed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('loyalty_points_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $program = LoyaltyProgram::factory()->active()->create([
            'earning_rate' => 1,
            'redemption_rate' => 0.01,
            'min_redeem_points' => 10,
        ]);

        LoyaltyTier::factory()->create([
            'loyalty_program_id' => $program->id,
            'min_spend' => 0,
            'multiplier' => 1,
            'is_active' => true,
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'total' => 25,
            'due_amount' => 25,
        ]);

        app(LoyaltyServiceInterface::class)->earnForOrder($order, [
            'program_id' => $program->id,
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'loyalty_customer'
                && $this->notificationTitleMatchesBase($payload, 'Loyalty Points Changed');
        });
    }

    public function test_reward_redemption_sends_loyalty_reward_redeemed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $actor = $this->createUserWithRole(DefaultRole::AdminBranch->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($actor);

        $this->setWorkflowChannels('loyalty_reward_redeemed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);
        $this->setWorkflowChannels('loyalty_points_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $program = LoyaltyProgram::factory()->active()->create([
            'earning_rate' => 1,
            'redemption_rate' => 0.01,
            'min_redeem_points' => 10,
        ]);
        $tier = LoyaltyTier::factory()->create([
            'loyalty_program_id' => $program->id,
            'min_spend' => 0,
            'multiplier' => 1,
            'is_active' => true,
        ]);

        LoyaltyCustomer::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $program->id,
            'loyalty_tier_id' => $tier->id,
            'points_balance' => 500,
            'lifetime_points' => 500,
        ]);

        $reward = LoyaltyReward::query()->create([
            'loyalty_program_id' => $program->id,
            'loyalty_tier_id' => $tier->id,
            'name' => ['en' => 'Free Dessert', 'ar' => 'حلوى مجانية'],
            'description' => ['en' => 'Reward', 'ar' => 'مكافأة'],
            'type' => LoyaltyRewardType::FreeItem->value,
            'points_cost' => 100,
            'currency' => 'JOD',
            'value' => null,
            'value_type' => 'fixed',
            'max_redemptions_per_order' => 1,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'conditions' => [],
            'meta' => [],
            'total_redeemed' => 0,
            'total_customers' => 0,
            'is_active' => true,
            'order' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $gift = LoyaltyGift::query()->create([
            'loyalty_customer_id' => LoyaltyCustomer::query()->where('customer_id', $customer->id)->value('id'),
            'loyalty_program_id' => $program->id,
            'loyalty_reward_id' => $reward->id,
            'artifact_id' => null,
            'artifact_type' => null,
            'type' => LoyaltyRewardType::FreeItem,
            'status' => 'available',
            'qty' => 1,
            'points_spent' => 100,
            'conditions' => [],
            'meta' => [],
            'valid_from' => now(),
            'valid_until' => now()->addDay(),
        ]);

        event(new LoyaltyRewardRedeemed(
            gift: $gift,
            action: [
                'en' => trans('loyalty::messages.notification_action_reward_redeemed', locale: 'en'),
                'ar' => trans('loyalty::messages.notification_action_reward_redeemed', locale: 'ar'),
            ],
            orderNumber: null,
            branchId: $branch->id,
        ));

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'loyalty_gift'
                && $this->notificationTitleMatchesBase($payload, 'Loyalty Reward Redeemed');
        });
    }

    public function test_tier_change_sends_loyalty_tier_changed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('loyalty_points_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);
        $this->setWorkflowChannels('loyalty_tier_changed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $program = LoyaltyProgram::factory()->active()->create([
            'earning_rate' => 1,
            'redemption_rate' => 0.01,
            'min_redeem_points' => 10,
        ]);

        LoyaltyTier::factory()->create([
            'loyalty_program_id' => $program->id,
            'min_spend' => 0,
            'multiplier' => 1,
            'is_active' => true,
        ]);
        LoyaltyTier::factory()->create([
            'loyalty_program_id' => $program->id,
            'min_spend' => 10,
            'multiplier' => 1.25,
            'is_active' => true,
            'name' => ['en' => 'Gold', 'ar' => 'ذهبي'],
        ]);

        $order = $this->createOrder($branch, [
            'customer_id' => $customer->id,
            'total' => 25,
            'due_amount' => 25,
        ]);

        app(LoyaltyServiceInterface::class)->earnForOrder($order, [
            'program_id' => $program->id,
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'loyalty_customer'
                && $this->notificationTitleMatchesBase($payload, 'Loyalty Tier Changed');
        });
    }
}
