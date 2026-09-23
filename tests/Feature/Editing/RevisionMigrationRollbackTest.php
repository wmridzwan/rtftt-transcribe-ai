<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * P6-002 migration reversibility.
 *
 * Runs the real migrations then rolls the three additive P6-002 migrations back
 * on a dedicated SQLite file, proving `down()` is clean (tables dropped, active
 * pointer column removed) without disturbing the default test database.
 */

it('rolls the additive P6-002 migrations back cleanly', function () {
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
            ->and(Schema::connection('revision_rollback')->hasColumn('transcriptions', 'active_revision_id'))->toBeTrue();

        Artisan::call('migrate:rollback', [
            '--database' => 'revision_rollback',
            '--step' => 3,
            '--force' => true,
        ]);

        expect(Schema::connection('revision_rollback')->hasTable('transcript_revisions'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasTable('transcript_revision_segments'))->toBeFalse()
            ->and(Schema::connection('revision_rollback')->hasColumn('transcriptions', 'active_revision_id'))->toBeFalse();
    } finally {
        DB::purge('revision_rollback');

        if (file_exists($path)) {
            @unlink($path);
        }
    }
});
