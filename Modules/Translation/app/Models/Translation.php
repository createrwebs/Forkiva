<?php

namespace Modules\Translation\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Support\PublicTranslationExceptPatterns;
use Modules\Translation\Support\TranslationSourceVersion;
use Spatie\Translatable\HasTranslations;
use Str;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value The translated value for the active locale.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Translation extends Model
{
    use HasActivityLog, HasTagsCache, HasTranslations;

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['value'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve all translations.
     */
    public static function retrieve(): array
    {
        $translations = [];

        foreach (self::allMergedTranslations() as $key => $translation) {
            $translations[] = [
                'key' => $key,
                'translations' => $translation,
            ];
        }

        return $translations;
    }

    /**
     * Get merged file and database translations keyed by translation key.
     */
    public static function allMergedTranslations(): array
    {
        return Cache::tags('translations')
            ->rememberForever(md5('translations.all'), fn () => self::getAllTranslations());
    }

    /**
     * Get all translations
     */
    protected static function getAllTranslations(): array
    {
        return array_replace_recursive(static::getFileTranslations(), static::getDatabaseTranslations());
    }

    /**
     * Get file translations.
     */
    public static function getFileTranslations(): array
    {
        $translations = [];

        foreach (resolve('translation.loader')->paths() as $hint => $path) {
            foreach (supportedLocaleKeys() as $locale) {
                foreach (glob("{$path}/{$locale}/*.php") as $file) {
                    foreach (Arr::dot(require $file) as $key => $value) {
                        if ($hint === 1) {
                            continue;
                        }
                        $group = str_replace('.php', '', basename($file));
                        $translations[($hint === 0 ? '' : "{$hint}::")."{$group}.{$key}"][$locale] = $value;
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Get database translations.
     */
    public static function getDatabaseTranslations(): array
    {
        $translations = [];

        foreach (static::all() as $translation) {
            foreach ($translation->getTranslations('value') as $locale => $value) {
                $translations[$translation->key][$locale] = $value;
            }
        }

        return $translations;
    }

    /**
     * Retrieve all translations.
     *
     * @throws JsonException
     */
    public static function appRetrieve(): array
    {
        $locales = supportedLocaleKeys();
        $fallbackLocale = setting('default_locale');

        $sourceVersion = TranslationSourceVersion::get();
        $source = null;
        $translations = [];

        // Keep individual database-cache writes below the size of the full multilingual bundle.
        foreach ($locales as $locale) {
            $encoded = Cache::tags('translations')->rememberForever(
                makeCacheKey([
                    'translations',
                    'public',
                    'payload-gzip-v1',
                    $sourceVersion,
                    implode(',', $locales),
                    $fallbackLocale,
                    $locale,
                ], false),
                function () use ($locale, $fallbackLocale, &$source): string {
                    $source ??= self::getAllTranslations();
                    $bundle = [];
                    $exceptPatterns = PublicTranslationExceptPatterns::$patterns;

                    foreach ($source as $key => $translation) {
                        $isExcluded = collect($exceptPatterns)
                            ->contains(fn ($pattern) => Str::is($pattern, $key));
                        if ($isExcluded) {
                            continue;
                        }

                        $bundle[$key] = $translation[$locale]
                            ?? ($translation[$fallbackLocale] ?? Arr::first($translation));
                    }

                    // Base64 keeps compressed bytes safe for text-based cache stores.
                    return base64_encode(gzencode(json_encode($bundle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
                }
            );
            $bundle = json_decode(gzdecode(base64_decode($encoded, true)), true, 512, JSON_THROW_ON_ERROR);
            if ($bundle !== []) {
                $translations[$locale] = $bundle;
            }
        }

        return $translations;
    }
}
