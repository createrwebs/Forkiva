<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Database\DatabaseExpression;
use Modules\Installer\Services\DatabaseProbe\InstallerDatabaseProbe;
use Tests\TestCase;

class PostgresCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Run with a disposable PostgreSQL database.');
        }
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && DB::getDriverName() === 'pgsql' && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_probe_cleans_up_every_object_and_preserves_transaction_depth(): void
    {
        $before = Schema::getTables();
        $depth = DB::transactionLevel();
        (new InstallerDatabaseProbe)->run(DB::connection());
        $this->assertSame($depth, DB::transactionLevel());
        $this->assertSame($before, Schema::getTables());
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS total FROM pg_proc WHERE proname LIKE 'installer_probe_%'")->total);
    }

    public function test_legacy_enum_changes_and_generated_columns_preserve_constraints(): void
    {
        Schema::create('postgres_compatibility_test', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['draft'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->bigInteger('zone_id');
            $table->bigInteger('active_zone_id')->nullable()->storedAs('case when `is_active` = 1 then `zone_id` else null end');
            $table->unique('active_zone_id');
        });
        DB::table('postgres_compatibility_test')->insert(['zone_id' => 12]);
        Schema::table('postgres_compatibility_test', fn (Blueprint $table) => $table->enum('status', ['draft', 'posted'])->default('draft')->change());
        DB::table('postgres_compatibility_test')->update(['status' => 'posted']);
        $this->assertSame(12, DB::table('postgres_compatibility_test')->value('active_zone_id'));
        $this->assertSame('posted', DB::table('postgres_compatibility_test')->value('status'));
        DB::table('postgres_compatibility_test')->update(['is_active' => false]);
        $this->assertNull(DB::table('postgres_compatibility_test')->value('active_zone_id'));
        $constraints = DB::selectOne("SELECT count(*) AS total FROM pg_constraint WHERE conrelid = 'postgres_compatibility_test'::regclass AND contype = 'c'");
        $this->assertSame(1, (int) $constraints->total);
    }

    public function test_json_conversion_and_scalar_aggregations(): void
    {
        Schema::create('postgres_compatibility_test', fn (Blueprint $table) => $table->text('payload'));
        DB::table('postgres_compatibility_test')->insert(['payload' => '{"name":"Example","paid":true,"amount":12.5}']);
        Schema::table('postgres_compatibility_test', fn (Blueprint $table) => $table->json('payload')->change());
        $row = DB::table('postgres_compatibility_test')->selectRaw(
            DatabaseExpression::jsonText('payload', 'name').' AS name, '.
            DatabaseExpression::jsonNumber('payload', 'amount').' AS amount, '.
            DatabaseExpression::jsonFlag('payload', 'paid').' AS paid'
        )->first();
        $this->assertSame('Example', $row->name);
        $this->assertEquals(12.5, $row->amount);
        $this->assertSame(1, $row->paid);
        $this->assertSame('jsonb', Schema::getColumnType('postgres_compatibility_test', 'payload'));
    }
}
