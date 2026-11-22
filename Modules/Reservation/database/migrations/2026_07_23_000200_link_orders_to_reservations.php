<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Models\Order;
use Modules\Reservation\Models\Reservation;

return new class extends Migration
{
    public function up(): void
    {
        $ordersTable = (new Order)->getTable();
        $reservationsTable = (new Reservation)->getTable();

        Schema::table($ordersTable, function (Blueprint $table) use ($reservationsTable): void {
            $table->foreignIdFor(Reservation::class)
                ->nullable()
                ->after('customer_id')
                ->constrained($reservationsTable)
                ->nullOnDelete();
        });

        DB::table($reservationsTable)
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->select(['id', 'order_id'])
            ->chunkById(250, function ($reservations) use ($ordersTable): void {
                foreach ($reservations as $reservation) {
                    DB::table($ordersTable)
                        ->where('id', $reservation->order_id)
                        ->whereNull('reservation_id')
                        ->update(['reservation_id' => $reservation->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table((new Order)->getTable(), function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
