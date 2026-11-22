<?php

namespace Modules\GiftCard\tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Branch\Models\Branch;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class GiftCardNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_gift_card_issued_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_issued', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        app(GiftCardServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'initial_balance' => 50,
            'status' => GiftCardStatus::Active,
            'expiry_date' => now()->addMonth()->toDateString(),
            'notes' => 'Issued for customer',
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Issued');
        });
    }

    public function test_gift_card_redeemed_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_redeemed', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $giftCard = GiftCard::factory()
            ->branchScoped($branch->id)
            ->forCustomer($customer)
            ->withBalance(100, 100)
            ->create([
                'currency' => $branch->currency,
                'status' => GiftCardStatus::Active,
            ]);

        app(GiftCardTransactionServiceInterface::class)->record($giftCard, [
            'type' => GiftCardTransactionType::Redeem,
            'amount' => 25,
            'branch_id' => $branch->id,
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Redeemed');
        });
    }

    public function test_gift_card_recharged_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_recharged', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $giftCard = GiftCard::factory()
            ->branchScoped($branch->id)
            ->forCustomer($customer)
            ->withBalance(50, 20)
            ->create([
                'currency' => $branch->currency,
                'status' => GiftCardStatus::Active,
            ]);

        app(GiftCardTransactionServiceInterface::class)->record($giftCard, [
            'type' => GiftCardTransactionType::Recharge,
            'amount' => 15,
            'branch_id' => $branch->id,
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Recharged');
        });
    }

    public function test_gift_card_adjusted_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_adjusted', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        $giftCard = GiftCard::factory()
            ->branchScoped($branch->id)
            ->forCustomer($customer)
            ->withBalance(40, 10)
            ->create([
                'currency' => $branch->currency,
                'status' => GiftCardStatus::Active,
            ]);

        app(GiftCardTransactionServiceInterface::class)->record($giftCard, [
            'type' => GiftCardTransactionType::Adjustment,
            'amount' => 5,
            'branch_id' => $branch->id,
        ]);

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Adjusted');
        });
    }

    public function test_gift_card_expired_sends_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $customer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_expired', [
            'in_app' => [
                DefaultRole::Customer->value => true,
            ],
        ]);

        GiftCard::factory()
            ->branchScoped($branch->id)
            ->forCustomer($customer)
            ->withBalance(30, 12)
            ->create([
                'currency' => $branch->currency,
                'status' => GiftCardStatus::Active,
                'expiry_date' => now()->subDay(),
            ]);

        app(GiftCardTransactionServiceInterface::class)->expireEligibleCards();

        Notification::assertSentTo($customer, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($customer) {
            $payload = $notification->toDatabase($customer);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Expired');
        });
    }

    public function test_gift_card_with_nullable_customer_only_notifies_allowed_internal_recipients(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $manager = $this->createUserWithRole(DefaultRole::Manager->value, [
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $otherCustomer = $this->createCustomerUser([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('gift_card_issued', [
            'in_app' => [
                DefaultRole::Manager->value => true,
                DefaultRole::Customer->value => true,
            ],
        ]);

        app(GiftCardServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'customer_id' => null,
            'initial_balance' => 50,
            'status' => GiftCardStatus::Active,
            'expiry_date' => now()->addMonth()->toDateString(),
            'notes' => 'Issued without customer',
        ]);

        Notification::assertSentTo($manager, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($manager) {
            $payload = $notification->toDatabase($manager);

            return $channels === ['database']
                && $payload['type'] === 'gift_card'
                && $this->notificationTitleMatchesBase($payload, 'Gift Card Issued');
        });

        Notification::assertNotSentTo($otherCustomer, RenderedTemplateNotification::class);
    }
}
