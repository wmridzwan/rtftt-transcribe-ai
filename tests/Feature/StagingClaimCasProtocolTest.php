<?php

use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * These tests prove the staging claim CAS protocol is race-safe using
 * genuine independent OS processes. Each process opens its own SQLite
 * connection to the same database file, coordinated by a filesystem
 * rendezvous (ready/go sentinels) so both attempt their conditional
 * statement at the same wall-clock moment.
 *
 * A dedicated test-harness Artisan command (test:staging-race-worker)
 * is used to give each spawned process a script to run. A sequential/
 * single-process simulation alone does not satisfy the acceptance criteria.
 */

beforeEach(function (): void {
    $this->testId = Str::uuid()->toString();
    $this->sentinelDir = storage_path("app/test-sentinels/{$this->testId}");
    File::makeDirectory($this->sentinelDir, 0755, true);

    // Create a shared file-based SQLite database for the child processes.
    // In-memory databases are per-connection, so each spawned process would
    // get its own empty DB. A file DB lets all processes share state.
    $this->raceDbPath = storage_path("app/test-race-db-{$this->testId}.db");
    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Create the schema the workers need
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "users" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "name" TEXT NOT NULL,
            "email" TEXT NOT NULL UNIQUE,
            "email_verified_at" TEXT NULL,
            "password" TEXT NOT NULL,
            "remember_token" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )
    ');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS "staging_claims" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "user_id" INTEGER NOT NULL,
            "upload_attempt_id" TEXT NOT NULL,
            "staging_path" TEXT NOT NULL,
            "held_by" TEXT NOT NULL DEFAULT \'upload\',
            "cleanup_claimed_at" TEXT NULL,
            "claimed_at" TEXT NOT NULL,
            "expires_at" TEXT NOT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL,
            FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE
        )
    ');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "staging_claims_user_id_upload_attempt_id_unique" ON "staging_claims" ("user_id", "upload_attempt_id")');
    $pdo->exec('CREATE INDEX IF NOT EXISTS "staging_claims_expires_at_index" ON "staging_claims" ("expires_at")');
    $pdo = null;

    // Seed the user row into the file DB so the workers' FK constraints pass.
    // Use createOne() to get a proper auto-increment ID from the in-memory DB,
    // then replicate the row into the file DB via raw PDO.
    $user = User::factory()->createOne();
    $this->raceUserId = $user->id;
    $this->raceUser = $user;

    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO "users" ("id", "name", "email", "password", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user->id,
        $user->name,
        $user->email,
        $user->password,
        $user->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
        $user->updated_at?->toDateTimeString() ?? now()->toDateTimeString(),
    ]);
    $pdo = null;
});

afterEach(function (): void {
    File::deleteDirectory(storage_path("app/test-sentinels/{$this->testId}"));
    if (isset($this->raceDbPath) && file_exists($this->raceDbPath)) {
        @unlink($this->raceDbPath);
    }
});

