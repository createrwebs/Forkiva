<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'notifications_search_text_fulltext';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
ALTER TABLE notifications
    ADD COLUMN search_text TEXT GENERATED ALWAYS AS (
        LOWER(CONCAT_WS(' ',
            JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(data), data, '{}'), '$.translations.*.title')),
            JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(data), data, '{}'), '$.translations.*.subject')),
            JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(data), data, '{}'), '$.translations.*.content'))
        ))
    ) STORED,
    ADD FULLTEXT INDEX notifications_search_text_fulltext (search_text)
SQL);

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->text('search_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE notifications DROP INDEX %s, DROP COLUMN search_text',
                self::INDEX_NAME,
            ));

            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
