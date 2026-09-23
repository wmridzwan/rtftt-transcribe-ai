<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * P6-002 concurrency evidence.
 *
 * Proves the revision append/version-allocation race under genuine independent
 * OS processes, each with its own SQLite connection to the same durable
 * database file, coordinated by a filesystem rendezvous. A sequential
 * simulation alone is insufficient. Mirrors the accepted ADR-013 /
 * P5-004 claim-race pattern.
 *
 * The race database uses the real migration schema (foreign key enforcement
 * disabled only to avoid seeding the full user/media graph, which is
 * irrelevant to the version-allocation invariant). SQLite serializes writers
 * at the file level and cannot demonstrate production row-locking; the
 * production guarantee is the `(transcription_id, version)` unique constraint
 * plus row locking on a datastore that supports it.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    $this->raceDbPath = storage_path("app/test-revision-race-{$this->testId}.db");
    touch($this->raceDbPath);

    config(['database.connections.revision_race' => array_merge(
        config('database.connections.sqlite'),
        [
            'database' => $this->raceDbPath,
            'foreign_key_constraints' => false,
            'busy_timeout' => 15000,
        ],
    )]);

    Artisan::call('migrate', ['--database' => 'revision_race', '--force' => true]);

    $connection = DB::connection('revision_race');
    $now = now()->toDateTimeString();

    $this->transcriptionId = (int) $connection->table('transcriptions')->insertGetId([
        'user_id' => 1,
        'media_file_id' => 1,
        'title' => 'revision race',
        'status' => 'completed',
        'full_text' => 'source',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->baseRevisionId = (string) Str::uuid();

    $connection->table('transcript_revisions')->insert([
        'id' => $this->baseRevisionId,
        'transcription_id' => $this->transcriptionId,
        'version' => 1,
        'parent_revision_id' => null,
        'created_by' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $connection->table('transcriptions')
        ->where('id', $this->transcriptionId)
        ->update(['active_revision_id' => $this->baseRevisionId]);
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-sentinels/{$this->testId}"));

    DB::purge('revision_race');

    if (isset($this->raceDbPath)) {
        foreach (glob($this->raceDbPath.'*') ?: [] as $file) {
            @unlink($file);
        }
    }
});

it('proves exactly one independent process can persist a given (transcription_id, version)', function (): void {
    $goFile = "{$this->sentinelDir}/go.go";
    $readyA = "{$this->sentinelDir}/a.ready";
    $readyB = "{$this->sentinelDir}/b.ready";
    $resultA = "{$this->sentinelDir}/a-result.json";
    $resultB = "{$this->sentinelDir}/b-result.json";

    $env = [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->raceDbPath,
        'DB_FOREIGN_KEYS' => 'false',
        'DB_BUSY_TIMEOUT' => '15000',
        'APP_ENV' => 'testing',
    ];

    $command = [PHP_BINARY, base_path('artisan'), 'test:revision-append-race-worker', (string) $this->transcriptionId, $this->baseRevisionId, $goFile];

    $workerA = new Process(array_merge($command, [$resultA, $readyA, $this->sentinelDir]));
    $workerA->setTimeout(40);
    $workerA->setEnv($env);
    $workerA->start();

    $workerB = new Process(array_merge($command, [$resultB, $readyB, $this->sentinelDir]));
    $workerB->setTimeout(40);
    $workerB->setEnv($env);
    $workerB->start();

    $timeout = 25;
    $start = time();

    while (true) {
        if (time() - $start > $timeout) {
            $workerA->stop(5);
            $workerB->stop(5);
            $this->fail('Timed out waiting for both append processes to be ready');
        }

        if (file_exists($readyA) && file_exists($readyB)) {
            break;
        }

        usleep(10_000);
    }

    file_put_contents($goFile, 'go');

    $workerA->wait();
    $workerB->wait();

    expect(file_exists($resultA))->toBeTrue('Worker A did not write a result')
        ->and(file_exists($resultB))->toBeTrue('Worker B did not write a result');

    $outcomeA = json_decode((string) file_get_contents($resultA), true);
    $outcomeB = json_decode((string) file_get_contents($resultB), true);

    expect($outcomeA['status'])->not->toBeIn(['error', 'timeout'])
        ->and($outcomeB['status'])->not->toBeIn(['error', 'timeout']);

    $successes = array_values(array_filter([$outcomeA, $outcomeB], fn (array $o): bool => $o['status'] === 'success'));
    $conflicts = array_values(array_filter([$outcomeA, $outcomeB], fn (array $o): bool => $o['status'] === 'conflict'));

    expect($successes)->toHaveCount(1)
        ->and($conflicts)->toHaveCount(1)
        ->and($successes[0]['version'])->toBe(2);

    $connection = DB::connection('revision_race');

    $versions = $connection->table('transcript_revisions')
        ->where('transcription_id', $this->transcriptionId)
        ->orderBy('version')
        ->pluck('version')
        ->all();

    expect($versions)->toBe([1, 2])
        ->and(count($versions))->toBe(count(array_unique($versions)));

    $active = $connection->table('transcriptions')
        ->where('id', $this->transcriptionId)
        ->value('active_revision_id');

    expect($active)->toBe($successes[0]['revisionId']);
});
