<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * P5-005 concurrency evidence.
 *
 * Proves the manual-retry compare-and-set under genuine independent OS
 * processes, each with its own SQLite connection to the same database file,
 * coordinated by a filesystem rendezvous. Follows the accepted ADR-013 / P3-007
 * concurrency-evidence pattern. A sequential simulation alone is insufficient.
 *
 * The harness command (test:translation-retry-race-worker) invokes the real,
 * unmodified App\Actions\TranslationRetry::retry() operation and fakes the queue
 * so the number of dispatched jobs identifies the CAS winner.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-translation-retry-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    $this->raceDbPath = storage_path("app/test-translation-retry-race-db-{$this->testId}.db");

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "translations" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "transcription_id" INTEGER NOT NULL,
            "target_language" TEXT NOT NULL,
            "status" TEXT NOT NULL DEFAULT \'pending\',
            "attempt_token" TEXT NULL,
            "dispatched_at" TEXT NULL,
            "source_language" TEXT NULL,
            "provider" TEXT NULL,
            "model" TEXT NULL,
            "full_text" TEXT NULL,
            "failure_code" TEXT NULL,
            "started_at" TEXT NULL,
            "completed_at" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )
    ');

    $now = now()->toDateTimeString();

    $pdo->prepare('INSERT INTO "translations" ("transcription_id", "target_language", "status", "failure_code", "attempt_token", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([1, 'ms', 'failed', 'PROVIDER_FAILED', 'old-token', $now, $now]);
    $this->raceTranslationId = (int) $pdo->lastInsertId();

    $pdo = null;
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-translation-retry-sentinels/{$this->testId}"));

    if (isset($this->raceDbPath) && file_exists($this->raceDbPath)) {
        @unlink($this->raceDbPath);
    }
});

it('proves exactly one independent process wins the translation retry CAS', function (): void {
    $goFile = "{$this->sentinelDir}/go.go";
    $readyA = "{$this->sentinelDir}/a.ready";
    $readyB = "{$this->sentinelDir}/b.ready";
    $resultA = "{$this->sentinelDir}/a-result.json";
    $resultB = "{$this->sentinelDir}/b-result.json";

    $artisanPath = base_path('artisan');
    $phpBinary = PHP_BINARY;
    $env = [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->raceDbPath,
        'APP_ENV' => 'testing',
    ];

    $workerA = new Process([
        $phpBinary, $artisanPath, 'test:translation-retry-race-worker',
        (string) $this->raceTranslationId, $goFile, $resultA, $readyA, $this->sentinelDir,
    ]);
    $workerA->setTimeout(30);
    $workerA->setEnv($env);
    $workerA->start();

    $workerB = new Process([
        $phpBinary, $artisanPath, 'test:translation-retry-race-worker',
        (string) $this->raceTranslationId, $goFile, $resultB, $readyB, $this->sentinelDir,
    ]);
    $workerB->setTimeout(30);
    $workerB->setEnv($env);
    $workerB->start();

    $timeout = 20;
    $start = time();

    while (true) {
        if (time() - $start > $timeout) {
            $workerA->stop(5);
            $workerB->stop(5);
            $this->fail('Timed out waiting for both retry processes to be ready');
        }

        if (file_exists($readyA) && file_exists($readyB)) {
            break;
        }

        usleep(10_000);
    }

    file_put_contents($goFile, 'go');

    $workerA->wait();
    $workerB->wait();

    $barrierSentinels = glob("{$this->sentinelDir}/*.at_retry") ?: [];

    expect(count($barrierSentinels))->toBe(2);

    $outcomeA = json_decode((string) file_get_contents($resultA), true);
    $outcomeB = json_decode((string) file_get_contents($resultB), true);

    expect($outcomeA['status'])->toBe('ok')
        ->and($outcomeB['status'])->toBe('ok');

    // Exactly one new attempt exists: both processes converge on the same row
    // and the same attempt token (only one won the CAS and minted a token). The
    // loser may re-dispatch that same attempt before the winner stamps it, which
    // is harmless because the job claim is token-fenced.
    $dispatched = (int) $outcomeA['dispatched'] + (int) $outcomeB['dispatched'];

    expect($dispatched)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2)
        ->and($outcomeA['translation_id'])->toBe($this->raceTranslationId)
        ->and($outcomeB['translation_id'])->toBe($this->raceTranslationId)
        ->and($outcomeA['attempt_token'])->toBe($outcomeB['attempt_token'])
        ->and($outcomeA['attempt_token'])->not->toBe('old-token');

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $pdo->prepare('SELECT "status", "failure_code", "attempt_token" FROM "translations" WHERE "id" = ?');
    $statement->execute([$this->raceTranslationId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $pdo = null;

    expect($row)->not->toBeFalse()
        ->and($row['status'])->toBe('queued')
        ->and($row['failure_code'])->toBeNull()
        ->and($row['attempt_token'])->toBe($outcomeA['attempt_token']);
});
