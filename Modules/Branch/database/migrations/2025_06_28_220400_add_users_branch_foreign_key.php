<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('branches')) {
            return;
        }

        $foreignExists = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreign): bool => in_array('branch_id', $foreign['columns'] ?? [], true));

        if (! $foreignExists) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $foreignExists = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreign): bool => in_array('branch_id', $foreign['columns'] ?? [], true));

        if ($foreignExists) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['branch_id']);
            });
        }
    }
};
