<?php

namespace Modules\Notification\Services\NotificationRecipient;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Module;
use Modules\Delivery\Models\DeliveryDriver;
use Modules\Notification\Enums\NotificationContext;
use Modules\User\Models\Role;
use Modules\User\Models\User;

class NotificationRecipientRoleResolverService implements NotificationRecipientRoleResolverServiceInterface
{
    /**
     * Cached recipient role definitions for this resolver instance.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $roles = null;

    /**
     * Cached profile-derived contexts keyed by user id.
     *
     * @var array<int, array<int, string>>
     */
    protected array $contexts = [];

    /** {@inheritDoc} */
    public function keysFor(User $user): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles
            : $user->roles()->get(['name']);

        return $roles
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** {@inheritDoc} */
    public function all(): array
    {
        if ($this->roles !== null) {
            return $this->roles;
        }

        $roles = Role::query()
            ->select(['id', 'name', 'display_name', 'built_in'])
            ->orderByDesc('built_in')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'key' => $role->name,
                'name' => $role->display_name,
            ])
            ->values()
            ->all();

        return $this->roles = $roles;
    }

    /** {@inheritDoc} */
    public function contextsFor(User $user): array
    {
        $userId = $user->getKey();
        if ($userId === null) {
            return [];
        }

        if (! array_key_exists((int) $userId, $this->contexts)) {
            $this->preloadContextsFor(collect([$user]));
        }

        return $this->contexts[(int) $userId] ?? [];
    }

    /** {@inheritDoc} */
    public function preloadContextsFor(Collection $users): void
    {
        $users = $users
            ->filter(fn ($user) => $user instanceof User && $user->getKey() !== null)
            ->reject(fn (User $user) => array_key_exists((int) $user->getKey(), $this->contexts))
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        if (! $this->moduleEnabled('Hr')) {
            $users->each(fn (User $user) => $this->contexts[(int) $user->getKey()] = []);

            return;
        }

        (new EloquentCollection($users->all()))->loadMissing('employee');

        $employeesByUserId = [];
        foreach ($users as $user) {
            $employee = $this->activeEmployeeFor($user);
            $userId = (int) $user->getKey();

            if (! $employee) {
                $this->contexts[$userId] = [];

                continue;
            }

            $employeesByUserId[$userId] = $employee;
        }

        $driverEmployeeIds = $this->activeDriverEmployeeIds($employeesByUserId);

        foreach ($employeesByUserId as $userId => $employee) {
            $contexts = [NotificationContext::Employee->value];

            if (isset($driverEmployeeIds[(int) $employee->getKey()])) {
                $contexts[] = NotificationContext::Driver->value;
            }

            $this->contexts[(int) $userId] = array_values(array_unique($contexts));
        }
    }

    /**
     * Determine whether an optional module is enabled.
     */
    protected function moduleEnabled(string $module): bool
    {
        return Module::find($module) || Module::find(strtolower($module));
    }

    /**
     * Resolve the user's active employee profile, when available.
     */
    protected function activeEmployeeFor(User $user): ?object
    {
        if (! $this->moduleEnabled('Hr')) {
            return null;
        }

        $user->loadMissing('employee');
        $employee = $user->employee;

        if (! $employee || ! $this->isTruthy($employee->is_active ?? true)) {
            return null;
        }

        return $employee;
    }

    /**
     * Normalize boolean-ish model flags.
     */
    protected function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    /**
     * Resolve employee ids with active delivery-driver profiles.
     *
     * @param  array<int, object>  $employeesByUserId
     * @return array<int, true>
     */
    protected function activeDriverEmployeeIds(array $employeesByUserId): array
    {
        if ($employeesByUserId === [] || ! $this->moduleEnabled('Delivery')) {
            return [];
        }

        $driverModel = DeliveryDriver::class;
        if (! class_exists($driverModel)) {
            return [];
        }

        $employeeIds = collect($employeesByUserId)
            ->map(fn (object $employee) => (int) $employee->getKey())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === []) {
            return [];
        }

        return array_fill_keys($driverModel::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->pluck('employee_id')
            ->map(fn ($employeeId) => (int) $employeeId)
            ->all(), true);
    }

    /** {@inheritDoc} */
    public function exists(string $role): bool
    {
        return in_array($role, $this->keys(), true);
    }

    /** {@inheritDoc} */
    public function keys(): array
    {
        return array_values(array_map(
            fn (array $role) => (string) $role['key'],
            $this->all()
        ));
    }
}
