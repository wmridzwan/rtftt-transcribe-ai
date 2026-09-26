<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P7-002 PostgreSQL defense-in-depth parity (D7-01/B).
     *
     * The Phase 3/5 partial unique indexes
     * (`processing_jobs_active_attempt_unique`,
     * `translations_active_target_unique`) were authored sqlite-only:
     * their `up()`/`down()` return early for any non-sqlite driver, so on
     * PostgreSQL the one-active-attempt and one-active-translation
     * invariants silently lose their database-level backstop while the
     * CAS application guards remain primary.
     *
     * This additive migration creates the same partial unique indexes for
     * the pgsql driver only (PostgreSQL supports partial unique indexes;
     * `IF NOT EXISTS` is valid for `CREATE UNIQUE INDEX`). It is a no-op
     * on every other driver, including sqlite (whose own migration pair
     * remains authoritative there). Historical migrations are untouched.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS processing_jobs_active_attempt_unique'
            ." ON processing_jobs (transcription_id) WHERE status IN ('queued', 'running')"
        );

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS translations_active_target_unique'
            ." ON translations (transcription_id, target_language) WHERE status IN ('pending', 'queued', 'translating', 'completed')"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS processing_jobs_active_attempt_unique');
        DB::statement('DROP INDEX IF EXISTS translations_active_target_unique');
    }
};
