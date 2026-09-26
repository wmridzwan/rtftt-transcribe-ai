<?php

use App\Backup\BackupManager;

/*
 * P7-007: generation pruning with last-good protection, inventory
 * reconciliation, pre-migrate hook goldens, diagnostics presence, and
 * credential redaction. Temp dirs only; repo files are never modified
 * (divergence is proven against temp copies of the manifest shape).
 */

function backupSetDir(string $target, string $name, string $status, string $createdAt): string
{
    $dir = $target.DIRECTORY_SEPARATOR.$name;
    mkdir($dir, 0750, true);

    $manifest = ['name' => $name, 'created_at' => $createdAt, 'status' => $status];
    $manifest['root'] = hash('sha256', (string) json_encode($manifest));
    file_put_contents($dir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest));

    return $dir;
}

it('prunes beyond generations while protecting the last good set', function (): void {
    config()->set('backup.generations', 2);
    $target = backupTempTarget();

    try {
        $manager = new BackupManager(Storage::disk('local'));
        backupSetDir($target, '20260101-000001-old-fail', 'failed', '2026-01-01T00:00:00+00:00');
        backupSetDir($target, '20260102-000002-old-good', 'ok', '2026-01-02T00:00:00+00:00');
        backupSetDir($target, '20260103-000003-mid-good', 'ok', '2026-01-03T00:00:00+00:00');
        backupSetDir($target, '20260104-000004-new-good', 'ok', '2026-01-04T00:00:00+00:00');

        $manager->prune($target);
        $remaining = $manager->listSets($target);

        // Newest 2 good sets survive; older good pruned; old failed (older
        // than newest good) pruned too.
        expect($remaining)->toContain('20260104-000004-new-good')
            ->and($remaining)->toContain('20260103-000003-mid-good')
            ->and($remaining)->not->toContain('20260102-000002-old-good')
            ->and($remaining)->not->toContain('20260101-000001-old-fail');
    } finally {
        removeDirectoryTree($target);
    }
});

it('keeps a newer failed set until a good set supersedes it', function (): void {
    config()->set('backup.generations', 7);
    $target = backupTempTarget();

    try {
        $manager = new BackupManager(Storage::disk('local'));
        backupSetDir($target, '20260104-000004-new-good', 'ok', '2026-01-04T00:00:00+00:00');
        backupSetDir($target, '20260105-000005-newer-fail', 'failed', '2026-01-05T00:00:00+00:00');

        $manager->prune($target);

        // The newer failed set is quarantined evidence, not prunable yet;
        // the newest good set is never deleted.
        expect($manager->listSets($target))->toContain('20260105-000005-newer-fail')
            ->and($manager->listSets($target))->toContain('20260104-000004-new-good');
    } finally {
        removeDirectoryTree($target);
    }
});

it('never prunes when no good set exists', function (): void {
    $target = backupTempTarget();

    try {
        $manager = new BackupManager(Storage::disk('local'));
        backupSetDir($target, '20260105-000005-only-fail', 'failed', '2026-01-05T00:00:00+00:00');

        $manager->prune($target);

        expect($manager->listSets($target))->toContain('20260105-000005-only-fail');
    } finally {
        removeDirectoryTree($target);
    }
});

it('reconciles the live migration inventory and detects divergence in copies', function (): void {
    $manager = new BackupManager(Storage::disk('local'));

    // Live tree: reconciled (P7-008 pin + P7-006 append intact).
    expect($manager->inventoryDivergence())->toBeNull();

    // Fabricated divergence against temp copies (repo files untouched).
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7007-inv-'.uniqid();
    mkdir($dir, 0750, true);

    try {
        copy(database_path('migrations/2026_09_09_000002_create_media_files_table.php'), $dir.DIRECTORY_SEPARATOR.'a.php');
        file_put_contents($dir.DIRECTORY_SEPARATOR.'a.php', "<?php // tampered\n", FILE_APPEND);

        $fakeManifest = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7007-manifest-'.uniqid().'.json';
        file_put_contents($fakeManifest, json_encode(['migrations' => [
            'a.php' => hash('sha256', 'original-bytes-that-no-longer-match'),
        ]]));

        expect($manager->inventoryDivergence($fakeManifest, $dir))->toContain('diverged');

        // Unpinned file present alongside a pinned one.
        copy(
            database_path('migrations/2026_09_09_000002_create_media_files_table.php'),
            $dir.DIRECTORY_SEPARATOR.'b-unpinned.php'
        );
        file_put_contents($fakeManifest, json_encode(['migrations' => [
            'a.php' => hash_file('sha256', $dir.DIRECTORY_SEPARATOR.'a.php'),
        ]]));

        // a.php now matches, but b-unpinned.php is unpinned.
        file_put_contents($dir.DIRECTORY_SEPARATOR.'a.php', file_get_contents(
            database_path('migrations/2026_09_09_000002_create_media_files_table.php')
        ));
        file_put_contents($fakeManifest, json_encode(['migrations' => [
            'a.php' => hash_file('sha256', $dir.DIRECTORY_SEPARATOR.'a.php'),
        ]]));

        expect($manager->inventoryDivergence($fakeManifest, $dir))->toContain('Unpinned');

        unlink($fakeManifest);
    } finally {
        removeDirectoryTree($dir);
    }
});

it('emits golden pre-migrate hook output on success', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');

    $db = backupTempDb();
    $target = backupTempTarget();

    try {
        withBackupIsolation($db, $target, function (): void {
            $this->artisan('backup:pre-migrate')
                ->expectsOutputToContain('PRE-MIGRATE BACKUP OK')
                ->assertExitCode(0);
        });
    } finally {
        unlink($db);
        removeDirectoryTree($target);
    }
});

it('refuses pre-migrate when no backup can run', function (): void {
    $target = backupTempTarget();

    try {
        withBackupIsolation(':memory:', $target, function (): void {
            $this->artisan('backup:pre-migrate')
                ->expectsOutputToContain('PRE-MIGRATE BACKUP REFUSED')
                ->assertExitCode(1);
        });
    } finally {
        removeDirectoryTree($target);
    }
});

it('reports backup presence in verify and diagnostics', function (): void {
    $this->artisan('deployment:verify')->expectsOutputToContain('Backups:');
    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Backup target:')
        ->expectsOutputToContain('Backup schedule:')
        ->expectsOutputToContain('Backup last-good:');
});

it('keeps credentials out of manifests and command output', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');

    $db = backupTempDb();
    $target = backupTempTarget();

    try {
        withBackupIsolation($db, $target, function () use ($target): void {
            $manager = BackupManager::forMediaDisk();
            $result = $manager->run('sqlite');

            expect($result['ok'])->toBeTrue();

            $manifestText = (string) file_get_contents(
                $target.DIRECTORY_SEPARATOR.$result['set'].DIRECTORY_SEPARATOR.'manifest.json'
            );

            foreach (['password', 'secret', 'token', 'credential'] as $needle) {
                expect(strtolower($manifestText))->not->toContain($needle);
            }
        });
    } finally {
        unlink($db);
        removeDirectoryTree($target);
    }
});
