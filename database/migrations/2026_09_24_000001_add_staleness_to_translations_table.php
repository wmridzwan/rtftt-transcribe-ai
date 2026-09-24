<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-005 — persisted translation invalidation marker (D6-04;
     * DECISION-P6-005-SCHEMA-001).
     *
     * Additive only. Existing Phase 5 translation rows and translation segments
     * are preserved; Phase 5 translation identity is not rewritten.
     */
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->timestamp('stale_at')->nullable();
            $table->string('staleness_reason', 50)->nullable();
            $table->uuid('stale_caused_by_revision_id')->nullable();

            $table->index('staleness_reason');
            $table->index('stale_caused_by_revision_id');
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex(['staleness_reason']);
            $table->dropIndex(['stale_caused_by_revision_id']);
            $table->dropColumn(['stale_at', 'staleness_reason', 'stale_caused_by_revision_id']);
        });
    }
};
