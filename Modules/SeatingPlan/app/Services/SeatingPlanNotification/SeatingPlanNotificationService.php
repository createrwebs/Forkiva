<?php

namespace Modules\SeatingPlan\Services\SeatingPlanNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\SeatingPlan\Events\TableAssignWaiter;
use Modules\SeatingPlan\Events\TableUpdateStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class SeatingPlanNotificationService implements SeatingPlanNotificationServiceInterface
{
    /**
     * Create a new SeatingPlanNotificationService instance.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendAssignedToWaiter(TableAssignWaiter $event): void
    {
        if (! $event->waiterId) {
            return;
        }

        $table = $this->loadTable($event->table);
        $waiter = $table->waiter;
        if (! $waiter) {
            return;
        }

        $oldWaiter = $event->oldWaiterId
            ? $this->findUser($event->oldWaiterId)
            : null;

        $recipients = $this->recipients($table, 'table_assigned_to_waiter', [$waiter, $oldWaiter]);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send('table_assigned_to_waiter', $recipients, $this->tableVariables(
            table: $table,
            waiter: $waiter,
        ));
    }

    /**
     * Load the table graph required for notifications.
     */
    protected function loadTable(Table $table): Table
    {
        $table->loadMissing([
            'branch',
            'floor',
            'zone',
            'waiter.roles',
        ]);

        return $table;
    }

    /**
     * Find a user without global branch or active scopes.
     */
    protected function findUser(int $id): ?User
    {
        return User::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed()
            ->with('roles')
            ->find($id);
    }

    /**
     * Resolve candidate recipients for a table workflow.
     *
     * @param  array<int, User|null>  $users
     * @return array<int, User>
     */
    protected function recipients(Table $table, string $templateKey, array $users = []): array
    {
        return collect($users)
            ->merge($this->branchRecipients($table->branch_id, $templateKey))
            ->filter(fn ($user) => $user instanceof User && filled($user->id))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Resolve branch-scoped and global-admin recipients for a table template.
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
     * Build template variables for table notifications.
     *
     * @param  array<string, mixed>|null  $status
     * @return array<string, mixed>
     */
    protected function tableVariables(
        Table $table,
        ?User $waiter = null,
        ?array $status = null,
    ): array {
        return [
            'type' => 'table',
            'type_id' => $table->id,
            'table_name' => $table->getTranslations('name') ?: $table->name,
            'waiter_name' => $waiter?->name ?: '-',
            'table_status' => $status ?: $table->status->allTrans(),
            'branch_name' => $table->branch?->getTranslations('name') ?: '-',
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /** {@inheritDoc} */
    public function sendStatusChanged(TableUpdateStatus $event): void
    {
        $table = $this->loadTable($event->table);
        $recipients = $this->recipients($table, 'table_status_changed', [$table->waiter]);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send('table_status_changed', $recipients, $this->tableVariables(
            table: $table,
            waiter: $table->waiter,
            status: $event->status->allTrans(),
        ));
    }
}
