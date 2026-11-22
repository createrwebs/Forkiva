<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Enums\ReservationReminderStatus;
use Modules\Reservation\Models\Reservation;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Reservation::class)->constrained()->cascadeOnDelete();
            $table->string('channel', 50);
            $table->string('reminder_type', 50);
            $table->dateTime('scheduled_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('status', 50)->default(ReservationReminderStatus::Pending->value);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index('reservation_id');
            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_reminders');
    }
};
