<?php

namespace Modules\Core\Tests\Feature;

use App\Cache\AlternativeFileCacheStoreWithLocks;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class AlternativeFileCacheLockTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cachePath = storage_path('framework/testing/alternative-file-cache-lock');

        (new Filesystem)->deleteDirectory($this->cachePath);

        config()->set('cache.stores.alternative_file_lock_testing', [
            'driver' => 'file',
            'path' => $this->cachePath,
            'lock_path' => $this->cachePath.'/locks',
        ]);

        app(CacheManager::class)->forgetDriver('alternative_file_lock_testing');
    }

    protected function tearDown(): void
    {
        app(CacheManager::class)->forgetDriver('alternative_file_lock_testing');

        (new Filesystem)->deleteDirectory($this->cachePath);

        parent::tearDown();
    }

    public function test_file_cache_uses_atomic_laravel_locks(): void
    {
        $cache = app(CacheManager::class)->store('alternative_file_lock_testing');

        $this->assertInstanceOf(AlternativeFileCacheStoreWithLocks::class, $cache->getStore());

        $cache->tags('compatibility')->put('tagged-value', 'preserved', 10);

        $this->assertSame('preserved', $cache->get('tagged-value'));

        $firstLock = $cache->lock('order-payment', 10, 'first-owner');
        $secondLock = $cache->lock('order-payment', 10, 'second-owner');

        $this->assertTrue($firstLock->get());
        $this->assertFalse($secondLock->get());
        $this->assertTrue($firstLock->release());
        $this->assertTrue($secondLock->get());
        $this->assertTrue($secondLock->release());

        $cache->tags('compatibility')->flush();

        $this->assertNull($cache->get('tagged-value'));
    }

    public function test_array_cache_retains_laravel_expiration_and_tag_support(): void
    {
        $cache = app(CacheManager::class)->store('array');

        $this->assertInstanceOf(ArrayStore::class, $cache->getStore());

        $cache->tags('expiration')->put('temporary', 'value', 10);

        $this->assertSame('value', $cache->tags('expiration')->get('temporary'));

        $this->travel(11)->seconds();

        $this->assertNull($cache->tags('expiration')->get('temporary'));
    }
}
