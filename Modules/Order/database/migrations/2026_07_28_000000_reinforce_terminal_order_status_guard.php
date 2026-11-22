<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const TRIGGER = 'orders_terminal_status_immutable';

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER);

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::unprepared(sprintf(
                    "CREATE TRIGGER %s BEFORE UPDATE ON orders
                FOR EACH ROW
                BEGIN
                    IF OLD.status IN ('cancelled', 'refunded', 'merged')
                       AND NEW.status <> OLD.status THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Terminal order status cannot be changed';
                    END IF;
                END",
                    self::TRIGGER,
                ));
            } catch (QueryException $exception) {
                if (! $this->isBinaryLoggingPrivilegeError($exception)) {
                    throw $exception;
                }

                Log::warning('Skipped order terminal-status trigger because MySQL binary logging does not trust trigger creators.', [
                    'trigger' => self::TRIGGER,
                    'mysql_error_code' => 1419,
                ]);
            }

            return;
        }

        DB::unprepared(sprintf(
            "CREATE TRIGGER %s
            BEFORE UPDATE OF status ON orders
            WHEN OLD.status IN ('cancelled', 'refunded', 'merged')
                 AND NEW.status <> OLD.status
            BEGIN
                SELECT RAISE(ABORT, 'Terminal order status cannot be changed');
            END",
            self::TRIGGER,
        ));
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER);
        }
    }

    private function isBinaryLoggingPrivilegeError(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1419;
    }
};