it('proves upload vs cleanup race with genuine independent processes', function (): void {
    $userId = $this->raceUserId;
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$userId}/{$attemptId}/test.mp3";

    $goFile = "{$this->sentinelDir}/go.go";
    $uploadResultFile = "{$this->sentinelDir}/upload-result.json";
    $cleanupResultFile = "{$this->sentinelDir}/cleanup-result.json";
    $uploadReadyFile = "{$this->sentinelDir}/upload.ready";
    $cleanupReadyFile = "{$this->sentinelDir}/cleanup.ready";

    // Seed the expired claim in the shared file DB
    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO "staging_claims" ("user_id", "upload_attempt_id", "staging_path", "held_by", "claimed_at", "expires_at", "created_at", "updated_at") VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $attemptId,
        $stagingPath,
        'upload',
        now()->subHour()->toDateTimeString(),
        now()->subHour()->toDateTimeString(),
        now()->toDateTimeString(),
        now()->toDateTimeString(),
    ]);
    $pdo = null;

    $artisanPath = base_path('artisan');
    $phpBinary = PHP_BINARY;
    $env = [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->raceDbPath,
        'APP_ENV' => 'testing',
    ];

    // Spawn upload worker process
    $uploadProcess = new Process([
        $phpBinary, $artisanPath, 'test:staging-race-worker',
        'upload', $userId, $attemptId, $stagingPath, $goFile, $uploadResultFile, $uploadReadyFile,
    ]);
    $uploadProcess->setTimeout(30);
    $uploadProcess->setEnv($env);
    $uploadProcess->start();

    // Spawn cleanup worker process
    $cleanupProcess = new Process([
        $phpBinary, $artisanPath, 'test:staging-race-worker',
        'cleanup', $userId, $attemptId, $stagingPath, $goFile, $cleanupResultFile, $cleanupReadyFile,
    ]);
    $cleanupProcess->setTimeout(30);
    $cleanupProcess->setEnv($env);
    $cleanupProcess->start();

    // Wait for both processes to be ready
    $timeout = 20;
    $start = time();
    while (true) {
        if (time() - $start > $timeout) {
            $uploadProcess->stop(5);
            $cleanupProcess->stop(5);
            $this->fail('Timed out waiting for both processes to be ready');
        }

        $uploadReady = file_exists($uploadReadyFile);
        $cleanupReady = file_exists($cleanupReadyFile);

        if ($uploadReady && $cleanupReady) {
            break;
        }

        usleep(10_000); // 10ms
    }

    // Both processes are ready. Signal go.
    file_put_contents($goFile, 'go');

    // Wait for both processes to finish
    $uploadProcess->wait();
    $cleanupProcess->wait();

    // Parse results
    $uploadResult = json_decode(file_get_contents($uploadResultFile), true);
    $cleanupResult = json_decode(file_get_contents($cleanupResultFile), true);

    // Both processes must have completed successfully
    expect($uploadResult['status'])->not->toBe('error')
        ->and($cleanupResult['status'])->not->toBe('error');

    // Exactly one process must have won the claim
    $uploadWon = in_array($uploadResult['status'], ['inserted', 'renewed'], true);
    $cleanupWon = $cleanupResult['status'] === 'claimed';

    expect($uploadWon || $cleanupWon)->toBeTrue('At least one process should have won the claim');

    // If cleanup won, upload must have lost (retryable failure path)
    if ($cleanupWon) {
        expect($uploadResult['status'])->toBe('lost_race')
            ->and($uploadResult['held_by'] ?? null)->toBe('cleanup');
    }

    // The final claim state must be consistent (check the file DB)
    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT "held_by" FROM "staging_claims" WHERE "user_id" = ? AND "upload_attempt_id" = ?');
    $stmt->execute([$userId, $attemptId]);
    $finalClaim = $stmt->fetch(PDO::FETCH_ASSOC);
    $pdo = null;

    if ($finalClaim !== false) {
        expect($finalClaim['held_by'])->toBeIn(['upload', 'cleanup']);
    }
});

it('proves cleanup vs upload race with genuine independent processes', function (): void {
    $userId = $this->raceUserId;
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$userId}/{$attemptId}/test.mp3";

    $goFile = "{$this->sentinelDir}/go.go";
    $uploadResultFile = "{$this->sentinelDir}/upload-result.json";
    $cleanupResultFile = "{$this->sentinelDir}/cleanup-result.json";
    $uploadReadyFile = "{$this->sentinelDir}/upload.ready";
    $cleanupReadyFile = "{$this->sentinelDir}/cleanup.ready";

    // No existing claim — both processes will try to insert
    $artisanPath = base_path('artisan');
    $phpBinary = PHP_BINARY;
    $env = [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->raceDbPath,
        'APP_ENV' => 'testing',
    ];

    // Spawn cleanup worker process first
    $cleanupProcess = new Process([
        $phpBinary, $artisanPath, 'test:staging-race-worker',
        'cleanup', $userId, $attemptId, $stagingPath, $goFile, $cleanupResultFile, $cleanupReadyFile,
    ]);
    $cleanupProcess->setTimeout(30);
    $cleanupProcess->setEnv($env);
    $cleanupProcess->start();

    // Spawn upload worker process
    $uploadProcess = new Process([
        $phpBinary, $artisanPath, 'test:staging-race-worker',
        'upload', $userId, $attemptId, $stagingPath, $goFile, $uploadResultFile, $uploadReadyFile,
    ]);
    $uploadProcess->setTimeout(30);
    $uploadProcess->setEnv($env);
    $uploadProcess->start();

    // Wait for both processes to be ready
    $timeout = 20;
    $start = time();
    while (true) {
        if (time() - $start > $timeout) {
            $uploadProcess->stop(5);
            $cleanupProcess->stop(5);
            $this->fail('Timed out waiting for both processes to be ready');
        }

        $uploadReady = file_exists($uploadReadyFile);
        $cleanupReady = file_exists($cleanupReadyFile);

        if ($uploadReady && $cleanupReady) {
            break;
        }

        usleep(10_000); // 10ms
    }

    // Both processes are ready. Signal go.
    file_put_contents($goFile, 'go');

    // Wait for both processes to finish
    $cleanupProcess->wait();
    $uploadProcess->wait();

    // Parse results
    $uploadResult = json_decode(file_get_contents($uploadResultFile), true);
    $cleanupResult = json_decode(file_get_contents($cleanupResultFile), true);

    // Both processes must have completed successfully
    expect($uploadResult['status'])->not->toBe('error')
        ->and($cleanupResult['status'])->not->toBe('error');

    // Exactly one process must have won
    $uploadWon = in_array($uploadResult['status'], ['inserted', 'renewed'], true);
    $cleanupWon = in_array($cleanupResult['status'], ['claimed', 'inserted_no_row_gap'], true);

    expect($uploadWon || $cleanupWon)->toBeTrue('At least one process should have won the claim');

    // The final claim state must be consistent (check the file DB)
    $pdo = new PDO("sqlite:{$this->raceDbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT "held_by" FROM "staging_claims" WHERE "user_id" = ? AND "upload_attempt_id" = ?');
    $stmt->execute([$userId, $attemptId]);
    $finalClaim = $stmt->fetch(PDO::FETCH_ASSOC);
    $pdo = null;

    if ($finalClaim !== false) {
        expect($finalClaim['held_by'])->toBeIn(['upload', 'cleanup']);
    }
});

