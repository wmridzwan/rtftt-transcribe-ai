<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-002 — active revision pointer (D6-05).
     *
     * Additive only: `null` means the immutable machine source is authoritative.
     * `nullOnDelete` keeps the pointer safe if a revision row is ever removed;
     * the revision layer itself is append-only in application code.
     */
    public function up(): void
    {
        Schema::table('transcriptions', function (Blueprint $table) {
            $table->uuid('active_revision_id')->nullable()->after('status');
            $table->foreign('active_revision_id')->references('id')->on('transcript_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transcriptions', function (Blueprint $table) {
            $table->dropForeign(['active_revision_id']);
            $table->dropColumn('active_revision_id');
        });
    }
};
