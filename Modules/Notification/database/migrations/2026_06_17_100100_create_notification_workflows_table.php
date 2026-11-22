<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('channels');
            $table->order();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_workflows');
    }
};
