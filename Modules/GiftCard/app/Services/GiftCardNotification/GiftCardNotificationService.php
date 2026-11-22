<?php

namespace Modules\GiftCard\Services\GiftCardNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\GiftCard\Events\GiftCardExpired;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\Support\Money;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class GiftCardNotificationService implements GiftCardNotificationServiceInterface
{
    /**
     * Create a new GiftCardNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendIssued(GiftCard $giftCard): void
    {
        $giftCard = $this->loadGiftCard($giftCard);

        $this->dispatch(
            templateKey: 'gift_card_issued',
            giftCard: $giftCard,
            variables: array_merge($this->baseGiftCardVariables($giftCard), [
                'amount' => $this->moneyAmount($giftCard->initial_balance),
                'expiry_date' => $this->expiryDate($giftCard),
                'balance' => $this->moneyAmount($giftCard->current_balance),
            ]),
        );
    }

    /** {@inheritDoc} */
    public function sendRedeemed(GiftCardTransaction $transaction): void
    {
        $transaction = $this->loadTransaction($transaction);

        $this->dispatch(
            templateKey: 'gift_card_redeemed',
            giftCard: $transaction->giftCard,
            variables: array_merge($this->baseGiftCardVariables($transaction->giftCard), [
                'amount' => $this->moneyAmount($transaction->amount),
                'balance' => $this->moneyAmount($transaction->balance_after),
            ]),
        );
    }

    /** {@inheritDoc} */
    public function sendRecharged(GiftCardTransaction $transaction): void
    {
        $transaction = $this->loadTransaction($transaction);

        $this->dispatch(
            templateKey: 'gift_card_recharged',
            giftCard: $transaction->giftCard,
            variables: array_merge($this->baseGiftCardVariables($transaction->giftCard), [
                'amount' => $this->moneyAmount($transaction->amount),
                'balance' => $this->moneyAmount($transaction->balance_after),
            ]),
        );
    }

    /** {@inheritDoc} */
    public function sendAdjusted(GiftCardTransaction $transaction): void
    {
        $transaction = $this->loadTransaction($transaction);

        $this->dispatch(
            templateKey: 'gift_card_adjusted',
            giftCard: $transaction->giftCard,
            variables: array_merge($this->baseGiftCardVariables($transaction->giftCard), [
                'adjustment_amount' => $this->moneyAmount($transaction->amount),
                'balance' => $this->moneyAmount($transaction->balance_after),
                'adjustment_reason' => $transaction->notes ?: $this->adjustmentReasonFallback(),
            ]),
        );
    }

    /** {@inheritDoc} */
    public function sendExpired(GiftCardExpired $event): void
    {
        $giftCard = $this->loadGiftCard($event->giftCard);
        $transaction = $event->transaction ? $this->loadTransaction($event->transaction) : null;

        $this->dispatch(
            templateKey: 'gift_card_expired',
            giftCard: $giftCard,
            variables: array_merge($this->baseGiftCardVariables($giftCard), [
                'expiry_date' => $this->expiryDate($giftCard),
                'balance' => $this->moneyAmount($transaction?->balance_after ?: $giftCard->current_balance),
            ]),
        );
    }

    /**
     * Dispatch one gift-card notification template when recipients exist.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function dispatch(string $templateKey, GiftCard $giftCard, array $variables): void
    {
        $roleKeys = $this->candidateRoleKeys($templateKey);
        if ($roleKeys === []) {
            return;
        }

        $recipients = $this->recipients($giftCard, $roleKeys);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send($templateKey, $recipients, $variables);
    }

    /**
     * Load gift-card relations required for notifications.
     */
    protected function loadGiftCard(GiftCard $giftCard): GiftCard
    {
        $giftCard->loadMissing([
            'branch',
            'customer.roles',
            'batch',
        ]);

        return $giftCard;
    }

    /**
     * Load transaction relations required for notifications.
     */
    protected function loadTransaction(GiftCardTransaction $transaction): GiftCardTransaction
    {
        $transaction->loadMissing([
            'giftCard.branch',
            'giftCard.customer.roles',
            'giftCard.batch',
        ]);

        return $transaction;
    }

    /**
     * Resolve candidate recipients for one gift-card workflow.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<int, User>
     */
    protected function recipients(GiftCard $giftCard, array $roleKeys): array
    {
        $recipients = [];

        if (in_array(DefaultRole::Customer->value, $roleKeys, true) && $giftCard->customer instanceof User && filled($giftCard->customer->id)) {
            $recipients[$giftCard->customer->id] = $giftCard->customer;
        }

        foreach ($this->branchRecipients($giftCard->branch_id, $roleKeys) as $user) {
            if ($user instanceof User && filled($user->id)) {
                $recipients[$user->id] = $user;
            }
        }

        return array_values($recipients);
    }

    /**
     * Resolve branch-scoped and global-admin recipients for one gift-card template.
     *
     * @param  array<int, string>  $roleKeys
     * @return Collection<int, User>
     */
    protected function branchRecipients(?int $branchId, array $roleKeys): Collection
    {
        $globalRoles = array_values(array_intersect($roleKeys, [DefaultRole::Admin->value]));
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles, [DefaultRole::Customer->value]));

        if (! $branchId && $globalRoles === []) {
            return collect();
        }

        if ($branchRoles === [] && $globalRoles === []) {
            return collect();
        }

        return User::query()
            ->withOutGlobalBranchPermission()
            ->where(function (Builder $query) use ($branchId, $branchRoles, $globalRoles) {
                if ($branchId && $branchRoles !== []) {
                    $query->where(function (Builder $branchQuery) use ($branchId, $branchRoles) {
                        $branchQuery
                            ->where('branch_id', $branchId)
                            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', $branchRoles));
                    });
                }

                if ($globalRoles !== []) {
                    $method = $branchId && $branchRoles !== [] ? 'orWhere' : 'where';

                    $query->{$method}(function (Builder $globalQuery) use ($globalRoles) {
                        $globalQuery->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', $globalRoles));
                    });
                }
            })
            ->get()
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
     * Build the shared gift-card template variables.
     *
     * @return array<string, mixed>
     */
    protected function baseGiftCardVariables(GiftCard $giftCard): array
    {
        return [
            'type' => 'gift_card',
            'type_id' => $giftCard->id,
            'gift_card_code' => $giftCard->code,
            'customer_name' => $giftCard->customer?->name ?: '-',
            'currency' => $giftCard->currency,
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /**
     * Format the expiry date for template variables.
     */
    protected function expiryDate(GiftCard $giftCard): string
    {
        return $giftCard->expiry_date?->toDateString() ?: '-';
    }

    /**
     * Format one money value for template variables.
     */
    protected function moneyAmount(Money $money): string
    {
        return $money->round()->format();
    }

    /**
     * Resolve the default adjustment reason used when no note is present.
     *
     * @return array<string, string>
     */
    protected function adjustmentReasonFallback(): array
    {
        return collect(supportedLocaleKeys())
            ->mapWithKeys(fn (string $locale) => [
                $locale => trans('giftcard::messages.notification_action_adjusted', locale: $locale),
            ])
            ->all();
    }
}
