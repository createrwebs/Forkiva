<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Create user-owned preference storage with a unique key per user.
     */
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('identifier', 191);
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->longText('payload');
            $table->timestamps();

            $table->unique(['user_id', 'identifier']);
        });
    }

    /**
     * Remove user preference storage when rolling back this migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
