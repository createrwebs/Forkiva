<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track PDPA consent and erasure so customer personal-data requests can be
     * honoured while financial records are retained for legal obligations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('pdpa_consent_at')->nullable()->after('postal_code');
            $table->timestamp('pdpa_anonymized_at')->nullable()->after('pdpa_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pdpa_consent_at', 'pdpa_anonymized_at']);
        });
    }
};
