<?php

namespace Modules\Pos\Services\PosNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\Pos\Models\PosSession;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class PosNotificationService implements PosNotificationServiceInterface
{
    /**
     * Create a new PosNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendSessionOpened(PosSession $session): void
    {
        $session = $this->loadSession($session);

        $this->dispatch(
            templateKey: 'pos_session_opened',
            session: $session,
            variables: $this->openedSessionVariables($session)
        );
    }

    /**
     * Load relations required for POS notifications.
     */
    protected function loadSession(PosSession $session): PosSession
    {
        $session->loadMissing([
            'branch',
            'posRegister',
            'openedBy',
            'closedBy',
        ]);

        return $session;
    }

    /**
     * Dispatch one POS notification template when recipients exist.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function dispatch(string $templateKey, PosSession $session, array $variables): void
    {
        $recipients = $this->recipients($session, $templateKey);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send($templateKey, $recipients, $variables);
    }

    /**
     * Resolve candidate recipients for one POS workflow.
     *
     * @return array<int, User>
     */
    protected function recipients(PosSession $session, string $templateKey): array
    {
        return collect([
            $session->openedBy,
            $session->closedBy,
        ])
            ->merge($this->branchRecipients($session->branch_id, $templateKey))
            ->filter(fn ($user) => $user instanceof User && filled($user->id))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resolve branch-scoped and global-admin recipients for a POS template.
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
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles));

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
     * Build template variables for an opened POS session.
     *
     * @return array<string, mixed>
     */
    protected function openedSessionVariables(PosSession $session): array
    {
        return array_merge($this->baseSessionVariables($session), [
            'cashier_name' => $session->openedBy?->name ?: '-',
            'opened_at' => dateTimeFormat($session->opened_at),
            'branch_name' => $session->branch?->getTranslations('name') ?: '-',
            'opening_amount' => $session->opening_float->round()->format(),
            'currency' => $session->branch?->currency ?: $session->currency,
        ]);
    }

    /**
     * Build the shared POS session template metadata.
     *
     * @return array<string, mixed>
     */
    protected function baseSessionVariables(PosSession $session): array
    {
        return [
            'type' => 'pos_session',
            'type_id' => $session->id,
            'session_number' => (string) $session->id,
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /** {@inheritDoc} */
    public function sendSessionClosed(PosSession $session): void
    {
        $session = $this->loadSession($session);

        $this->dispatch(
            templateKey: 'pos_session_closed',
            session: $session,
            variables: $this->closedSessionVariables($session)
        );
    }

    /**
     * Build template variables for a closed POS session.
     *
     * @return array<string, mixed>
     */
    protected function closedSessionVariables(PosSession $session): array
    {
        return array_merge($this->baseSessionVariables($session), [
            'cashier_name' => $session->closedBy?->name ?: $session->openedBy?->name ?: '-',
            'closed_at' => dateTimeFormat($session->closed_at ?: now()),
            'branch_name' => $session->branch?->getTranslations('name') ?: '-',
            'closing_amount' => $session->declared_cash?->round()->format() ?: '0',
            'currency' => $session->branch?->currency ?: $session->currency,
        ]);
    }

    /** {@inheritDoc} */
    public function sendCashVarianceFound(PosSession $session): void
    {
        $session = $this->loadSession($session);

        if (! $session->cash_over_short || round($session->cash_over_short->amount(), 4) === 0.0) {
            return;
        }

        $this->dispatch(
            templateKey: 'pos_cash_variance_found',
            session: $session,
            variables: $this->cashVarianceVariables($session)
        );
    }

    /**
     * Build template variables for a POS cash variance alert.
     *
     * @return array<string, mixed>
     */
    protected function cashVarianceVariables(PosSession $session): array
    {
        return array_merge($this->baseSessionVariables($session), [
            'cashier_name' => $session->closedBy?->name ?: $session->openedBy?->name ?: '-',
            'branch_name' => $session->branch?->getTranslations('name') ?: '-',
            'variance_amount' => $session->cash_over_short?->round()->format() ?: '0',
            'currency' => $session->branch?->currency ?: $session->currency,
        ]);
    }
}
