<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'translations_active_target_unique';

    /**
     * P5-002 defense-in-depth for the one-active/successful-translation-per-
     * target invariant (D5-03). Historical failed attempts are excluded by the
     * partial predicate so re-translation can retain prior failures.
     *
     * Mirrors the P3-007 partial-index precedent: the canonical environment is
     * SQLite; the predicate is applied only for that driver.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS '.self::INDEX
            ." ON translations (transcription_id, target_language) WHERE status IN ('pending', 'queued', 'translating', 'completed')"
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
