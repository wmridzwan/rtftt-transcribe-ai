<?php

use App\Backup\BackupManager;
use Illuminate\Support\Facades\Storage;

/*
 * Pre-Linux remediation (BLOCKER-B): PostgreSQL-native backup path.
 * No live PostgreSQL exists on the dev host, so execution against a
 * real server is NOT claimed here — these tests prove driver
 * acceptance, command construction, loud tooling/connection failure,
 * manifest/verify/prune discipline, the pre-migrate path, and the
 * absence of secret material. Live pg_dump execution is recorded for
 * Linux-target verification (HIGH-D follow-on).
 */

function pgsqlCraftSet(string $target, string $name, string $dumpBytes, ?string $shaOverride = null): string
{
    $dir = $target.DIRECTORY_SEPARATOR.$name;
    mkdir($dir, 0750, true);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'database.dump', $dumpBytes);

    $manifest = [
        'name' => $name,
        'created_at' => '2026-09-27T00:00:00+00:00',
        'driver' => 'pgsql',
        'tool' => 'pg_dump/(test)',
        'database' => ['file' => 'database.dump', 'format' => 'pgdump-custom', 'sha256' => $shaOverride ?? hash('sha256', $dumpBytes)],
        'media' => ['files' => [], 'root' => hash('sha256', '[]')],
        'migration_inventory_sha256' => hash_file('sha256', base_path('deploy/migrations-inventory.json')),
        'status' => 'ok',
    ];
    $manifest['root'] = hash('sha256', (string) json_encode($manifest));
    file_put_contents($dir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest));

    return $dir;
}

it('accepts the pgsql driver and fails loudly on missing pg_dump tooling', function (): void {
    $original = config('backup.pg_dump');
    config()->set('backup.pg_dump', 'rtftt-nonexistent-pg-dump-binary');

    try {
        $result = BackupManager::forMediaDisk()->run('pgsql');
    } finally {
        config()->set('backup.pg_dump', $original);
    }

    expect($result['ok'])->toBeFalse()
        ->and($result['set'])->toBeNull()
        ->and($result['manifest'])->toBeNull()
        ->and(implode(' ', $result['errors']))->toContain('RTFTT_PG_DUMP_PATH');
});

it('refuses pgsql when the connection has no database name', function (): void {
    $originalDb = config('database.connections.pgsql.database');
    config()->set('database.connections.pgsql.database', '');

    try {
        $result = BackupManager::forMediaDisk()->run('pgsql');
    } finally {
        config()->set('database.connections.pgsql.database', $originalDb);
    }

    expect($result['ok'])->toBeFalse()
        ->and($result['set'])->toBeNull()
        ->and(implode(' ', $result['errors']))->toContain('DB_DATABASE');
});

it('probes pg_dump availability without credentials', function (): void {
    $manager = BackupManager::forMediaDisk();

    expect($manager->pgDumpToolVersion('rtftt-nonexistent-pg-dump-binary'))->toBeNull();
});

it('builds a pg_dump custom-format command with secrets only in env', function (): void {
    $manager = BackupManager::forMediaDisk();
    $connection = ['host' => 'db.internal', 'port' => '5432', 'database' => 'rtftt', 'username' => 'rtftt_app', 'password' => 'hunter2-should-never-appear'];

    $command = $manager->buildPgDumpCommand('/usr/bin/pg_dump', $connection, '/sets/x/database.dump');
    $flat = implode(' ', $command);

    expect($command[0])->toBe('/usr/bin/pg_dump')
        ->and($command)->toContain('-Fc')
        ->and($command)->toContain('-h')
        ->and($command)->toContain('db.internal')
        ->and($command)->toContain('-p')
        ->and($command)->toContain('-U')
        ->and($command)->toContain('rtftt_app')
        ->and($command)->toContain('rtftt')
        ->and($command)->toContain('/sets/x/database.dump')
        ->and($flat)->not->toContain('hunter2-should-never-appear');
});

it('verifies a genuine pg_dump artifact and rejects tampering', function (): void {
    Storage::fake('local');
    $target = backupTempTarget();
    $manager = new BackupManager(Storage::disk('local'));

    try {
        $dir = pgsqlCraftSet($target, '20260927-000001-pgsql', BackupManager::PG_DUMP_MAGIC.'-payload-bytes');
        expect($manager->verifySet($dir)['ok'])->toBeTrue();

        // Corrupted bytes: sha256 mismatch fails verification.
        file_put_contents($dir.DIRECTORY_SEPARATOR.'database.dump', BackupManager::PG_DUMP_MAGIC.'-tampered');
        expect($manager->verifySet($dir)['ok'])->toBeFalse();

        // Wrong magic: not a pg_dump archive.
        $dir2 = pgsqlCraftSet($target, '20260927-000002-pgsql', 'SQLITE FORMAT 3 padding..........');
        expect(implode(' ', $manager->verifySet($dir2)['errors']))->toContain('custom-format');

        // Missing artifact fails.
        unlink($dir2.DIRECTORY_SEPARATOR.'database.dump');
        expect($manager->verifySet($dir2)['ok'])->toBeFalse();
    } finally {
        removeDirectoryTree($target);
    }
});

it('prunes pgsql generations with last-good protection', function (): void {
    config()->set('backup.generations', 2);
    $target = backupTempTarget();
    $manager = new BackupManager(Storage::disk('local'));

    try {
        pgsqlCraftSet($target, '20260924-000001-old-good', BackupManager::PG_DUMP_MAGIC.'a');
        pgsqlCraftSet($target, '20260925-000002-mid-good', BackupManager::PG_DUMP_MAGIC.'b');
        pgsqlCraftSet($target, '20260926-000003-new-good', BackupManager::PG_DUMP_MAGIC.'c');

        $manager->prune($target);
        $remaining = $manager->listSets($target);

        expect($remaining)->toContain('20260926-000003-new-good')
            ->and($remaining)->toContain('20260925-000002-mid-good')
            ->and($remaining)->not->toContain('20260924-000001-old-good');
    } finally {
        removeDirectoryTree($target);
    }
});

it('refuses pre-migrate on pgsql without tooling instead of half-executing', function (): void {
    $target = backupTempTarget();
    $originalDefault = config('database.default');
    $originalBinary = config('backup.pg_dump');
    config()->set('database.default', 'pgsql');
    config()->set('backup.pg_dump', 'rtftt-nonexistent-pg-dump-binary');
    config()->set('backup.target', $target);

    try {
        $this->artisan('backup:pre-migrate')
            ->expectsOutputToContain('PRE-MIGRATE BACKUP REFUSED')
            ->assertExitCode(1);
    } finally {
        config()->set('database.default', $originalDefault);
        config()->set('backup.pg_dump', $originalBinary);
        removeDirectoryTree($target);
    }
});

it('keeps secrets out of pgsql manifests', function (): void {
    $target = backupTempTarget();
    $manager = new BackupManager(Storage::disk('local'));

    try {
        $dir = pgsqlCraftSet($target, '20260927-000009-pgsql', BackupManager::PG_DUMP_MAGIC.'-bytes');
        $manifestText = strtolower((string) file_get_contents($dir.DIRECTORY_SEPARATOR.'manifest.json'));

        expect($manager->verifySet($dir)['ok'])->toBeTrue();

        foreach (['password', 'secret', 'token', 'credential'] as $needle) {
            expect($manifestText)->not->toContain($needle);
        }
    } finally {
        removeDirectoryTree($target);
    }
});
