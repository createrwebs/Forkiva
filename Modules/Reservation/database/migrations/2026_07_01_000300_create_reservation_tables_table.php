<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Models\Reservation;
use Modules\SeatingPlan\Models\Table;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Reservation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Table::class)->constrained()->cascadeOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->foreignIdFor(User::class, 'assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['reservation_id', 'table_id']);
            $table->index('reservation_id');
            $table->index('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_tables');
    }
};
