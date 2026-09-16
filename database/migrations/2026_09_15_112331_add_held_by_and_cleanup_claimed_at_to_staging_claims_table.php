<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_claims', function (Blueprint $table) {
            $table->string('held_by')->default('upload')->after('staging_path');
            $table->timestamp('cleanup_claimed_at')->nullable()->after('held_by');
        });

        // Backfill existing rows to 'upload' held_by
        DB::table('staging_claims')->whereNull('held_by')->update(['held_by' => 'upload']);
    }

    public function down(): void
    {
        Schema::table('staging_claims', function (Blueprint $table) {
            $table->dropColumn(['held_by', 'cleanup_claimed_at']);
        });
    }
};
