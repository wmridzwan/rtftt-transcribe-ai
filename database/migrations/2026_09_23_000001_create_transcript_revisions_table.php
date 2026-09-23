<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-002 — durable editable revision layer (D6-01/D6-02).
     *
     * Additive only: no existing table/column is dropped or semantically
     * mutated. `version` is a transcription-scoped monotonic sequence
     * (independent of ancestry); `(transcription_id, version)` uniqueness is the
     * durable backstop against branch version collisions.
     */
    public function up(): void
    {
        Schema::create('transcript_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('transcription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->uuid('parent_revision_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transcription_id', 'version']);
            $table->index('parent_revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_revisions');
    }
};
