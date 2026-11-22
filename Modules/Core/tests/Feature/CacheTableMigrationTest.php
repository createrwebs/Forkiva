<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CacheTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_owns_the_database_cache_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('cache', [
            'key',
            'value',
            'expiration',
        ]));
        $this->assertTrue(Schema::hasColumns('cache_locks', [
            'key',
            'owner',
            'expiration',
        ]));
    }
}
