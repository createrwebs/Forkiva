<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Enums\NotificationPushTokenClient;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_push_tokens', function (Blueprint $table) {
            $table->string('client', 50)->default(NotificationPushTokenClient::Web->value)->after('platform');
            $table->index(['user_id', 'provider', 'client']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_push_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'provider', 'client']);
            $table->dropColumn('client');
        });
    }
};
