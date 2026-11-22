<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Models\Reservation;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Reservation::class)->constrained()->cascadeOnDelete();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->foreignIdFor(User::class, 'changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();
            $table->index('reservation_id');
            $table->index('new_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_status_logs');
    }
};
