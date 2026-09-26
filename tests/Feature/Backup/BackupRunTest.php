<?php

use App\Backup\BackupManager;
use Illuminate\Support\Facades\Storage;

/*
 * P7-007: backup foundation. Shared temp-file helpers
 * (backupTempDb/backupTempTarget/removeDirectoryTree/
 * withBackupIsolation) live in tests/Support/backup-helpers.php,
 * loaded once from tests/Pest.php so every backup file runs in
 * isolation.
 */

it('produces a manifest-valid set with passing integrity', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('media/uuid-1/song.mp3', str_repeat('m', 2048));

    $db = backupTempDb();
    $target = backupTempTarget();

    try {
        withBackupIsolation($db, $target, function () use ($target): void {
            $manager = BackupManager::forMediaDisk();
            $result = $manager->run('sqlite');

            expect($result['ok'])->toBeTrue()
                ->and($result['errors'])->toBe([])
                ->and($result['manifest']['status'] ?? null)->toBe('ok')
                ->and($result['manifest']['media']['files'] ?? [])->toHaveCount(1)
                ->and($manager->latestManifest($target)['status'] ?? null)->toBe('ok');
        });
    } finally {
        unlink($db);
        removeDirectoryTree($target);
    }
});

it('excludes quarantine content from media manifests', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('media/uuid-1/song.mp3', 'clean');
    Storage::disk('local')->put('media/quarantine/evil.mp3', 'infected');

    $db = backupTempDb();
    $target = backupTempTarget();

    try {
        withBackupIsolation($db, $target, function (): void {
            $result = BackupManager::forMediaDisk()->run('sqlite');
            $paths = array_column($result['manifest']['media']['files'] ?? [], 'path');

            expect($result['ok'])->toBeTrue()
                ->and($paths)->toHaveCount(1)
                ->and($paths[0] ?? '')->not->toContain('quarantine');
        });
    } finally {
        unlink($db);
        removeDirectoryTree($target);
    }
});

it('refuses non-file sources instead of backing up nothing', function (): void {
    $target = backupTempTarget();

    try {
        withBackupIsolation(':memory:', $target, function (): void {
            $result = BackupManager::forMediaDisk()->run('sqlite');

            expect($result['ok'])->toBeFalse()
                ->and(implode(' ', $result['errors']))->toContain('not a readable file');
        });
    } finally {
        removeDirectoryTree($target);
    }
});

it('refuses unknown drivers and fails pgsql loudly without tooling', function (): void {
    $manager = BackupManager::forMediaDisk();

    expect($manager->run('tapeworm')['ok'])->toBeFalse();

    // No pg_dump on the dev/test host: the pgsql path must fail loudly
    // on missing tooling (never silently fall back to SQLite, never the
    // old dormant refusal).
    $original = config('backup.pg_dump');
    config()->set('backup.pg_dump', 'rtftt-nonexistent-pg-dump-binary');

    try {
        $pgsql = $manager->run('pgsql');
    } finally {
        config()->set('backup.pg_dump', $original);
    }

    expect($pgsql['ok'])->toBeFalse()
        ->and($pgsql['set'])->toBeNull()
        ->and(implode(' ', $pgsql['errors']))->toContain('pg_dump');
});

it('requires an explicit driver on the command', function (): void {
    $this->artisan('backup:run')
        ->expectsOutputToContain('driver is required')
        ->assertExitCode(1);
});

it('detects tampered manifests on verify', function (): void {
    Storage::fake('local');

    $db = backupTempDb();
    $target = backupTempTarget();

    try {
        withBackupIsolation($db, $target, function () use ($target): void {
            $manager = BackupManager::forMediaDisk();
            $result = $manager->run('sqlite');

            expect($result['ok'])->toBeTrue();

            $manifestPath = $target.DIRECTORY_SEPARATOR.$result['set'].DIRECTORY_SEPARATOR.'manifest.json';
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $manifest['media']['root'] = 'tampered';
            file_put_contents($manifestPath, json_encode($manifest));

            expect($manager->verifySet($target.DIRECTORY_SEPARATOR.$result['set'])['ok'])->toBeFalse();
        });
    } finally {
        unlink($db);
        removeDirectoryTree($target);
    }
});
