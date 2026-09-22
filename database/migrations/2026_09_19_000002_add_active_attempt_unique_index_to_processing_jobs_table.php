<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'processing_jobs_active_attempt_unique';

    /**
     * P3-007 defense-in-depth for the one-active-attempt-per-transcription
     * invariant. The primary correctness boundary is the guarded compare-and-set
     * in App\Actions\TranscriptionRetry; this additive partial unique index
     * guarantees a second queued/running attempt can never be persisted.
     *
     * The canonical environment is SQLite (ADR-013 / Batch 2 review: no
     * separate production engine is decided). Historical completed/failed
     * attempts are excluded by the partial predicate and are never rejected.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS '.self::INDEX
            ." ON processing_jobs (transcription_id) WHERE status IN ('queued', 'running')"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
