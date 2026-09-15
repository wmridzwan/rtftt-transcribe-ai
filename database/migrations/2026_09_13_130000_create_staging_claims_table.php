<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('upload_attempt_id');
            $table->string('staging_path');
            $table->timestamp('claimed_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'upload_attempt_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_claims');
    }
};
