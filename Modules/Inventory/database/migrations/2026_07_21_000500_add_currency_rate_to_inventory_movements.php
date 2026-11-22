<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('currency_rate', 18, 8)->nullable()->after('currency');
            $table->string('currency_rate_source')->nullable()->after('currency_rate');
            $table->timestamp('currency_rate_reviewed_at')->nullable()->after('currency_rate_source');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->decimal('currency_rate', 18, 8)->nullable()->after('currency');
        });

        DB::table('stock_movements')
            ->where('currency', setting('default_currency', 'JOD'))
            ->whereNull('currency_rate')
            ->update([
                'currency_rate' => 1,
                'currency_rate_source' => 'base_currency',
                'currency_rate_reviewed_at' => now(),
            ]);

        DB::table('stock_transfers')
            ->where('currency', setting('default_currency', 'JOD'))
            ->whereNull('currency_rate')
            ->update(['currency_rate' => 1]);

        DB::table('stock_movements')
            ->whereNotNull('reversal_of_id')
            ->whereNull('currency_rate')
            ->orderBy('id')
            ->eachById(function (object $reversal): void {
                $original = DB::table('stock_movements')->where('id', $reversal->reversal_of_id)->first();
                if ($original && $original->currency_rate !== null) {
                    DB::table('stock_movements')->where('id', $reversal->id)->update([
                        'currency_rate' => $original->currency_rate,
                        'currency_rate_source' => 'original_movement',
                        'currency_rate_reviewed_at' => $original->currency_rate_reviewed_at ?? now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn('currency_rate');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['currency_rate', 'currency_rate_source', 'currency_rate_reviewed_at']);
        });
    }
};
