<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcription_id')->constrained()->cascadeOnDelete();
            $table->uuid('job_uuid')->unique();
            $table->string('worker_name')->nullable();
            $table->string('stage');
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('processing_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->json('logs')->nullable();
            $table->timestamps();

            $table->index(['transcription_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_jobs');
    }
};