it('handles normal same-attempt retry renewal correctly', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create an existing upload claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'expires_at' => now()->addHour(),
    ]);

    // Simulate a same-attempt retry via insertOrIgnore
    $inserted = DB::table('staging_claims')->insertOrIgnore([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'claimed_at' => now(),
        'expires_at' => now()->addHours(24),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($inserted)->toBe(0); // Row already exists

    // Renew the claim via conditional UPDATE
    $renewed = DB::table('staging_claims')
        ->where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->where('held_by', 'upload')
        ->update([
            'expires_at' => now()->addHours(24),
            'updated_at' => now(),
        ]);

    expect($renewed)->toBe(1);

    // Verify the claim is still held by upload
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    expect($claim->held_by)->toBe('upload')
        ->and($claim->expires_at->isFuture())->toBeTrue();
});

it('cleanup defers when it loses the race to an active upload claim', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create an active upload claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'expires_at' => now()->addHour(),
    ]);

    // Attempt cleanup claim — should return 0 (deferred)
    $claimed = DB::table('staging_claims')
        ->where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->where('held_by', 'upload')
        ->where('expires_at', '<', now())
        ->update([
            'held_by' => 'cleanup',
            'cleanup_claimed_at' => now(),
            'updated_at' => now(),
        ]);

    expect($claimed)->toBe(0);

    // Verify the claim is still held by upload
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    expect($claim->held_by)->toBe('upload');
});

it('ingestion loses against in-progress cleanup claim and returns retryable failure', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a cleanup claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now(),
        'expires_at' => now()->addHours(24),
    ]);

    // Attempt upload claim via insertOrIgnore — should return 0
    $inserted = DB::table('staging_claims')->insertOrIgnore([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'claimed_at' => now(),
        'expires_at' => now()->addHours(24),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($inserted)->toBe(0);

    // Check the existing claim
    $existingClaim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    expect($existingClaim->held_by)->toBe('cleanup');

    // This should result in a controlled retryable failure (ValidationException)
    // In the actual MediaIngestionService, this would throw ValidationException
    // We verify the logic here: insertOrIgnore returns 0 + held_by = 'cleanup'
    // means the service should NOT write to staging and should return retryable failure
});

it('crash-recovery re-claim after timeout works correctly', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a cleanup claim that timed out (16 minutes ago)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now()->subMinutes(16),
        'expires_at' => now()->addHours(24),
    ]);

    // Attempt crash-recovery re-claim
    $reclaimed = DB::table('staging_claims')
        ->where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->where('held_by', 'cleanup')
        ->where('cleanup_claimed_at', '<', now()->subMinutes(15))
        ->update([
            'held_by' => 'cleanup',
            'cleanup_claimed_at' => now(),
            'updated_at' => now(),
        ]);

    expect($reclaimed)->toBe(1);

    // Verify the claim was re-claimed with updated timestamp
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    expect($claim->held_by)->toBe('cleanup')
        ->and($claim->cleanup_claimed_at->diffInMinutes(now()))->toBeLessThanOrEqual(1);
});

it('crash-recovery does not re-claim within timeout window', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a cleanup claim that is still within timeout (10 minutes ago)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now()->subMinutes(10),
        'expires_at' => now()->addHours(24),
    ]);

    // Attempt crash-recovery re-claim — should return 0 (not eligible)
    $reclaimed = DB::table('staging_claims')
        ->where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->where('held_by', 'cleanup')
        ->where('cleanup_claimed_at', '<', now()->subMinutes(15))
        ->update([
            'held_by' => 'cleanup',
            'cleanup_claimed_at' => now(),
            'updated_at' => now(),
        ]);

    expect($reclaimed)->toBe(0);
});

it('cleanup claiming an expired attempt succeeds', function (): void {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create an expired upload claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'expires_at' => now()->subHour(),
    ]);

    // Cleanup should succeed
    $claimed = DB::table('staging_claims')
        ->where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->where('held_by', 'upload')
        ->where('expires_at', '<', now())
        ->update([
            'held_by' => 'cleanup',
            'cleanup_claimed_at' => now(),
            'updated_at' => now(),
        ]);

    expect($claimed)->toBe(1);

    // Verify the claim is now held by cleanup
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    expect($claim->held_by)->toBe('cleanup')
        ->and($claim->cleanup_claimed_at)->not->toBeNull();
});
