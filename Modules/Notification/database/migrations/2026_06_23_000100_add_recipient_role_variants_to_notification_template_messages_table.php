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
        Schema::table('notification_template_messages', function (Blueprint $table) {
            $table->dropUnique('notification_template_message_unique');
            $table->string('recipient_role_key')->default('default')->after('channel_key');
            $table->json('title')->nullable()->after('recipient_role_key');
            $table->unique(
                ['template_key', 'channel_key', 'recipient_role_key'],
                'notification_template_message_role_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_template_messages', function (Blueprint $table) {
            $table->dropUnique('notification_template_message_role_unique');
            $table->dropColumn(['recipient_role_key', 'title']);
            $table->unique(['template_key', 'channel_key'], 'notification_template_message_unique');
        });
    }
};
