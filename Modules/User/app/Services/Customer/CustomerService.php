<?php

namespace Modules\User\Services\Customer;

use App\Forkiva;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Modules\Support\Country;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Enums\CustomerType;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Events\AccountActivated;
use Modules\User\Events\PasswordChanged;
use Modules\User\Exports\CustomerExportDefinition;
use Modules\User\Models\User;

class CustomerService implements CustomerServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('user::users.user');
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
            ->role(DefaultRole::Customer)
            ->withoutGlobalActive()
            ->findOrFail($id);
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
    public function store(array $data): User
    {
        $data['customer_type'] = $data['customer_type'] ?? CustomerType::Regular->value;
        /** @var UploadedFile|null $profilePhoto */
        $profilePhoto = $data['profile_photo'] ?? null;

        /** @var User $customer */
        $customer = $this->getModel()
            ->query()
            ->create(Arr::except($data, ['profile_photo', 'remove_profile_photo']))
            ->assignRole(DefaultRole::Customer);

        if ($profilePhoto instanceof UploadedFile) {
            $customer->replaceProfilePhoto($profilePhoto);
            $customer->refresh();
        }

        return $customer;
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);
        $wasActive = (bool) $user->is_active;
        $passwordWillChange = filled($data['password'] ?? null);

        $exceptAttributes = [];

        if (empty($data['password'])) {
            $exceptAttributes[] = 'password';
        }

        /** @var UploadedFile|null $profilePhoto */
        $profilePhoto = $data['profile_photo'] ?? null;
        $removeProfilePhoto = (bool) ($data['remove_profile_photo'] ?? false);

        $user->update(Arr::except($data, [...$exceptAttributes, 'profile_photo', 'remove_profile_photo']));

        if ($removeProfilePhoto) {
            $user->clearProfilePhoto();
        } elseif ($profilePhoto instanceof UploadedFile) {
            $user->replaceProfilePhoto($profilePhoto);
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
            ->withoutGlobalActive()
            ->role(DefaultRole::Customer)
            ->whereIn('id', parseIds($ids))
            ->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        return [
            [
                'key' => 'gender',
                'label' => __('user::customers.filters.gender'),
                'type' => 'select',
                'options' => GenderType::toArrayTrans(),
            ],
            GlobalStructureFilters::active(),
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(): array
    {
        return [
            'genders' => GenderType::toArrayTrans(),
            'countries' => Country::toList(),
            'customer_types' => CustomerType::toArrayTrans(),
        ];
    }

    /** {@inheritDoc} */
    public function search(?string $query = null): Collection
    {
        if (is_null($query)) {
            return collect();
        }

        return $this->getModel()
            ->query()
            ->role(DefaultRole::Customer)
            ->search($query)
            ->limit(5)
            ->get();
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
            ->role(DefaultRole::Customer)
            ->with(['branch:id,name', 'printer:id,name', 'createdBy:id,name'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'customers';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.customers.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return CustomerExportDefinition::class;
    }
}
