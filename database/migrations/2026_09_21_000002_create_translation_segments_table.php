<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('segment_index');
            $table->decimal('start_seconds', 12, 3);
            $table->decimal('end_seconds', 12, 3);
            $table->text('text');
            $table->string('source_language', 16)->default('und');
            $table->timestamps();

            $table->unique(['translation_id', 'segment_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_segments');
    }
};
