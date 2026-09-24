<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-005 — explicit revision-segment mixed-language provenance
     * (DECISION-P6-005-LANGUAGE-PROVENANCE-001; DECISION-P6-005-SCHEMA-001).
     *
     * Nullable when unnecessary; ordered contributor language markers in
     * contributor order; independent of machine `segment_index`. Not a generic
     * metadata dumping field.
     */
    public function up(): void
    {
        Schema::table('transcript_revision_segments', function (Blueprint $table) {
            $table->text('language_provenance')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('transcript_revision_segments', function (Blueprint $table) {
            $table->dropColumn('language_provenance');
        });
    }
};
