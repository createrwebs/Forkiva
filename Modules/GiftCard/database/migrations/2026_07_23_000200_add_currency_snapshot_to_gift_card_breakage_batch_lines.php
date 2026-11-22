<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_breakage_batch_lines', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('amount');
            $table->decimal('currency_rate', 18, 8)->nullable()->after('currency');
        });

        DB::table('gift_card_breakage_batch_lines')
            ->select(['id', 'gift_card_breakage_batch_id'])
            ->orderBy('id')
            ->chunkById(500, function ($lines): void {
                $batches = DB::table('gift_card_breakage_batches')
                    ->whereIn('id', $lines->pluck('gift_card_breakage_batch_id')->unique())
                    ->get(['id', 'currency', 'currency_rate'])
                    ->keyBy('id');

                foreach ($lines as $line) {
                    $batch = $batches->get($line->gift_card_breakage_batch_id);

                    DB::table('gift_card_breakage_batch_lines')
                        ->where('id', $line->id)
                        ->update([
                            'currency' => $batch->currency,
                            'currency_rate' => $batch->currency_rate,
                        ]);
                }
            });

        Schema::table('gift_card_breakage_batch_lines', function (Blueprint $table) {
            $table->string('currency', 3)->nullable(false)->change();
            $table->decimal('currency_rate', 18, 8)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_breakage_batch_lines', function (Blueprint $table) {
            $table->dropColumn(['currency', 'currency_rate']);
        });
    }
};
