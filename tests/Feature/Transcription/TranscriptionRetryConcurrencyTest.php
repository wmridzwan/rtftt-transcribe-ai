<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * P3-007 concurrency evidence.
 *
 * Proves the manual-retry compare-and-set under genuine independent OS
 * processes, each with its own SQLite connection to the same database file,
 * coordinated by a filesystem rendezvous so both attempt the guarded CAS at the
 * same wall-clock moment. This follows the accepted ADR-013 / ADR-016 /
 * P2-004A2 / P3-006 concurrency-evidence pattern. A sequential/single-process
 * simulation alone does not satisfy the acceptance criteria.
 *
 * The harness command (test:transcription-retry-race-worker) invokes the real,
 * unmodified App\Actions\TranscriptionRetry::retry() operation.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-retry-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    // In-memory databases are per-connection, so the child processes need a
    // shared file-backed SQLite database.
    $this->raceDbPath = storage_path("app/test-retry-race-db-{$this->testId}.db");

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "media_files" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "user_id" INTEGER NOT NULL,
            "uuid" TEXT NOT NULL,
            "original_filename" TEXT NOT NULL,
            "storage_filename" TEXT NOT NULL,
            "storage_path" TEXT NOT NULL,
            "media_type" TEXT NOT NULL,
            "mime_type" TEXT NOT NULL,
            "extension" TEXT NOT NULL,
            "file_size_bytes" INTEGER NOT NULL,
            "duration_seconds" INTEGER NULL,
            "audio_codec" TEXT NULL,
            "video_codec" TEXT NULL,
            "sample_rate" INTEGER NULL,
            "channels" INTEGER NULL,
            "status" TEXT NOT NULL DEFAULT \'uploaded\',
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )
    ');

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
            "failure_code" TEXT NULL,
            "logs" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL,
            FOREIGN KEY ("transcription_id") REFERENCES "transcriptions"("id") ON DELETE CASCADE
        )
    ');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "processing_jobs_job_uuid_unique" ON "processing_jobs" ("job_uuid")');
    $pdo->exec('CREATE INDEX IF NOT EXISTS "processing_jobs_transcription_id_stage_index" ON "processing_jobs" ("transcription_id", "stage")');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "processing_jobs_active_attempt_unique" ON "processing_jobs" ("transcription_id") WHERE "status" IN (\'queued\', \'running\')');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "jobs" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "queue" TEXT NOT NULL,
            "payload" TEXT NOT NULL,
            "attempts" INTEGER NOT NULL DEFAULT 0,
            "reserved_at" INTEGER NULL,
            "available_at" INTEGER NOT NULL,
            "created_at" INTEGER NOT NULL
        )
    ');

    $now = now()->toDateTimeString();

    $pdo->prepare('INSERT INTO "media_files" ("user_id", "uuid", "original_filename", "storage_filename", "storage_path", "media_type", "mime_type", "extension", "file_size_bytes", "status", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([1, (string) Str::uuid(), 'race.mp3', 'race.mp3', 'media/race.mp3', 'audio', 'audio/mpeg', 'mp3', 1024, 'uploaded', $now, $now]);
    $mediaId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO "transcriptions" ("user_id", "media_file_id", "title", "status", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([1, $mediaId, 'Retry race transcription', 'failed', $now, $now]);
    $this->raceTranscriptionId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO "processing_jobs" ("transcription_id", "job_uuid", "stage", "status", "progress_percentage", "failure_code", "completed_at", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$this->raceTranscriptionId, (string) Str::uuid(), 'transcribe', 'failed', 0, 'WORKER_TIMEOUT', $now, $now, $now]);
    $this->failedAttemptId = (int) $pdo->lastInsertId();

    $pdo = null;
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-retry-sentinels/{$this->testId}"));

    if (isset($this->raceDbPath) && file_exists($this->raceDbPath)) {
        @unlink($this->raceDbPath);
    }
});

it('proves exactly one independent process wins the retry CAS and creates exactly one active attempt', function (): void {
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
        'QUEUE_CONNECTION' => 'database',
        'RTFTT_TRANSCRIPTION_QUEUE_CONNECTION' => 'database',
    ];

    $workerA = new Process([
        $phpBinary, $artisanPath, 'test:transcription-retry-race-worker',
        (string) $this->raceTranscriptionId, $goFile, $resultA, $readyA, $this->sentinelDir,
    ]);
    $workerA->setTimeout(30);
    $workerA->setEnv($env);
    $workerA->start();

    $workerB = new Process([
        $phpBinary, $artisanPath, 'test:transcription-retry-race-worker',
        (string) $this->raceTranscriptionId, $goFile, $resultB, $readyB, $this->sentinelDir,
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

        usleep(10_000); // 10ms
    }

    file_put_contents($goFile, 'go');

    $workerA->wait();
    $workerB->wait();

    $barrierSentinels = glob("{$this->sentinelDir}/*.at_retry") ?: [];

    expect(count($barrierSentinels))->toBe(2);

    expect(file_exists($resultA))->toBeTrue('Worker A did not write a result')
        ->and(file_exists($resultB))->toBeTrue('Worker B did not write a result');

    $outcomeA = json_decode((string) file_get_contents($resultA), true);
    $outcomeB = json_decode((string) file_get_contents($resultB), true);

    // Neither independent process may have faulted; both must return the same
    // authoritative active attempt.
    expect($outcomeA['status'])->not->toBeIn(['error', 'timeout'])
        ->and($outcomeB['status'])->not->toBeIn(['error', 'timeout'])
        ->and($outcomeA['attempt_id'])->toBe($outcomeB['attempt_id'])
        ->and($outcomeA['attempt_id'])->not->toBeNull();

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total = (int) $pdo->query("SELECT COUNT(*) FROM processing_jobs WHERE transcription_id = {$this->raceTranscriptionId}")->fetchColumn();
    $active = (int) $pdo->query("SELECT COUNT(*) FROM processing_jobs WHERE transcription_id = {$this->raceTranscriptionId} AND status IN ('queued','running')")->fetchColumn();
    $oldStatus = (string) $pdo->query("SELECT status FROM processing_jobs WHERE id = {$this->failedAttemptId}")->fetchColumn();
    $dispatched = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $transcriptionStatus = (string) $pdo->query("SELECT status FROM transcriptions WHERE id = {$this->raceTranscriptionId}")->fetchColumn();

    $pdo = null;

    // Exactly one new attempt was created (the failed historical attempt plus
    // exactly one new active attempt), exactly one dispatch authority exists,
    // the previous failed attempt is unchanged, and the transcription was
    // re-opened.
    expect($total)->toBe(2)
        ->and($active)->toBe(1)
        ->and($oldStatus)->toBe('failed')
        ->and($dispatched)->toBe(1)
        ->and($transcriptionStatus)->toBe('queued');
});
