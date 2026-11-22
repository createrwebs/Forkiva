<?php

namespace Modules\Inventory\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockTransferMySqlIntegrityTest extends TestCase
{
    use RefreshDatabase {
        migrateDatabases as migrateConventionalDatabase;
    }

    public function test_mysql_constraints_and_transition_trigger_are_installed(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Requires MySQL or PostgreSQL.');
        }

        $schema = DB::getDriverName() === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $constraints = collect(DB::select(
            "SELECT CONSTRAINT_NAME AS name
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = {$schema}
              AND TABLE_NAME IN ('stock_transfers', 'stock_transfer_items', 'stock_transfer_receipt_items')",
        ))->pluck('name');

        $this->assertContains('stock_transfers_branch_check', $constraints);
        $this->assertContains('stock_transfers_status_check', $constraints);
        $this->assertContains('stock_transfer_items_quantity_check', $constraints);
        $this->assertContains('stock_transfer_receipt_items_value_check', $constraints);

        $trigger = DB::selectOne(
            "SELECT TRIGGER_NAME
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = {$schema}
              AND TRIGGER_NAME = 'stock_transfers_terminal_status_guard'",
        );
        $this->assertNotNull($trigger);
    }

    /**
     * Build MySQL tables in module dependency order.
     */
    protected function migrateDatabases(): void
    {
        if (DB::getDriverName() !== 'sqlite' && ! str_ends_with(DB::getDatabaseName(), '_test')) {
            $this->fail('Stock-transfer integrity tests require a disposable database ending in _test.');
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->migrateConventionalDatabase();

            return;
        }

        $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);
        Schema::disableForeignKeyConstraints();

        try {
            $this->artisan('migrate:install')->assertExitCode(0);
            $modulePaths = collect([
                'Branch',
                'User',
                'Tax',
                'Category',
                'Option',
                'Menu',
                'Product',
                'Pos',
                'Order',
                'Inventory',
                'Payment',
                'Invoice',
                'Accounting',
            ])->map(fn (string $module) => module_path($module, 'database/migrations'))->all();

            $this->artisan('migrate', [
                '--path' => $modulePaths,
                '--realpath' => true,
                '--force' => true,
            ])->assertExitCode(0);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
