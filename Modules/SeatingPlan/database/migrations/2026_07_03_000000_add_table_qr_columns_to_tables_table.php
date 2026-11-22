<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->string('table_qr_token', 128)
                ->nullable()
                ->unique()
                ->after('uuid');
            $table->timestamp('table_qr_token_regenerated_at')
                ->nullable()
                ->after('table_qr_token');
        });

        DB::table('tables')
            ->whereNull('table_qr_token')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($table) {
                DB::table('tables')
                    ->where('id', $table->id)
                    ->update([
                        'table_qr_token' => Str::random(64),
                        'table_qr_token_regenerated_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['table_qr_token']);
            $table->dropColumn(['table_qr_token', 'table_qr_token_regenerated_at']);
        });
    }
};
