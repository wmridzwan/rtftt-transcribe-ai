<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P7-011 purge audit ledger (D7-06/G-09). One row per purge decision
     * outcome — purged, absent, failed, skipped, protected — scoped by a
     * per-run identifier so resume/repeat runs stay accountable. Audit
     * rows themselves are never purge-eligible under P7-011 (§6.10).
     * Additive; rolled back by dropping the table.
     */
    public function up(): void
    {
        Schema::create('retention_purge_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('run_id', 36);
            $table->string('purgeable_type', 32);
            $table->unsignedBigInteger('purgeable_id')->nullable();
            $table->string('artifact_path', 1024)->nullable();
            $table->string('outcome', 16);
            $table->string('reason', 1024)->nullable();
            $table->timestamps();

            $table->index(['run_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_purge_audits');
    }
};
