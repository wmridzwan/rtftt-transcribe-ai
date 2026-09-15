<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->string('checksum_sha256', 64)->nullable()->after('storage_path');
            $table->index('checksum_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['checksum_sha256']);
            $table->dropColumn('checksum_sha256');
        });
    }
};
