<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->dropIndex(['transcription_id', 'segment_index']);
            $table->unique(['transcription_id', 'segment_index']);
        });
    }

    public function down(): void
    {
        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->dropUnique(['transcription_id', 'segment_index']);
            $table->index(['transcription_id', 'segment_index']);
        });
    }
};
