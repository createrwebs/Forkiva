<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Enums\NotificationTemplateContentType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_template_messages', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->index();
            $table->string('channel_key')->index();
            $table->json('subject')->nullable();
            $table->json('content');
            $table->enum('content_type', NotificationTemplateContentType::values())
                ->default(NotificationTemplateContentType::Text->value);
            $table->timestamps();

            $table->unique(['template_key', 'channel_key'], 'notification_template_message_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_template_messages');
    }
};
