<?php

namespace Modules\Import\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportOwnershipMigrationTest extends TestCase
{
    public function test_initial_import_schema_keeps_history_when_its_creator_is_deleted(): void
    {
        $connection = 'import_ownership_schema_test';
        $originalConnection = DB::getDefaultConnection();
        config()->set("database.connections.$connection", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection($connection);

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            $migration = require __DIR__.'/../../database/migrations/2026_09_27_000000_create_imports_tables.php';
            $migration->up();
            DB::table('users')->insert(['id' => 42]);
            DB::table('imports')->insert([
                'id' => 7,
                'created_by' => 42,
                'resource_key' => 'menus',
                'locale' => 'en',
                'status' => 'completed',
                'original_name' => 'menus.csv',
                'disk' => 'local',
                'path' => 'imports/menus.csv',
                'headers' => '[]',
            ]);

            $this->assertFalse(Schema::hasColumn('imports', 'user_id'));
            $this->assertTrue(Schema::hasColumn('imports', 'created_by'));
            $this->assertSame(42, DB::table('imports')->where('id', 7)->value('created_by'));

            DB::table('users')->where('id', 42)->delete();
            $this->assertNull(DB::table('imports')->where('id', 7)->value('created_by'));
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge($connection);
        }
    }
}
