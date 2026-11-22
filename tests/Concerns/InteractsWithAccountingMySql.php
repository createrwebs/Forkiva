<?php

namespace Tests\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Provide MySQL schema gates and shared MySQL/PostgreSQL concurrency safeguards. */
trait InteractsWithAccountingMySql
{
    /**
     * Require a disposable database; concurrency gates also support PostgreSQL.
     */
    protected function requireAccountingMySql(bool $requirePcntl = false): void
    {
        if (DB::getDriverName() !== 'mysql' && ! ($requirePcntl && DB::getDriverName() === 'pgsql')) {
            $this->markTestSkipped('A real supported database connection is required.');
        }
        if (! str_ends_with((string) DB::getDatabaseName(), '_test')) {
            $this->fail('The Accounting MySQL database name must end in _test.');
        }
        if ($requirePcntl && ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for concurrent workers.');
        }
    }

    /**
     * Build an isolated schema in the same cross-module dependency order as the established MySQL gates.
     */
    protected function rebuildAccountingMySqlSchema(): void
    {
        $this->requireAccountingMySql();
        $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);
        Schema::disableForeignKeyConstraints();

        try {
            $this->artisan('migrate:install')->assertExitCode(0);
            $paths = collect([
                'Branch', 'Core', 'User', 'Setting', 'Currency', 'Tax', 'Category', 'Option', 'Menu',
                'Product', 'SeatingPlan', 'Pos', 'Order', 'Inventory', 'Payment', 'Discount', 'Invoice',
                'GiftCard', 'Reservation', 'Approval', 'Export', 'Accounting',
            ])->map(fn (string $module): string => module_path($module, 'database/migrations'))->all();
            $branch = array_shift($paths);
            $this->artisan('migrate', ['--path' => [$branch], '--realpath' => true, '--force' => true])->assertExitCode(0);
            $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Execute two workers after one filesystem gate and return their scalar results.
     */
    protected function runAccountingMySqlWorkers(Closure $worker): Collection
    {
        $this->requireAccountingMySql(true);
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }
        $this->committedForConcurrency = true;
        $token = sys_get_temp_dir().'/accounting-tax-concurrency-'.uniqid('', true);
        $gate = $token.'.go';
        $resultFiles = [$token.'.0', $token.'.1'];
        $children = [];
        DB::disconnect();

        foreach ($resultFiles as $index => $resultFile) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Unable to fork an Accounting tax-compliance worker.');
            }
            if ($pid === 0) {
                DB::purge();
                DB::reconnect();
                while (! file_exists($gate)) {
                    usleep(1_000);
                }
                try {
                    file_put_contents($resultFile, (string) $worker($index));
                } catch (Throwable $exception) {
                    file_put_contents($resultFile, 'error:'.$exception->getMessage());
                }
                exit(0);
            }
            $children[] = $pid;
        }

        file_put_contents($gate, 'go');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
        DB::reconnect();
        $results = collect($resultFiles)->map(fn (string $file): string => trim((string) file_get_contents($file)));
        foreach ([$gate, ...$resultFiles] as $file) {
            @unlink($file);
        }

        return $results;
    }
}
