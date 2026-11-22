<?php

namespace Modules\Translation\Services\Translation;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Translation\Models\Translation;
use Modules\Translation\Support\TranslationSourceVersion;
use Str;

class TranslationService implements TranslationServiceInterface
{
    /** @implements */
    public function label(): string
    {
        return __('translation::translations.translation');
    }

    /** @implements */
    public function update(string $key, string $locale, string $value): bool
    {
        $translation = Translation::query()->firstOrNew(['key' => $key]);
        $saved = $translation
            ->setTranslation('value', $locale, $value)
            ->save();

        cache()->tags('translations')->flush();

        return $saved;
    }

    /** @implements */
    public function getEmployeeAppTranslations(): array
    {
        return $this->filterAppTranslations('hr::employee_app.*');
    }

    /**
     * Return application translations that match a single translation key pattern.
     */
    private function filterAppTranslations(string|array $pattern): array
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];

        return collect($this->getAppTranslations())
            ->map(function (array $localeTranslations) use ($patterns) {
                return collect($localeTranslations)
                    ->filter(fn ($value, string $key) => collect($patterns)->contains(fn (string $pattern) => Str::is($pattern, $key)))
                    ->toArray();
            })
            ->toArray();
    }

    /** @implements */
    public function getAppTranslations(): array
    {
        return Translation::appRetrieve();
    }

    /** @implements */
    public function getDriverAppTranslations(): array
    {
        return $this->filterAppTranslations([
            'delivery::driver_panel.*',
            'delivery::driver_app.*',
        ]);
    }

    /** @implements */
    public function getAppTranslationsVersion(): string
    {
        $snapshot = Translation::query()
            ->selectRaw('COUNT(*) as total_translations, MAX(updated_at) as latest_updated_at')
            ->first();

        return sha1(json_encode([
            'total' => (int) ($snapshot?->total_translations ?? 0),
            'updated_at' => $snapshot?->latest_updated_at,
            'app_version' => TranslationSourceVersion::get(),
            'locale' => locale(),
            'supported_locales' => supportedLocaleKeys(),
            'fallback_locale' => setting('default_locale'),
        ]));
    }

    /** @implements */
    public function retrieve(): array
    {
        return Translation::retrieve();
    }

    /** @implements */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $records = $this->filterRecords(
            Translation::allMergedTranslations(),
            $filters['search'] ?? null,
            $filters['locale'] ?? null,
            $filters['status'] ?? 'all',
            $filters['prefix'] ?? null,
        );

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $items = array_slice($records, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            items: $items,
            total: count($records),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * Filter merged translations into paginated table records.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<int, array{key: string, translations: array<string, mixed>}>
     */
    private function filterRecords(
        array $translations,
        ?string $search,
        ?string $locale,
        string $status,
        ?string $prefix,
    ): array {
        $search = trim((string) $search);
        $prefix = trim((string) $prefix);
        $records = [];

        ksort($translations);

        foreach ($translations as $key => $values) {
            if ($prefix !== '' && ! str_starts_with($key, $prefix)) {
                continue;
            }

            if ($locale && $status !== 'all') {
                $missing = $this->isMissing($values[$locale] ?? null);
                if ($status === 'missing' && ! $missing) {
                    continue;
                }
                if ($status === 'filled' && $missing) {
                    continue;
                }
            }

            if ($search !== '' && ! $this->recordMatchesSearch($key, $values, $search)) {
                continue;
            }

            $records[] = [
                'key' => $key,
                'translations' => $values,
            ];
        }

        return $records;
    }

    /**
     * Determine whether a translation value is missing.
     */
    private function isMissing(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Check whether a translation record matches the search term.
     *
     * @param  array<string, mixed>  $values
     */
    private function recordMatchesSearch(string $key, array $values, string $search): bool
    {
        if (Str::contains(Str::lower($key), Str::lower($search))) {
            return true;
        }

        foreach ($values as $value) {
            if (is_string($value) && Str::contains(Str::lower($value), Str::lower($search))) {
                return true;
            }
        }

        return false;
    }
}
