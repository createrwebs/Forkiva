<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('table_merges')) {
            return;
        }

        $foreignExists = collect(Schema::getForeignKeys('orders'))
            ->contains(fn (array $foreign): bool => in_array('table_merge_id', $foreign['columns'] ?? [], true));

        if (! $foreignExists) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreign('table_merge_id')
                    ->references('id')
                    ->on('table_merges')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $foreignExists = collect(Schema::getForeignKeys('orders'))
            ->contains(fn (array $foreign): bool => in_array('table_merge_id', $foreign['columns'] ?? [], true));

        if ($foreignExists) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropForeign(['table_merge_id']);
            });
        }
    }
};
