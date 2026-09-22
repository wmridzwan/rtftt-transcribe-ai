<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * P3-006 concurrency evidence.
 *
 * These tests prove the processing-attempt claim protocol under genuine
 * independent OS processes, each with its own SQLite connection to the same
 * database file, coordinated by a filesystem rendezvous (ready/go sentinels)
 * so both attempt the guarded claim at the same wall-clock moment. This
 * follows the repository's accepted ADR-013 / P2-004A2 concurrency-evidence
 * pattern. A sequential/single-process simulation alone does not satisfy the
 * acceptance criteria.
 *
 * The dedicated test harness command (test:transcription-claim-race-worker)
 * invokes the real, unmodified ProcessTranscription::claimAttempt() method.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    // In-memory databases are per-connection, so the child processes need a
    // shared file-backed SQLite database.
    $this->raceDbPath = storage_path("app/test-race-db-{$this->testId}.db");

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "transcriptions" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "user_id" INTEGER NOT NULL,
            "media_file_id" INTEGER NOT NULL,
            "title" TEXT NOT NULL,
            "language" TEXT NULL,
            "detected_language" TEXT NULL,
            "speech_detected" INTEGER NULL,
            "model" TEXT NULL,
            "status" TEXT NOT NULL DEFAULT \'draft\',
            "full_text" TEXT NULL,
            "started_at" TEXT NULL,
            "completed_at" TEXT NULL,
            "processing_seconds" INTEGER NULL,
            "error_message" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "processing_jobs" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "transcription_id" INTEGER NOT NULL,
            "job_uuid" TEXT NOT NULL,
            "worker_name" TEXT NULL,
            "stage" TEXT NOT NULL,
            "status" TEXT NOT NULL DEFAULT \'queued\',
            "progress_percentage" INTEGER NOT NULL DEFAULT 0,
            "started_at" TEXT NULL,
            "completed_at" TEXT NULL,
            "processing_seconds" INTEGER NULL,
            "error_message" TEXT NULL,
            "logs" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL,
            FOREIGN KEY ("transcription_id") REFERENCES "transcriptions"("id") ON DELETE CASCADE
        )
    ');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "processing_jobs_job_uuid_unique" ON "processing_jobs" ("job_uuid")');
    $pdo->exec('CREATE INDEX IF NOT EXISTS "processing_jobs_transcription_id_stage_index" ON "processing_jobs" ("transcription_id", "stage")');

    $now = now()->toDateTimeString();

    $pdo->prepare('INSERT INTO "transcriptions" ("user_id", "media_file_id", "title", "status", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([1, 1, 'Race transcription', 'queued', $now, $now]);
    $this->raceTranscriptionId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO "processing_jobs" ("transcription_id", "job_uuid", "stage", "status", "progress_percentage", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$this->raceTranscriptionId, (string) Str::uuid(), 'transcribe', 'queued', 0, $now, $now]);
    $this->raceAttemptId = (int) $pdo->lastInsertId();

    $pdo = null;
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-sentinels/{$this->testId}"));

    if (isset($this->raceDbPath) && file_exists($this->raceDbPath)) {
        @unlink($this->raceDbPath);
    }
});

it('proves exactly one independent process can claim the same queued processing attempt', function (): void {
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
        $phpBinary, $artisanPath, 'test:transcription-claim-race-worker',
        (string) $this->raceAttemptId, $goFile, $resultA, $readyA, $this->sentinelDir,
    ]);
    $workerA->setTimeout(30);
    $workerA->setEnv($env);
    $workerA->start();

    $workerB = new Process([
        $phpBinary, $artisanPath, 'test:transcription-claim-race-worker',
        (string) $this->raceAttemptId, $goFile, $resultB, $readyB, $this->sentinelDir,
    ]);
    $workerB->setTimeout(30);
    $workerB->setEnv($env);
    $workerB->start();

    // Wait until both independent processes are ready to fire.
    $timeout = 20;
    $start = time();

    while (true) {
        if (time() - $start > $timeout) {
            $workerA->stop(5);
            $workerB->stop(5);
            $this->fail('Timed out waiting for both claim processes to be ready');
        }

        if (file_exists($readyA) && file_exists($readyB)) {
            break;
        }

        usleep(10_000); // 10ms
    }

    file_put_contents($goFile, 'go');

    $workerA->wait();
    $workerB->wait();

    // Both independent processes must have reached the pre-claim barrier.
    // Two distinct PID-named sentinels prove both were in-flight at the claim
    // point simultaneously, i.e. a genuine concurrent race rather than one
    // process finishing before the other started.
    $barrierSentinels = glob("{$this->sentinelDir}/*.at_claim") ?: [];

    expect(count($barrierSentinels))->toBe(2);

    expect(file_exists($resultA))->toBeTrue('Worker A did not write a result')
        ->and(file_exists($resultB))->toBeTrue('Worker B did not write a result');

    $outcomeA = json_decode((string) file_get_contents($resultA), true);
    $outcomeB = json_decode((string) file_get_contents($resultB), true);

    // Neither independent process may have faulted.
    expect($outcomeA['status'])->not->toBeIn(['error', 'timeout'])
        ->and($outcomeB['status'])->not->toBeIn(['error', 'timeout']);

    $successfulClaims = (int) $outcomeA['claimed'] + (int) $outcomeB['claimed'];
    $failedClaims = 2 - $successfulClaims;

    // Exactly one claimant must report successful ownership of the attempt.
    expect($successfulClaims)->toBe(1)
        ->and($failedClaims)->toBe(1);

    // And the winner must be consistent with the persisted attempt state.
    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $pdo->prepare('SELECT "status", "started_at" FROM "processing_jobs" WHERE "id" = ?');
    $statement->execute([$this->raceAttemptId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $pdo = null;

    expect($row)->not->toBeFalse()
        ->and($row['status'])->toBe('running')
        ->and($row['started_at'])->not->toBeNull();
});
