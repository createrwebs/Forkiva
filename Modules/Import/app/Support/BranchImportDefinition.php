<?php

namespace Modules\Import\Support;

use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;

abstract class BranchImportDefinition implements ImportDefinitionInterface
{
    public function __construct(protected BranchAccessServiceInterface $branches) {}

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return ['branch_id' => $this->branches->assignedBranchId()];
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
     * Build the branch selector field, restricted to the actor's assigned branch when applicable.
     *
     * @return array<string, mixed>
     */
    protected function branchField(): array
    {
        return [
            'key' => 'branch_id',
            'label' => __('branch::branches.table.branch'),
            'type' => 'integer',
            'required' => $this->branches->assignedBranchId() === null,
            'options' => Branch::query()->withoutGlobalActive()
                ->when($this->branches->assignedBranchId(), fn ($query, $id) => $query->whereKey($id))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])->all(),
        ];
    }

    /**
     * Prevent an import saved for one assigned branch from being resumed under another.
     */
    protected function authorizeBranch(?DataImport $import): void
    {
        if ($import && $this->branches->assignedBranchId() !== null) {
            abort_unless(($import->context['branch_id'] ?? null) === $this->branches->assignedBranchId(), 404);
        }
    }

    /**
     * Normalize imported values and enforce the actor's assigned branch.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    protected function normalize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }
        $data['branch_id'] = $this->branches->assignedBranchId() ?? ($data['branch_id'] ?? null);
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = match (mb_strtolower((string) $data['is_active'])) {
                'true', 'yes', '1' => 1,
                'false', 'no', '0' => 0,
                default => $data['is_active'],
            };
        }

        return $data;
    }

    /**
     * Resolve and authorize the branch referenced by the normalized import data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveBranch(array $data): void
    {
        $this->branches->resolve((int) $data['branch_id'], true);
    }

    /**
     * Build import field definitions for a default translation and its other locales.
     *
     * @return list<array{key: string, label: string, type: string, required: bool}>
     */
    protected function translatedFields(string $field, string $label, bool $required = true): array
    {
        $fields = [['key' => $field, 'label' => $label, 'type' => 'text', 'required' => $required]];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = ['key' => "{$field}_$locale", 'label' => "$label ($locale)", 'type' => 'text', 'required' => false];
        }

        return $fields;
    }

    /**
     * Return supported locales other than the configured default locale.
     *
     * @return list<string>
     */
    protected function otherLocales(): array
    {
        return array_values(
            array_filter(
                supportedLocaleKeys(), fn ($locale) => $locale !== (setting('default_locale') ?: 'en')
            )
        );
    }

    /**
     * Preserve unmapped locales on updates unless replacement was requested.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function translations(array $data, string $field, ?object $model, ImportMode $mode): array
    {
        $value = $model?->getTranslations($field) ?? [];
        $value[setting('default_locale') ?: 'en'] = $data[$field] ?? '';
        foreach ($this->otherLocales() as $locale) {
            $key = "{$field}_$locale";
            if (array_key_exists($key, $data) && ($data[$key] !== null || $mode === ImportMode::ReplaceExisting)) {
                $value[$locale] = $data[$key] ?? '';
            }
        }

        return $value;
    }

    /**
     * Choose the import operation and validation message for the record state and mode.
     *
     * @return array{ImportOperation, string|null}
     */
    protected function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('validation.unique', ['attribute' => 'id'])],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('validation.exists', ['attribute' => 'id'])]
            : [ImportOperation::Create, null];
    }
}
