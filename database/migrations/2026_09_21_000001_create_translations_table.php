<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcription_id')->constrained()->cascadeOnDelete();
            $table->string('target_language', 16);
            $table->string('status', 20)->default('pending');
            $table->string('source_language', 16)->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('full_text')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['transcription_id', 'target_language']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
