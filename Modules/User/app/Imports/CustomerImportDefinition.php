<?php

namespace Modules\User\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ImportDate;
use Modules\Support\Country;
use Modules\User\Enums\CustomerType;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\User;
use Modules\User\Rules\Username;
use Modules\User\Services\Customer\CustomerServiceInterface;
use Throwable;

class CustomerImportDefinition implements ImportDefinitionInterface
{
    public function __construct(private CustomerServiceInterface $customers) {}

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'name', 'label' => __('user::customers.table.name'), 'type' => 'text', 'required' => true],
            ['key' => 'phone_country_iso_code', 'label' => __('user::customers.table.phone_country_iso_code'), 'type' => 'text', 'required' => true,
                'options' => array_map(fn (array $country) => ['value' => $country['id'], 'label' => $country['name']], Country::toList())],
            ['key' => 'phone', 'label' => __('user::customers.table.phone'), 'type' => 'text', 'required' => true],
            ['key' => 'customer_type', 'label' => __('user::customers.table.customer_type'), 'type' => 'text', 'required' => true,
                'options' => array_map(fn (CustomerType $type) => ['value' => $type->value, 'label' => $type->trans()], CustomerType::cases())],
            ['key' => 'is_active', 'label' => __('admin::admin.table.activation'), 'type' => 'boolean', 'required' => true],
            ['key' => 'username', 'label' => __('user::customers.table.username'), 'type' => 'text', 'required' => false],
            ['key' => 'email', 'label' => __('user::customers.table.email'), 'type' => 'text', 'required' => false],
            ['key' => 'gender', 'label' => __('user::customers.table.gender'), 'type' => 'text', 'required' => false,
                'options' => array_map(fn (GenderType $gender) => ['value' => $gender->value, 'label' => $gender->trans()], GenderType::cases())],
            ['key' => 'birthdate', 'label' => __('user::customers.table.birthdate'), 'type' => 'date', 'required' => false],
            ['key' => 'note', 'label' => __('user::customers.table.note'), 'type' => 'text', 'required' => false],
            ['key' => 'registration_number', 'label' => __('user::customers.table.registration_number'), 'type' => 'text', 'required' => false],
            ['key' => 'vat_tin', 'label' => __('user::customers.table.vat_tin'), 'type' => 'text', 'required' => false],
        ];
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.customers.import'), 403);
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $country = strtoupper(trim((string) ($data['phone_country_iso_code'] ?? '')));
        $number = trim((string) ($data['phone'] ?? ''));
        if ($country === '' || $number === '') {
            return null;
        }

        try {
            $number = phone($number, $country)->formatE164();
        } catch (Throwable) {
            $number = "$country:$number";
        }

        return hash('sha256', $number);
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        foreach (array_column($this->fields(), 'key') as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        $data['phone_country_iso_code'] = strtoupper((string) ($data['phone_country_iso_code'] ?? ''));
        foreach (['customer_type', 'gender'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = mb_strtolower($data[$field]);
            }
        }
        $data['birthdate'] = ImportDate::normalizeSerial($data['birthdate'] ?? null);
        $data['is_active'] = match (mb_strtolower((string) ($data['is_active'] ?? ''))) {
            'true', 'yes', '1' => 1,
            'false', 'no', '0' => 0,
            default => $data['is_active'] ?? null,
        };
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }
        $errors = Validator::make($data, [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'phone_country_iso_code' => 'required|string|max:3',
            'phone' => 'bail|required|phone:phone_country_iso_code',
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'is_active' => 'required|boolean',
            'username' => ['nullable', 'string', 'max:255', new Username],
            'email' => 'nullable|email:rfc|max:50',
            'gender' => ['nullable', Rule::enum(GenderType::class)],
            'birthdate' => 'nullable|date|before_or_equal:today',
            'note' => 'nullable|string|max:2000',
            'registration_number' => 'nullable|string|max:120',
            'vat_tin' => 'nullable|string|max:120',
        ])->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }

        $data['phone'] = phone($data['phone'], $data['phone_country_iso_code'])->formatE164();
        $query = User::query()->withoutGlobalActive()->role(DefaultRole::Customer);
        $existing = ! empty($data['id'])
            ? (clone $query)->whereKey($data['id'])->first()
            : (clone $query)->where('phone', $data['phone'])->first();
        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('user::customers.import.missing_id')];
        }

        $unique = Validator::make($data, [
            'phone' => Rule::unique('users', 'phone')->ignore($existing?->id),
            'username' => ['nullable', Rule::unique('users', 'username')->ignore($existing?->id)],
            'email' => ['nullable', Rule::unique('users', 'email')->ignore($existing?->id)],
        ])->errors()->toArray();
        $errors += $unique;

        if ($existing?->two_factor_confirmed_at !== null) {
            if ($existing->two_factor_method === TwoFactorMethod::Sms
                && ($existing->getRawOriginal('phone') !== $data['phone']
                    || $existing->phone_country_iso_code !== $data['phone_country_iso_code'])) {
                $errors['phone'] = [__('user::auth.two_factor_phone_change_protected')];
            }
            if ($existing->two_factor_method === TwoFactorMethod::Email
                && $this->replacesOptional($data, $import, 'email', $existing->email)) {
                $errors['email'] = [__('user::auth.two_factor_email_change_protected')];
            }
        }

        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $errors[! empty($data['id']) ? 'id' : 'phone'] = [$modeError];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $values = [
            'name' => $data['name'],
            'phone_country_iso_code' => $data['phone_country_iso_code'],
            'phone' => $data['phone'],
            'customer_type' => $data['customer_type'],
            'is_active' => (bool) $data['is_active'],
        ];
        foreach (['username', 'email', 'gender', 'birthdate', 'note', 'registration_number', 'vat_tin'] as $field) {
            if (array_key_exists($field, $data)
                && ($targetId === null || $data[$field] !== null || $import->mode === ImportMode::ReplaceExisting)) {
                $values[$field] = $data[$field];
            }
        }
        if ($targetId === null) {
            $this->customers->store($values);
        } else {
            $this->customers->update($targetId, $values);
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

    /**
     * Match optional contact changes to the account's enrolled email factor.
     */
    private function replacesOptional(array $data, DataImport $import, string $field, ?string $existing): bool
    {
        return array_key_exists($field, $data)
            && ($data[$field] !== null || $import->mode === ImportMode::ReplaceExisting)
            && $data[$field] !== $existing;
    }

    /** @return array{ImportOperation, ?string} */
    private function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('user::customers.import.exists')],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('user::customers.import.missing')]
            : [ImportOperation::Create, null];
    }
}
