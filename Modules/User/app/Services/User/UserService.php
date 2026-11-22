<?php

namespace Modules\User\Services\User;

use App\Forkiva;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Category\Models\Category;
use Modules\Printer\Models\Printer;
use Modules\Support\Country;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Events\AccountActivated;
use Modules\User\Events\PasswordChanged;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Services\Role\RoleServiceInterface;

class UserService implements UserServiceInterface
{
    public function __construct(protected BranchAccessServiceInterface $branchAccess) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('user::users.user');
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->getModel()
            ->query()
            ->withoutGlobalActive()
            ->role(roles: DefaultRole::Customer, without: true)
            ->with(['branch:id,name', 'printer:id,name', 'createdBy:id,name'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function getModel(): User
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return User::class;
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'users';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.users.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return User::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): User
    {
        return $this->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|User
    {
        return $this->getModel()
            ->query()
            ->role(roles: DefaultRole::Customer, without: true)
            ->withoutGlobalActive()
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function store(array $data): User
    {
        $role = app(RoleServiceInterface::class)->find($data['role']);

        if ($role->name !== DefaultRole::Kitchen->value) {
            $data['printer_id'] = null;
            $data['category_slugs'] = null;
        }

        $user = $this->getModel()->query()->create(Arr::except($data, ['role']));

        $user->syncRoles([$data['role']]);

        return $user;
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);
        $wasActive = (bool) $user->is_active;
        $passwordWillChange = filled($data['password'] ?? null);
        $role = app(RoleServiceInterface::class)->find($data['role']);

        if ($role->name !== DefaultRole::Kitchen->value) {
            $data['printer_id'] = null;
            $data['category_slugs'] = null;
        }

        if ($user->isMainUser()) {
            $data['is_active'] = true;
        }

        $exceptAttributes = ['role'];

        if (empty($data['password'])) {
            $exceptAttributes[] = 'password';
        }

        $user->update(Arr::except($data, $exceptAttributes));

        if (! $user->isMainUser()) {
            $user->syncRoles([$data['role']]);
        }

        $user = $user->fresh();

        if (! $wasActive && $user->is_active) {
            event(new AccountActivated(
                user: $user,
                activatedAt: Carbon::now(),
                locale: locale(),
            ));
        }

        if ($passwordWillChange) {
            event(new PasswordChanged(
                user: $user,
                ipAddress: request()->ip(),
                changedAt: Carbon::now(),
                locale: locale(),
            ));
        }

        return $user;
    }

    /** {@inheritDoc} */
    public function destroy(int|array|string $ids): bool
    {
        return $this->getModel()
            ->query()
            ->role(roles: DefaultRole::Customer, without: true)
            ->withoutGlobalActive()
            ->whereNot('id', 1)
            ->whereIn('id', parseIds($ids))
            ->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'role',
                'label' => __('user::users.filters.role'),
                'type' => 'select',
                'options' => Role::list(auth()->user()->assignedToBranch()),
            ],
            [
                'key' => 'gender',
                'label' => __('user::users.filters.gender'),
                'type' => 'select',
                'options' => GenderType::toArrayTrans(),
            ],
            GlobalStructureFilters::active(),
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'roles' => Role::list($this->branchAccess->assignedBranchId() !== null),
                'branches' => Branch::list(true),
                'genders' => GenderType::toArrayTrans(),
                'categories' => Category::listWithSlug(),
                'branch_available_roles' => DefaultRole::getBranchAvailableRoles(),
                'countries' => Country::toList(),
            ];
        }

        return [
            'printers' => Printer::list($resolvedBranchId),
        ];
    }
}
