<?php

namespace Modules\Category\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Category\Services\CategoryTreeUpdater\CategoryTreeUpdaterServiceInterface;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Import\Models\DataImport;
use Modules\Import\Models\ImportRow;
use Modules\Menu\Models\Menu;

class CategoryImportDefinition implements ImportDefinitionInterface
{
    private array $stagedParents = [];

    private array $existingParents = [];

    private ?string $lookupScope = null;

    public function __construct(private readonly CategoryTreeUpdaterServiceInterface $treeUpdater) {}

    /**
     * Parent lookups are local to one bounded job, never shared between actors or revisions.
     */
    private function prepareLookups(DataImport $import): void
    {
        $scope = implode(':', [$import->id, $import->revision, $import->context['menu_id'], $import->status?->value]);
        if ($this->lookupScope !== $scope) {
            $this->stagedParents = [];
            $this->existingParents = [];
            $this->lookupScope = $scope;
        }
    }

    private function stagedParent(DataImport $import, string $slug): ?ImportRow
    {
        if (! array_key_exists($slug, $this->stagedParents)) {
            $this->stagedParents[$slug] = $import->rows()
                ->where('record_key', hash('sha256', $slug))
                ->orderBy('id')
                ->first(['id', 'data', 'status']);
        }

        return $this->stagedParents[$slug];
    }

    private function existingParent(int $menuId, string $slug): ?Category
    {
        if (! array_key_exists($slug, $this->existingParents)) {
            $this->existingParents[$slug] = Category::query()
                ->withoutGlobalActive()
                ->where('menu_id', $menuId)
                ->where('slug', $slug)
                ->first(['id', 'slug', 'parent_id']);
        }

        return $this->existingParents[$slug];
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            [
                'key' => 'slug',
                'label' => __('category::categories.table.slug'),
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'name',
                'label' => __('category::categories.table.name'),
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'parent_slug',
                'label' => __('category::categories.import.parent_slug'),
                'type' => 'text',
                'required' => false,
            ],
            [
                'key' => 'is_active',
                'label' => __('admin::admin.table.activation'),
                'type' => 'boolean',
                'required' => true,
            ],
        ];

        foreach ($this->otherLocales() as $locale) {
            $fields[] = [
                'key' => "name_$locale",
                'label' => __('category::categories.table.name')." ($locale)",
                'type' => 'text',
                'required' => false,
            ];
        }

