<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * P6-002 / P6-005 migration reversibility.
 *
 * Runs the real migrations then rolls the three additive P6-002 migrations and
 * the two additive P6-005 migrations back on a dedicated SQLite file, proving
 * `down()` is clean (tables dropped, active pointer and P6-005 columns removed)
 * without disturbing the default test database.
 *
 * P7-006 note: the additive scan-verdict migration now leads the tree, so
 * --step covers 6 (P7-006 + the five P6 migrations); the P6 assertions are
 * unchanged, and one scan_verdict assertion proves the P7-006 `down()` is
 * clean too.
 *
 * P7-002 note: the pgsql-only parity migration now leads the tree, so
 * --step covers 7. Its `down()` is a deliberate no-op on sqlite (driver
 * guard — pg indexes are never created there); the step bump only moves
 * the rollback window to still cover P7-006 + the five P6 migrations.
 * The P6/P7-006 assertions are unchanged.
 *
 * P7-011 note: the purged_at tombstone column + purge-audit ledger now
 * lead the tree, so --step covers 9. Both `down()`s are sqlite-clean
 * (drop column / drop table); the window still covers P7-002 + P7-006 +
 * the five P6 migrations, whose assertions are unchanged.
 */

it('rolls the additive P6-002 and P6-005 migrations back cleanly', function () {
    $testId = Str::uuid()->toString();
    $path = storage_path("app/test-revision-rollback-{$testId}.db");
    touch($path);

    config(['database.connections.revision_rollback' => array_merge(
        config('database.connections.sqlite'),
        ['database' => $path, 'foreign_key_constraints' => false],
    )]);

    try {
        Artisan::call('migrate', ['--database' => 'revision_rollback', '--force' => true]);

        expect(Schema::connection('revision_rollback')->hasTable('transcript_revisions'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasTable('transcript_revision_segments'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasColumn('transcriptions', 'active_revision_id'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasColumn('transcript_revision_segments', 'language_provenance'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'stale_at'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'staleness_reason'))->toBeTrue()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'stale_caused_by_revision_id'))->toBeTrue();

        Artisan::call('migrate:rollback', [
            '--database' => 'revision_rollback',
            '--step' => 9,
            '--force' => true,
        ]);

        expect(Schema::connection('revision_rollback')->hasTable('transcript_revisions'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasTable('transcript_revision_segments'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('transcriptions', 'active_revision_id'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'stale_at'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'staleness_reason'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('translations', 'stale_caused_by_revision_id'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('media_files', 'scan_verdict'))->toBeFalse();
    } finally {
        DB::purge('revision_rollback');

        if (file_exists($path)) {
            @unlink($path);
        }
    }
});
