<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->uuid('upload_attempt_id')->nullable()->after('user_id');
            $table->unique(['user_id', 'upload_attempt_id'], 'media_files_user_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropUnique('media_files_user_attempt_unique');
            $table->dropColumn('upload_attempt_id');
        });
    }
};
