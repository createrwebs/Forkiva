<?php

namespace Modules\Installer\Services\DatabaseProbe;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;

class InstallerDatabaseProbe implements InstallerDatabaseProbeInterface
{
    /** {@inheritDoc} */
    public function run(Connection $db): void
    {
        if ($db->getDriverName() === 'pgsql') {
            $this->runPostgres($db);

            return;
        }

        $table = 'installer_probe_'.bin2hex(random_bytes(8));
        try {
            $db->statement("CREATE TABLE `$table` (id BIGINT PRIMARY KEY, value INT NOT NULL CHECK (value >= 0), payload JSON NULL, search_text TEXT NULL, doubled BIGINT GENERATED ALWAYS AS (value * 2) STORED) ENGINE=InnoDB");
            $db->statement("ALTER TABLE `$table` ADD parent_id BIGINT NULL");
            $db->statement("CREATE INDEX probe_value ON `$table` (value)");
            $db->statement("CREATE INDEX probe_generated ON `$table` (doubled)");
            $db->statement("CREATE FULLTEXT INDEX probe_search ON `$table` (search_text)");
            $db->statement("ALTER TABLE `$table` ADD FOREIGN KEY (parent_id) REFERENCES `$table` (id)");
            $db->statement("CREATE TRIGGER `{$table}_guard` BEFORE UPDATE ON `$table` FOR EACH ROW BEGIN IF NEW.value = 9 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'installer_probe'; END IF; END");
            $db->table($table)->insert(['id' => 1, 'value' => 1, 'payload' => '{"ready":true}', 'search_text' => 'restaurant']);
            $db->table($table)->where('id', 1)->update(['value' => 2]);
            if ((int) $db->table($table)->value('doubled') !== 4 || $db->selectOne("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.ready')) AS ready FROM `$table`")->ready !== 'true') {
                throw new RuntimeException('Generated columns or JSON did not round trip.');
            }
            $this->expectRejection(fn () => $db->table($table)->insert(['id' => 2, 'value' => -1]), [3819, 4025]);
            $this->expectRejection(fn () => $db->table($table)->insert(['id' => 2, 'value' => 1, 'parent_id' => 999]), [1452]);
            $this->expectRejection(fn () => $db->table($table)->where('id', 1)->update(['value' => 9]), [1644]);
            $db->beginTransaction();
            try {
                $db->table($table)->insert(['id' => 2, 'value' => 3, 'parent_id' => 1]);
                $rows = $db->select("SELECT LAG(value) OVER (ORDER BY id) AS previous_value FROM `$table` ORDER BY id");
                if ((int) $rows[1]->previous_value !== 2) {
                    throw new RuntimeException('Window functions did not round trip.');
                }
            } finally {
                $db->rollBack();
            }
            if ($db->table($table)->where('id', 2)->exists()) {
                throw new RuntimeException('InnoDB transaction rollback failed.');
            }
            $db->table($table)->delete();
        } finally {
            // Dropping the table also removes its trigger and indexes.
            $db->statement("DROP TABLE IF EXISTS `$table`");
        }
    }

    /**
     * Verify PostgreSQL privileges and integrity inside a rolled-back transaction.
     */
    private function runPostgres(Connection $db): void
    {
        $table = 'installer_probe_'.bin2hex(random_bytes(8));
        $depth = $db->transactionLevel();
        $db->beginTransaction();
        try {
            $db->statement("CREATE TABLE {$table} (id BIGINT PRIMARY KEY, value INT NOT NULL CHECK (value >= 0), payload JSONB, search_text TEXT, doubled BIGINT GENERATED ALWAYS AS (value * 2) STORED)");
            $db->statement("ALTER TABLE {$table} ADD parent_id BIGINT REFERENCES {$table}(id)");
            $db->statement("CREATE INDEX {$table}_value ON {$table}(value)");
            $db->statement("CREATE INDEX {$table}_search ON {$table} USING GIN(to_tsvector('simple', search_text))");
            $db->unprepared("CREATE FUNCTION {$table}_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.value = 9 THEN RAISE EXCEPTION 'installer_probe'; END IF; RETURN NEW; END \$\$");
            $db->statement("CREATE TRIGGER {$table}_guard BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_guard()");
            $db->table($table)->insert(['id' => 1, 'value' => 1, 'payload' => '{"ready":true}']);
            $db->table($table)->where('id', 1)->update(['value' => 2]);
            $row = $db->selectOne("SELECT doubled, payload->>'ready' AS ready FROM {$table}");
            if ((int) $row->doubled !== 4 || $row->ready !== 'true') {
                throw new RuntimeException('Generated columns or JSON did not round trip.');
            }
            foreach ([
                [fn () => $db->table($table)->insert(['id' => 2, 'value' => -1]), '23514'],
                [fn () => $db->table($table)->insert(['id' => 2, 'value' => 1, 'parent_id' => 999]), '23503'],
                [fn () => $db->table($table)->where('id', 1)->update(['value' => 9]), 'P0001'],
            ] as [$operation, $code]) {
                // PostgreSQL requires a savepoint rollback after any SQL error.
                $rejected = false;
                $db->beginTransaction();
                try {
                    $operation();
                } catch (QueryException $exception) {
                    if (($exception->errorInfo[0] ?? null) !== $code) {
                        throw $exception;
                    }
                    $rejected = true;
                } finally {
                    $db->rollBack();
                }
                if (! $rejected) {
                    throw new RuntimeException('A required database constraint was not enforced.');
                }
            }
            $db->beginTransaction();
            $db->table($table)->insert(['id' => 2, 'value' => 3, 'parent_id' => 1]);
            $rows = $db->select("SELECT LAG(value) OVER (ORDER BY id) AS previous_value FROM {$table} ORDER BY id");
            $db->rollBack();
            if ((int) $rows[1]->previous_value !== 2 || $db->table($table)->where('id', 2)->exists()) {
                throw new RuntimeException('Window functions or rollback failed.');
            }
            $db->table($table)->delete();
            $db->statement("DROP TABLE {$table}");
            $db->statement("DROP FUNCTION {$table}_guard()");
        } finally {
            $db->rollBack($depth);
        }
    }

    /**
     * Require the intended constraint violation instead of accepting unrelated SQL failures.
     *
     * @param  callable(): mixed  $operation
     * @param  list<int>  $codes
     *
     * @throws QueryException
     * @throws RuntimeException
     */
    private function expectRejection(callable $operation, array $codes): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            if (in_array((int) ($exception->errorInfo[1] ?? 0), $codes, true)) {
                return;
            }
            throw $exception;
        }
        throw new RuntimeException('A required database constraint was not enforced.');
    }
}
