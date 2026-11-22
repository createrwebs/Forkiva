<?php

namespace Modules\Inventory\Imports;

use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;

abstract class InventoryImportDefinition implements ImportDefinitionInterface
{
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

    protected function otherLocales(): array
    {
        return array_values(array_filter(
            supportedLocaleKeys(),
            fn ($locale) => $locale !== (setting('default_locale') ?: 'en')
        ));
    }

    protected function normalized(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    protected function boolean(mixed $value): mixed
    {
        return match (mb_strtolower(trim((string) $value))) {
            'true', 'yes', '1' => 1,
            'false', 'no', '0' => 0,
            default => $value,
        };
    }

    /** @return array{ImportOperation, ?string} */
    protected function operation(ImportMode $mode, bool $exists, string $resource): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __("inventory::$resource.import.exists")],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __("inventory::$resource.import.missing")]
            : [ImportOperation::Create, null];
    }
}
