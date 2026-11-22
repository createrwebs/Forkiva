<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::unprepared(
                "CREATE TRIGGER stock_transfers_terminal_status_guard
            BEFORE UPDATE ON stock_transfers
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status
                    AND NOT (
                        (OLD.status = 'draft' AND NEW.status IN ('approved', 'cancelled'))
                        OR (OLD.status = 'approved' AND NEW.status IN ('dispatched', 'cancelled'))
                        OR (OLD.status = 'dispatched' AND NEW.status IN ('partially_received', 'received', 'closed_with_discrepancy', 'voided'))
                        OR (OLD.status = 'partially_received' AND NEW.status IN ('received', 'closed_with_discrepancy'))
                    ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Invalid stock transfer status transition';
                END IF;
            END"
            );
        } catch (QueryException $exception) {
            if (! $this->isBinaryLoggingPrivilegeError($exception)) {
                throw $exception;
            }

            Log::warning('Skipped stock-transfer terminal-status trigger because MySQL binary logging does not trust trigger creators.', [
                'trigger' => 'stock_transfers_terminal_status_guard',
                'mysql_error_code' => 1419,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS stock_transfers_terminal_status_guard');
        }
    }

    private function isBinaryLoggingPrivilegeError(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1419;
    }
};
