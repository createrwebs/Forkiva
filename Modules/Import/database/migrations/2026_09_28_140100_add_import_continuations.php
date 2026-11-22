<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table): void {
            $table->boolean('client_processing')->default(false);
            $table->json('continuation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('imports', fn (Blueprint $table) => $table->dropColumn(['client_processing', 'continuation']));
    }
};
