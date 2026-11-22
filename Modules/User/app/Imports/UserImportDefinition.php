<?php

namespace Modules\User\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Support\Country;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Rules\Username;
use Modules\User\Services\User\UserServiceInterface;

class UserImportDefinition implements ImportDefinitionInterface
{
    public function __construct(
        private BranchAccessServiceInterface $branches,
        private UserServiceInterface $users,
    ) {}

    /** {@inheritDoc} */
    public function fields(): array
    {
        $assignedBranchId = $this->branches->assignedBranchId();

        return [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'name', 'label' => __('user::users.table.name'), 'type' => 'text', 'required' => true],
            ['key' => 'username', 'label' => __('user::users.table.username'), 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => __('user::users.table.email'), 'type' => 'text', 'required' => true],
            ['key' => 'phone_country_iso_code', 'label' => __('user::users.table.phone_country_iso_code'), 'type' => 'text', 'required' => true,
                'options' => array_map(fn (array $country) => ['value' => $country['id'], 'label' => $country['name']], Country::toList())],
            ['key' => 'phone', 'label' => __('user::users.table.phone'), 'type' => 'text', 'required' => true],
            ['key' => 'gender', 'label' => __('user::users.table.gender'), 'type' => 'text', 'required' => true,
                'options' => array_map(fn (GenderType $gender) => ['value' => $gender->value, 'label' => $gender->trans()], GenderType::cases())],
            ['key' => 'role', 'label' => __('user::attributes.users.role'), 'type' => 'integer', 'required' => true,
                'options' => Role::list($assignedBranchId !== null)->map(fn (array $role) => ['value' => (string) $role['id'], 'label' => $role['name']])->all()],
            ['key' => 'branch_id', 'label' => __('user::attributes.users.branch_id'), 'type' => 'integer', 'required' => false,
                'options' => Branch::query()->withoutGlobalActive()
                    ->when($assignedBranchId, fn ($query, $id) => $query->whereKey($id))
                    ->orderBy('id')->get(['id', 'name'])
                    ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])->all()],
            ['key' => 'is_active', 'label' => __('admin::admin.table.activation'), 'type' => 'boolean', 'required' => true],
        ];
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.users.import'), 403);
        if ($import && $this->branches->assignedBranchId() !== null) {
            abort_unless(($import->context['branch_id'] ?? null) === $this->branches->assignedBranchId(), 404);
        }
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return ['branch_id' => $this->branches->assignedBranchId()];
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        $username = mb_strtolower(trim((string) ($data['username'] ?? '')));

        return $import->mode !== ImportMode::CreateOnly && $id !== ''
            ? 'id:'.hash('sha256', $id)
            : ($username === '' ? null : 'username:'.hash('sha256', $username));
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        foreach (['id', 'name', 'username', 'email', 'phone_country_iso_code', 'phone', 'gender', 'role', 'branch_id', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        $data['id'] ??= null;
        $data['phone_country_iso_code'] = strtoupper((string) ($data['phone_country_iso_code'] ?? ''));
        if (isset($data['gender'])) {
            $data['gender'] = mb_strtolower($data['gender']);
        }
        $data['is_active'] = match (mb_strtolower((string) ($data['is_active'] ?? ''))) {
            'true', 'yes', '1' => 1,
            'false', 'no', '0' => 0,
            default => $data['is_active'] ?? null,
        };
        $data['branch_id'] = $this->branches->assignedBranchId() ?? ($data['branch_id'] ?? null);
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }

        $roleRule = Rule::exists('roles', 'id')->whereNot('name', DefaultRole::Customer->value);
        if ($this->branches->assignedBranchId() !== null) {
            $roleRule->whereIn('name', DefaultRole::getBranchAvailableRoles());
        }
        $errors = Validator::make($data, [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', new Username],
            'email' => 'required|email:rfc|max:50',
            'phone_country_iso_code' => 'required|string|max:3',
            'phone' => 'bail|required|phone:phone_country_iso_code',
            'gender' => ['required', Rule::enum(GenderType::class)],
            'role' => ['required', 'integer', $roleRule],
            'branch_id' => 'nullable|integer|exists:branches,id,deleted_at,NULL',
            'is_active' => 'required|boolean',
        ])->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }

        $data['phone'] = phone($data['phone'], $data['phone_country_iso_code'])->formatE164();
        $query = User::query()->withoutGlobalActive()->role(roles: DefaultRole::Customer, without: true);
        $existing = $data['id'] !== null
            ? (clone $query)->whereKey($data['id'])->first()
            : (clone $query)->where('username', $data['username'])->first();
        if ($data['id'] !== null && ! $existing) {
            $errors['id'] = [__('user::users.import.missing_id')];
        }
        if ($existing?->isMainUser()) {
            $errors[$data['id'] !== null ? 'id' : 'username'] = [__('user::users.import.main_user')];
        }

        $role = Role::query()->findOrFail($data['role']);
        $branchId = $data['branch_id']
            ?? ($import->mode === ImportMode::ReplaceExisting ? null : $existing?->branch_id);
        if (in_array($role->name, DefaultRole::getBranchAvailableRoles(), true) && $branchId === null) {
            $errors['branch_id'] = [__('user::users.import.branch_required')];
        }
        if ($data['branch_id'] !== null) {
            $this->branches->resolve((int) $data['branch_id']);
        }

        $errors += Validator::make($data, [
            'username' => Rule::unique('users', 'username')->ignore($existing?->id),
            'email' => Rule::unique('users', 'email')->ignore($existing?->id),
            'phone' => Rule::unique('users', 'phone')->ignore($existing?->id),
        ])->errors()->toArray();
        if ($existing?->two_factor_confirmed_at !== null) {
            if ($existing->two_factor_method === TwoFactorMethod::Email && $existing->email !== $data['email']) {
                $errors['email'] = [__('user::auth.two_factor_email_change_protected')];
            }
            if ($existing->two_factor_method === TwoFactorMethod::Sms
                && ($existing->getRawOriginal('phone') !== $data['phone']
                    || $existing->phone_country_iso_code !== $data['phone_country_iso_code'])) {
                $errors['phone'] = [__('user::auth.two_factor_phone_change_protected')];
            }
        }

        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $errors[$data['id'] !== null ? 'id' : 'username'] = [$modeError];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $values = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone_country_iso_code' => $data['phone_country_iso_code'],
            'phone' => $data['phone'],
            'gender' => $data['gender'],
            'role' => (int) $data['role'],
            'is_active' => (bool) $data['is_active'],
        ];
        if ($targetId === null) {
            $this->users->store($values + [
                'branch_id' => $data['branch_id'],
                'password' => Str::random(64),
            ]);
        } else {
            if ($data['branch_id'] !== null || $import->mode === ImportMode::ReplaceExisting) {
                $values['branch_id'] = $data['branch_id'];
            }
            $this->users->update($targetId, $values);
        }
    }

    /** {@inheritDoc} */
    public function validateDependencies(DataImport $import, int $afterId = 0): ?int
    {
        return null;
    }

    /** {@inheritDoc} */
    public function afterImport(DataImport $import, int $afterId = 0): ?int
    {
        return null;
    }

    /** @return array{ImportOperation, ?string} */
    private function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('user::users.import.exists')],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('user::users.import.missing')]
            : [ImportOperation::Create, null];
    }
}
