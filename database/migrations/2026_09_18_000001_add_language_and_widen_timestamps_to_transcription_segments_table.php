<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->string('language', 16)->default('und')->after('text');
        });

        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->decimal('start_seconds', 12, 3)->change();
            $table->decimal('end_seconds', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->unsignedInteger('start_seconds')->change();
            $table->unsignedInteger('end_seconds')->change();
        });

        Schema::table('transcription_segments', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
