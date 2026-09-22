<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-007: persist the provider-neutral domain failure classification for a
     * processing attempt so retry eligibility can be derived deterministically
     * without storing raw provider metadata.
     */
    public function up(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->string('failure_code')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropColumn('failure_code');
        });
    }
};
