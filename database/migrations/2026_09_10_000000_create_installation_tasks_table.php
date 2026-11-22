<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create durable receipts for completed installation seed tasks.
     */
    public function up(): void
    {
        Schema::create('installation_tasks', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('completed_at');
        });
    }

    /**
     * Remove the installation task receipts table.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_tasks');
    }
};
