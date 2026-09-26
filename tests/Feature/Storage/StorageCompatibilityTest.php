<?php

use App\Models\MediaFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
 * P7-004: compatibility with the Wave 2 surfaces this task must consume
 * without redefining — P7-006 scan/quarantine path, P7-007 manifest
 * semantics, private-disk boundary. No behavior change, only proof.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('keeps the media disk local and private', function (): void {
    $disk = (string) config('media.storage_disk');

    expect(config("filesystems.disks.{$disk}.driver"))->toBe('local');

    MediaFile::assertPrivateStorageDisk();

    expect(true)->toBeTrue();
});

it('keeps quarantine content out of the backup manifest', function (): void {
    $disk = Storage::disk(config('media.storage_disk'));
    $disk->put('media/keep/file.mp3', 'bytes');
    $disk->put('quarantine/infected.bin', 'malware');

    // Isolated file-sqlite source (the manager snapshots files, never
    // the :memory: test connection) + isolated target; config restored
    // afterwards. Mirrors the P7-007 isolation pattern without
    // redeclaring its helpers.
    $db = tempnam(sys_get_temp_dir(), 'p7004-db-').'.sqlite';
    $pdo = new PDO('sqlite:'.$db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE migrations (migration VARCHAR(255), batch INT)');
    $pdo->exec("INSERT INTO migrations VALUES ('2026_01_01_000001_probe.php', 1)");
    unset($pdo);

    $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7004-compat-'.uniqid();
    mkdir($target, 0750, true);

    $originalDb = config('database.connections.sqlite.database');
    $originalTarget = config('backup.target');
    config()->set('database.connections.sqlite.database', $db);
    config()->set('backup.target', $target);

    try {
        $exit = Artisan::call('backup:run', ['--driver' => 'sqlite']);
    } finally {
        config()->set('database.connections.sqlite.database', $originalDb);
        config()->set('backup.target', $originalTarget);
    }

    expect($exit)->toBe(0);

    $leaked = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && str_contains($file->getPathname(), 'infected.bin')) {
            $leaked[] = $file->getPathname();
        }
    }

    expect($leaked)->toBe([]);

    @unlink($db);
});

it('reports storage topology through deployment:verify without breaking P7-008 checks', function (): void {
    $exit = Artisan::call('deployment:verify');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Topology:');
});

it('validates the production topology through its own command', function (): void {
    $exit = Artisan::call('storage:validate-topology');

    expect($exit)->toBeInt()
        ->and(Artisan::output())->toContain('Topology [driver]: local');
});