        return $fields;
    }

    private function otherLocales(): array
    {
        return array_values(array_filter(
            supportedLocaleKeys(),
            fn ($locale) => $locale !== (setting('default_locale') ?: 'en')
        ));
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        $menuId = (int) ($input['menu_id'] ?? 0);
        abort_unless($menuId > 0, 422, __('import::imports.messages.menu_required'));

        $context = ['menu_id' => $menuId];
        $this->authorize(new DataImport(['context' => $context]));

        return $context;
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        $user = auth()->user();
        abort_unless($user?->can('admin.categories.import'), 403);

        if ($import) {
            $menuId = (int) ($import->context['menu_id'] ?? 0);
            Menu::query()
                ->withoutGlobalActive()
                ->findOrFail($menuId);
        }
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $slug = Str::slug(trim((string) ($data['slug'] ?? '')));

        return $slug === '' ? null : hash('sha256', $slug);
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        $this->prepareLookups($import);
        $data['slug'] = Str::slug(trim((string) ($data['slug'] ?? '')));
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['parent_slug'] = Str::slug(trim((string) ($data['parent_slug'] ?? ''))) ?: null;
        foreach ($this->otherLocales() as $locale) {
            $key = "name_$locale";
            if (array_key_exists($key, $data)) {
                $data[$key] = trim((string) $data[$key]) ?: null;
            }
        }
        $data['is_active'] = match (mb_strtolower(trim((string) ($data['is_active'] ?? '')))) {
            'true', 'yes', '1' => 1,
            'false', 'no', '0' => 0,
            default => $data['is_active'] ?? null,
        };

        $rules = [
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => 'required|string|max:255',
            'parent_slug' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
        }
        $errors = Validator::make($data, $rules)
            ->errors()
            ->toArray();

        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }

        $menuId = (int) $import->context['menu_id'];
        $existing = Category::query()
            ->withoutGlobalActive()
            ->where('menu_id', $menuId)
            ->where('slug', $data['slug'])
            ->first();

        if ($data['parent_slug'] === $data['slug']) {
            $errors['parent_slug'] = [__('category::categories.import.self_parent')];
        } elseif ($data['parent_slug'] && ! $this->parentExists($import, $menuId, $data['parent_slug'])) {
            $errors['parent_slug'] = [__('category::categories.import.missing_parent')];
        } elseif ($data['parent_slug'] && $this->hasCycle($import, $menuId, $data['slug'], $data['parent_slug'])) {
            $errors['parent_slug'] = [__('category::categories.import.cycle')];
        }

        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $errors['slug'] = [$modeError];
        }
        if ($operation === ImportOperation::Create && ! auth()->user()->can('admin.categories.create')) {
            $errors['slug'] = [__('category::categories.import.cannot_create')];
        }
        if ($operation === ImportOperation::Update && ! auth()->user()->can('admin.categories.edit')) {
            $errors['slug'] = [__('category::categories.import.cannot_update')];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /**
     * Accept a parent slug when it already belongs to the menu or is staged in this import.
     */
    private function parentExists(DataImport $import, int $menuId, string $slug): bool
    {
        return $this->stagedParent($import, $slug) !== null
            || $this->existingParent($menuId, $slug) !== null;
    }

    /**
     * Detect cycles across staged rows and existing menu categories, with a bounded traversal.
     */
    private function hasCycle(DataImport $import, int $menuId, string $slug, string $parentSlug): bool
    {
        $seen = [$slug => true];
        $current = $parentSlug;

        for ($depth = 0; $depth < 100 && $current; $depth++) {
            if (isset($seen[$current])) {
                return true;
            }
            $seen[$current] = true;
            $staged = $this->stagedParent($import, $current);
            if ($staged) {
                $current = Str::slug((string) ($staged->data['parent_slug'] ?? '')) ?: null;

                continue;
            }
            $category = $this->existingParent($menuId, $current);
            $current = $category?->parent?->slug;
        }

        return $current !== null;
    }

    /**
     * Resolve the row action while preserving the selected conflict behavior.
     *
     * @return array{ImportOperation, ?string}
     */
    private function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('category::categories.import.exists')],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('category::categories.import.missing')]
            : [ImportOperation::Create, null];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $menuId = (int) $import->context['menu_id'];
        Menu::query()
            ->withoutGlobalActive()
            ->findOrFail($menuId);

        $category = new Category;
        if ($targetId !== null) {
            $category = Category::query()
                ->withoutGlobalActive()
                ->where('menu_id', $menuId)
                ->findOrFail($targetId);
        }

        if (! $category->exists) {
            $category->menu_id = $menuId;
        }
        $category->slug = $data['slug'];
        $category->setTranslation('name', setting('default_locale') ?: 'en', $data['name']);
        foreach ($this->otherLocales() as $locale) {
            $key = "name_$locale";
            if (array_key_exists($key, $data) &&
                ($import->mode === ImportMode::ReplaceExisting || $data[$key] !== null)) {
                $category->setTranslation('name', $locale, $data[$key] ?? '');
            }
        }
        $category->is_active = (bool) $data['is_active'];
        $category->save();
    }

    /** {@inheritDoc} */
    public function validateDependencies(DataImport $import, int $afterId = 0): ?int
    {
        $this->prepareLookups($import);
        // Row validation may have changed a cached parent's status in this same job.
        $this->stagedParents = [];
        $menuId = (int) $import->context['menu_id'];
        $rows = $import->rows()
            ->where('id', '>', $afterId)
            ->where('status', ImportRowStatus::Valid->value)
            ->orderBy('id')
            ->limit(max(1, (int) config('import.validation_batch_size', 200)))
            ->get();
        $invalid = 0;
        $new = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! $this->hasInvalidParentDependency($import, $menuId, $row->data['parent_slug'] ?? null)) {
                continue;
            }
            $errors = $row->errors ?? [];
            $errors['parent_slug'] = [__('category::categories.import.invalid_parent')];
            $operation = $row->operation;
            $row->update([
                'status' => ImportRowStatus::Invalid,
                'operation' => ImportOperation::Invalid,
                'errors' => $errors,
            ]);
            $invalid++;
            if ($operation === ImportOperation::Create) {
                $new++;
            } elseif ($operation === ImportOperation::Update) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        if ($invalid > 0) {
            $import->update([
                'valid_rows' => $import->valid_rows - $invalid,
                'invalid_rows' => $import->invalid_rows + $invalid,
                'new_rows' => $import->new_rows - $new,
                'update_rows' => $import->update_rows - $updated,
                'skipped_rows' => $import->skipped_rows - $skipped,
            ]);
        }

        $lastId = $rows->last()?->id;

        $hasMore = $lastId && $import->rows()
            ->where('id', '>', $lastId)
            ->where('status', ImportRowStatus::Valid->value)
            ->exists();

        return $hasMore ? $lastId : null;
    }

    /**
     * Detect a missing, invalid, or cyclic parent chain before processing the dependent row.
     */
    private function hasInvalidParentDependency(DataImport $import, int $menuId, ?string $parentSlug): bool
    {
        $seen = [];
        while ($parentSlug !== null) {
            if (isset($seen[$parentSlug])) {
                return true;
            }
            $seen[$parentSlug] = true;
            if ($this->existingParent($menuId, $parentSlug) !== null) {
                return false;
            }

            $parent = $this->stagedParent($import, $parentSlug);
            if (! $parent || $parent->status === ImportRowStatus::Invalid) {
                return true;
            }
            $parentSlug = Str::slug((string) ($parent->data['parent_slug'] ?? '')) ?: null;
        }

        return false;
    }

    /** {@inheritDoc} */
    public function afterImport(DataImport $import, int $afterId = 0): ?int
    {
        $menuId = (int) $import->context['menu_id'];
        $rows = ImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ImportRowStatus::Processed->value)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(max(1, (int) config('import.finalization_batch_size', 100)))
            ->get();
        $slugs = $rows->flatMap(fn ($row) => [$row->data['slug'], $row->data['parent_slug'] ?? null])
            ->filter()->unique()->values();
        $categories = Category::query()->withoutGlobalActive()
            ->where('menu_id', $menuId)->whereIn('slug', $slugs)
            ->get(['id', 'slug'])->keyBy('slug');
        $parentIds = [];

        foreach ($rows as $row) {
            $data = $row->data;
            $category = $categories->get($data['slug']);
            abort_unless($category, 404);
            $parentSlug = $data['parent_slug'] ?? null;
            $replaceMappedParent = $import->mode === ImportMode::ReplaceExisting
                && array_key_exists('parent_slug', $import->mapping);
            if (! $parentSlug && ! $replaceMappedParent) {
                continue;
            }
            $parent = $parentSlug ? $categories->get($parentSlug) : null;
            abort_if($parentSlug && ! $parent, 404);
            $parentIds[$category->id] = $parent?->id;
        }

        // The updater repairs nested-set indexes and invalidates menu category caches.
        $this->treeUpdater->updateParents($menuId, $parentIds);

        $lastId = $rows->last()?->id;

        $hasMore = $lastId && ImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ImportRowStatus::Processed->value)
            ->where('id', '>', $lastId)
            ->exists();

        return $hasMore ? $lastId : null;
    }
}
