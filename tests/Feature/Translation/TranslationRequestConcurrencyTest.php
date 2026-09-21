<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * P5-004B concurrency evidence.
 *
 * Proves that concurrent FIRST requests for the same (transcription, target)
 * converge on a single row under genuine independent OS processes, each with
 * its own SQLite connection to the same database file, coordinated by a
 * filesystem rendezvous. The race table carries the real partial unique index
 * (translations_active_target_unique), so a losing insert hits the production
 * constraint. Follows the accepted ADR-013 / P3-007 concurrency-evidence pattern.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-translation-request-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    $this->raceDbPath = storage_path("app/test-translation-request-race-db-{$this->testId}.db");

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('
        CREATE TABLE "transcriptions" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "status" TEXT NOT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE "translations" (
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

    $pdo->exec("CREATE UNIQUE INDEX translations_active_target_unique ON translations (transcription_id, target_language) WHERE status IN ('pending', 'queued', 'translating', 'completed')");

    $now = now()->toDateTimeString();
    $pdo->prepare('INSERT INTO "transcriptions" ("status", "created_at", "updated_at") VALUES (?, ?, ?)')
        ->execute(['completed', $now, $now]);
    $this->raceTranscriptionId = (int) $pdo->lastInsertId();

    $pdo = null;
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-translation-request-sentinels/{$this->testId}"));

    if (isset($this->raceDbPath) && file_exists($this->raceDbPath)) {
        @unlink($this->raceDbPath);
    }
});

it('proves concurrent first requests in independent processes converge on one translation', function (): void {
    $goFile = "{$this->sentinelDir}/go.go";
    $env = [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->raceDbPath,
        'APP_ENV' => 'testing',
    ];

    $workers = [];

    foreach (['a', 'b'] as $name) {
        $process = new Process([
            PHP_BINARY, base_path('artisan'), 'test:translation-request-race-worker',
            (string) $this->raceTranscriptionId, $goFile, "{$this->sentinelDir}/{$name}-result.json", "{$this->sentinelDir}/{$name}.ready", $this->sentinelDir,
        ]);
        $process->setTimeout(60);
        $process->setEnv($env);
        $process->start();
        $workers[$name] = $process;
    }

    $start = time();

    while (! (file_exists("{$this->sentinelDir}/a.ready") && file_exists("{$this->sentinelDir}/b.ready"))) {
        if (time() - $start > 20) {
            foreach ($workers as $process) {
                $process->stop(5);
            }

            $this->fail('Timed out waiting for both request processes to be ready');
        }

        usleep(10_000);
    }

    file_put_contents($goFile, 'go');

    foreach ($workers as $process) {
        $process->wait();
    }

    expect(count(glob("{$this->sentinelDir}/*.at_request") ?: []))->toBe(2);

    $outcomeA = json_decode((string) file_get_contents("{$this->sentinelDir}/a-result.json"), true);
    $outcomeB = json_decode((string) file_get_contents("{$this->sentinelDir}/b-result.json"), true);

    // No raw constraint/lock exception escapes to either caller.
    expect($outcomeA['status'])->toBe('ok', json_encode($outcomeA))
        ->and($outcomeB['status'])->toBe('ok', json_encode($outcomeB))
        ->and($outcomeA['translation_id'])->toBe($outcomeB['translation_id']);

    // The winner dispatches; the loser may re-dispatch the same attempt before
    // the winner stamps it (harmless: the claim is token-fenced), never more.
    $dispatched = (int) $outcomeA['dispatched'] + (int) $outcomeB['dispatched'];
    expect($dispatched)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rows = $pdo->query('SELECT "id", "status", "attempt_token", "dispatched_at" FROM "translations"')->fetchAll(PDO::FETCH_ASSOC);
    $pdo = null;

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toEqual($outcomeA['translation_id'])
        ->and($rows[0]['status'])->toBe('queued')
        ->and($rows[0]['attempt_token'])->not->toBeNull()
        ->and($rows[0]['dispatched_at'])->not->toBeNull();
});
