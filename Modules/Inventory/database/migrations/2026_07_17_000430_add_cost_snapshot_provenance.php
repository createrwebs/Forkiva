<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('cost_snapshot_source', 64)
                ->default('recorded')
                ->after('legacy_cost_snapshot_missing');
            $table->timestamp('cost_snapshot_remediated_at')
                ->nullable()
                ->after('cost_snapshot_source');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn([
                'cost_snapshot_source',
                'cost_snapshot_remediated_at',
            ]);
        });
    }
};
