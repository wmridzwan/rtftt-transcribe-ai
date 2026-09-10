<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('original_filename');
            $table->string('storage_filename');
            $table->string('storage_path');
            $table->string('media_type');
            $table->string('mime_type');
            $table->string('extension');
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('audio_codec')->nullable();
            $table->string('video_codec')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedSmallInteger('channels')->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
