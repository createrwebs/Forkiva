<?php

namespace Modules\Loyalty\Services\LoyaltyNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Loyalty\Events\LoyaltyPointsChanged;
use Modules\Loyalty\Events\LoyaltyRewardRedeemed;
use Modules\Loyalty\Events\LoyaltyTierChanged;
use Modules\Loyalty\Models\LoyaltyCustomer;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class LoyaltyNotificationService implements LoyaltyNotificationServiceInterface
{
    /**
     * Create a new LoyaltyNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendPointsChanged(LoyaltyPointsChanged $event): void
    {
        $loyaltyCustomer = $this->loadCustomer($event->loyaltyCustomer);
        $recipients = $this->recipients($loyaltyCustomer, 'loyalty_points_changed', $event->branchId);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send('loyalty_points_changed', $recipients, [
            'type' => 'loyalty_customer',
            'type_id' => $loyaltyCustomer->id,
            'customer_name' => $loyaltyCustomer->customer?->name ?: '-',
            'points_change' => $this->signedPoints($event->points),
            'points_balance' => (string) $loyaltyCustomer->points_balance,
            'app_name' => NotificationVariableValue::appName(),
        ]);
    }

    /**
     * Load the loyalty customer graph required for notifications.
     */
    protected function loadCustomer(LoyaltyCustomer $loyaltyCustomer): LoyaltyCustomer
    {
        $loyaltyCustomer->loadMissing([
            'customer.roles',
            'loyaltyTier',
            'loyaltyProgram',
        ]);

        return $loyaltyCustomer;
    }

    /**
     * Resolve candidate recipients for a loyalty workflow.
     *
     * @return array<int, User>
     */
    protected function recipients(LoyaltyCustomer $loyaltyCustomer, string $templateKey, ?int $branchId): array
    {
        return collect([
            $loyaltyCustomer->customer,
        ])
            ->merge($this->branchRecipients($branchId, $templateKey))
            ->filter(fn ($user) => $user instanceof User && filled($user->id))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resolve branch-scoped and global-admin recipients for a loyalty template.
     *
     * @return Collection<int, User>
     */
    protected function branchRecipients(?int $branchId, string $templateKey): Collection
    {
        $roleKeys = $this->candidateRoleKeys($templateKey);
        if ($roleKeys === []) {
            return collect();
        }

        $globalRoles = array_values(array_intersect($roleKeys, [DefaultRole::Admin->value]));
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles, [DefaultRole::Customer->value]));

        $branchRecipients = ($branchId && $branchRoles !== [])
            ? User::query()
                ->withOutGlobalBranchPermission()
                ->where('branch_id', $branchId)
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $branchRoles))
                ->get()
            : collect();

        $globalRecipients = $globalRoles === []
            ? collect()
            : User::query()
                ->withOutGlobalBranchPermission()
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $globalRoles))
                ->get();

        return $branchRecipients
            ->merge($globalRecipients)
            ->unique('id')
            ->values();
    }

    /**
     * Resolve all allowed role keys for a template across supported channels.
     *
     * @return array<int, string>
     */
    protected function candidateRoleKeys(string $templateKey): array
    {
        return collect(['email', 'in_app', 'push_notification', 'sms', 'whatsapp'])
            ->flatMap(fn (string $channel) => NotificationWorkflowRecipientCatalog::allowedRolesFor($templateKey, $channel))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Format a points delta with a sign for notification copy.
     */
    protected function signedPoints(int $points): string
    {
        return $points > 0 ? "+$points" : (string) $points;
    }

    /** {@inheritDoc} */
    public function sendRewardRedeemed(LoyaltyRewardRedeemed $event): void
    {
        $gift = $this->loadGift($event->gift);
        $loyaltyCustomer = $gift->customer;
        $rewardName = $gift->reward?->getTranslations('name') ?: $gift->reward?->name;
        $recipients = $this->recipients($loyaltyCustomer, 'loyalty_reward_redeemed', $event->branchId);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send('loyalty_reward_redeemed', $recipients, [
            'type' => 'loyalty_gift',
            'type_id' => $gift->id,
            'customer_name' => $loyaltyCustomer->customer?->name ?: '-',
            'points_used' => (string) $gift->points_spent,
            'points_balance' => (string) $loyaltyCustomer->points_balance,
            'reward_name' => $rewardName ?: '-',
            'app_name' => NotificationVariableValue::appName(),
        ]);
    }

    /**
     * Load the loyalty gift graph required for notifications.
     */
    protected function loadGift(LoyaltyGift $gift): LoyaltyGift
    {
        $gift->loadMissing([
            'customer.customer.roles',
            'reward',
        ]);

        return $gift;
    }

    /** {@inheritDoc} */
    public function sendTierChanged(LoyaltyTierChanged $event): void
    {
        $loyaltyCustomer = $this->loadCustomer($event->loyaltyCustomer);
        $recipients = $this->recipients($loyaltyCustomer, 'loyalty_tier_changed', $event->branchId);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send('loyalty_tier_changed', $recipients, [
            'type' => 'loyalty_customer',
            'type_id' => $loyaltyCustomer->id,
            'customer_name' => $loyaltyCustomer->customer?->name ?: '-',
            'previous_tier' => $this->tierName($event->oldTier),
            'new_tier' => $this->tierName($event->newTier),
            'points_balance' => (string) $loyaltyCustomer->points_balance,
            'app_name' => NotificationVariableValue::appName(),
        ]);
    }

    /**
     * Resolve a loyalty tier name for templates.
     *
     * @return array<string, string>|string
     */
    protected function tierName(?LoyaltyTier $tier): array|string
    {
        if ($tier?->getTranslations('name')) {
            return $tier->getTranslations('name');
        }

        return $tier?->name ?: '-';
    }
}
