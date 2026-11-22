<?php

namespace Modules\Translation\Support;

use App\Forkiva;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;
use Modules\Translation\Models\Translation;
use Throwable;

class TranslationLoader extends FileLoader
{
    /** @var array|string[] */
    protected array $defaultFiles = ['validation', 'auth', 'password', 'pagination'];

    /**
     * Get translation file paths.
     */
    public function paths(): array
    {
        return array_merge($this->paths, $this->hints);
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     */
    public function load($locale, $group, $namespace = null): array
    {
        if (! Forkiva::cacheEnabled()) {
            return $this->getTranslations($locale, $group, $namespace);
        }

        try {
            return Cache::tags('translations')
                ->rememberForever(
                    md5("translation_loader.$locale.$group.$namespace"),
                    fn () => $this->getTranslations($locale, $group, $namespace)
                );
        } catch (Throwable) {
            return $this->getTranslations($locale, $group, $namespace);
        }
    }

    /**
     * Get file and database translations.
     */
    private function getTranslations(string $locale, string $group, ?string $namespace = null): array
    {
        $databaseTranslations = $this->databaseTranslations($locale, $group, $namespace);

        return array_replace_recursive(
            $this->fileTranslations($locale, $group, $namespace),
            $this->breakDot($databaseTranslations)
        );
    }

    /**
     * Get database translations.
     */
    private function databaseTranslations(string $locale, string $group, ?string $namespace = null): array
    {
        if (! $this->translationsTableExists()) {
            return [];
        }

        if (in_array($group, $this->defaultFiles) && $namespace === '*') {
            $key = "$group";
        } else {
            $key = "$namespace::$group";
        }

        return Translation::where('key', 'LIKE', "$key.%")
            ->whereLocale('value', $locale)
            ->get()
            ->mapWithKeys(function ($translation) {
                return [$translation->key => $translation->value];
            })
            ->all();
    }

    /**
     * Determine whether database translations are available.
     */
    private function translationsTableExists(): bool
    {
        try {
            return Schema::hasTable((new Translation)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get file translations.
     */
    private function fileTranslations(string $locale, string $group, ?string $namespace = null): array
    {
        return parent::load($locale, $group, $namespace);
    }

    /**
     * Break flattened dot translations to an array.
     */
    private function breakDot(array $translations): array
    {
        $array = [];
        foreach ($translations as $key => $value) {
            if (in_array(explode('.', $key)[0], $this->defaultFiles)) {
                $key = str_replace(array_map(fn ($newKey) => "$newKey.", $this->defaultFiles), '', $key);
                if (count(explode('.', $key)) > 2) {
                    Arr::set($array, $key, $value);
                } else {
                    $array[$key] = $value;
                }
            } elseif (! str_contains($key, '*')) {
                Arr::set($array, $key, $value);
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
