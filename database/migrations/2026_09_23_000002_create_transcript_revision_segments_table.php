<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-002 — ordered segments of a durable revision (D6-01/D6-03/D6-04).
     *
     * Additive only. `segment_key` is the stable `RevisionSegmentIdentity`;
     * `position` is unique and contiguous `0..n-1` per revision (enforced by the
     * domain layer and the `(revision_id, position)` uniqueness backstop).
     */
    public function up(): void
    {
        Schema::create('transcript_revision_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('revision_id');
            $table->foreign('revision_id')->references('id')->on('transcript_revisions')->cascadeOnDelete();
            $table->string('segment_key');
            $table->unsignedInteger('position');
            $table->decimal('start_seconds', 12, 3);
            $table->decimal('end_seconds', 12, 3);
            $table->text('text');
            $table->string('language', 16)->default('und');
            $table->timestamps();

            $table->unique(['revision_id', 'segment_key']);
            $table->unique(['revision_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_revision_segments');
    }
};
