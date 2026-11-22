<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION orders_terminal_status_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status IN ('cancelled', 'refunded', 'merged') AND NEW.status IS DISTINCT FROM OLD.status THEN
                    RAISE EXCEPTION 'Terminal order status cannot be changed';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER orders_terminal_status_immutable BEFORE UPDATE ON orders
            FOR EACH ROW EXECUTE FUNCTION orders_terminal_status_guard();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS orders_terminal_status_immutable ON orders');
            DB::statement('DROP FUNCTION IF EXISTS orders_terminal_status_guard()');
        }
    }
};
