<?php

namespace Modules\Installer\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Tests\TestCase;

class InstallerFileCacheTest extends TestCase
{
    public function test_launch_can_initialize_file_cache_while_default_cache_is_array(): void
    {
        $path = sys_get_temp_dir().'/installer-file-cache-'.Str::uuid();
        File::makeDirectory($path, 0700, true);
        config(['cache.default' => 'array', 'cache.stores.file.path' => $path, 'cache.stores.file.lock_path' => $path]);
        Cache::forgetDriver('file');

        try {
            app(InstallerConfigurationInterface::class)->verifyCache('file');
            $this->assertTrue(Cache::store('file')->supportsTags());
        } finally {
            Cache::forgetDriver('file');
            File::deleteDirectory($path);
        }
    }
}
