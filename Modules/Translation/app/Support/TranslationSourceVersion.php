<?php

namespace Modules\Translation\Support;

use App\Forkiva;

class TranslationSourceVersion
{
    /**
     * Keep development caches stable while still detecting translation file edits.
     */
    public static function get(): string
    {
        if (! app()->isLocal()) {
            return Forkiva::$version;
        }

        $files = [];
        foreach (resolve('translation.loader')->paths() as $path) {
            foreach (supportedLocaleKeys() as $locale) {
                foreach (glob("$path/$locale/*.php") ?: [] as $file) {
                    $files[$file] = sha1_file($file);
                }
            }
        }
        ksort($files);

        return sha1(json_encode([Forkiva::$version, $files]));
    }
}
