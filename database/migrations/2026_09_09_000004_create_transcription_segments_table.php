<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('segment_index');
            $table->unsignedInteger('start_seconds');
            $table->unsignedInteger('end_seconds');
            $table->text('text');
            $table->timestamps();

            $table->index(['transcription_id', 'segment_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcription_segments');
    }
};
