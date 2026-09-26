<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P7-006: per-media malware-scan verdict provenance. All columns
     * nullable: rows created before scanning existed, or uploads that
     * legitimately skipped scanning in non-production, carry nulls.
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->string('scan_verdict', 16)->nullable()->after('status');
            $table->string('scan_engine', 64)->nullable()->after('scan_verdict');
            $table->string('scan_signature_date', 64)->nullable()->after('scan_engine');
            $table->timestamp('scanned_at')->nullable()->after('scan_signature_date');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropColumn(['scan_verdict', 'scan_engine', 'scan_signature_date', 'scanned_at']);
        });
    }
};
