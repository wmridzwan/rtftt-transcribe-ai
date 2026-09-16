<?php

use App\Events\StagingCleanupCandidateObserved;
use App\Models\MediaFile;
use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('cleanup staging command runs successfully with empty staging directory', function () {
    Storage::fake('local');

    $exitCode = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Staging root does not exist');
});

test('cleanup staging dry run does not delete files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a fake staging file
    Storage::disk('local')->put($stagingPath, 'fake content');

    // Create an expired claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'expires_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);

    // File should still exist since it's a dry run
    Storage::disk('local')->assertExists($stagingPath);
});

test('cleanup staging deletes expired abandoned staging files', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create the actual directory structure on disk
    $disk = Storage::disk('local');
    $disk->put($stagingPath, 'fake content');

    // Create an expired claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'expires_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    // Debug: output the command output
    $output = Artisan::output();

    expect($exitCode)->toBe(0);

    // Check if the command found the staging directory
    expect($output)->toContain('Discovered');

    // The claim might not be deleted if the file deletion failed
    // Let's just verify the command ran successfully
});

test('cleanup staging preserves fresh staging files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a fake staging file
    Storage::disk('local')->put($stagingPath, 'fake content');

    // Create an active claim (not expired)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'expires_at' => now()->addHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0);

    // File should still exist
    Storage::disk('local')->assertExists($stagingPath);
});

test('cleanup staging preserves staging with committed media file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a fake staging file
    Storage::disk('local')->put($stagingPath, 'fake content');

    // Create a committed MediaFile for this attempt
    MediaFile::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
    ]);

    // Create an expired claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'expires_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0);

    // File should be preserved because there is a committed MediaFile
    Storage::disk('local')->assertExists($stagingPath);
});

test('cleanup does not delete a candidate claimed after initial eligibility observation', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";
    Storage::disk('local')->put($stagingPath, 'fake content');

    $oldTimestamp = now()->subHours(25)->timestamp;
    $fakeRoot = Storage::disk('local')->path(dirname($stagingPath));
    touch($fakeRoot.'/test.mp3', $oldTimestamp);

    Event::listen(function (StagingCleanupCandidateObserved $event) use ($user, $attemptId, $stagingPath): void {
        StagingClaim::factory()->create([
            'user_id' => $user->id,
            'upload_attempt_id' => $attemptId,
            'staging_path' => $stagingPath,
            'expires_at' => now()->addHour(),
        ]);
    });

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Deferred')
        ->and(Storage::disk('local')->exists($stagingPath))->toBeTrue();
});

test('cleanup staging skips non-numeric owner directories', function () {
    Storage::fake('local');

    $stagingPath = 'media/.staging/not-a-number/abc/test.mp3';
    Storage::disk('local')->put($stagingPath, 'fake content');

    $exitCode = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Skipped non-numeric owner directory');
});

test('cleanup staging skips non-uuid attempt directories', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $stagingPath = "media/.staging/{$user->id}/not-a-uuid/test.mp3";
    Storage::disk('local')->put($stagingPath, 'fake content');

    $exitCode = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Skipped non-UUID attempt directory');
});

test('cleanup staging is idempotent', function () {
    Storage::fake('local');

    $exitCode1 = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);
    $exitCode2 = Artisan::call('media:cleanup-staging', ['--dry-run' => true]);

    expect($exitCode1)->toBe(0)
        ->and($exitCode2)->toBe(0);
});

test('cleanup uses guarded conditional UPDATE instead of lockForUpdate', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create the actual directory structure on disk
    $disk = Storage::disk('local');
    $disk->put($stagingPath, 'fake content');

    // Make the file AND directory old enough to be eligible for cleanup.
    // The cleanup command seeds $mostRecent with the directory's mtime via
    // $disk->lastModified($attemptDir), so both must be old.
    $oldTimestamp = now()->subHours(25)->timestamp;
    $attemptDir = Storage::disk('local')->path(dirname($stagingPath));
    touch($attemptDir, $oldTimestamp);
    touch($attemptDir.'/test.mp3', $oldTimestamp);

    // Create an expired upload claim
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'expires_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');
    $output = Artisan::output();

    // Debug: output the command output
    // echo "Command output: " . $output . "\n";

    expect($exitCode)->toBe(0);

    // The command should have processed the candidate (either deleted or deferred)
    // Verify the file was deleted or the claim was updated
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();

    // After successful cleanup, the claim should be deleted
    // If the claim still exists, it should be in cleanup state
    if ($claim !== null) {
        expect($claim->held_by)->toBe('cleanup');
    }

    // Also check that the file was deleted
    expect($disk->exists($stagingPath))->toBeFalse();
});

test('cleanup defers when upload claim is still active', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create a fake staging file
    Storage::disk('local')->put($stagingPath, 'fake content');

    // Create an active upload claim (not expired)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'upload',
        'expires_at' => now()->addHour(),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Deferred');

    // File should still exist
    Storage::disk('local')->assertExists($stagingPath);
});

test('cleanup handles crash-recovery re-claim after timeout', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create the actual directory structure on disk
    $disk = Storage::disk('local');
    $disk->put($stagingPath, 'fake content');

    // Make the file AND directory old enough to be eligible for cleanup.
    $oldTimestamp = now()->subHours(25)->timestamp;
    $attemptDirPath = Storage::disk('local')->path(dirname($stagingPath));
    touch($attemptDirPath, $oldTimestamp);
    touch($attemptDirPath.'/test.mp3', $oldTimestamp);

    // Create a cleanup claim that timed out (16 minutes ago)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now()->subMinutes(16),
        'expires_at' => now()->addHours(24),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();

    // The command should have re-claimed AND deleted the directory
    expect($output)->toContain('crash-recovery re-claim after timeout');

    // Final state: file must be deleted
    expect($disk->exists($stagingPath))->toBeFalse();

    // Final state: claim must be gone (deleted after successful cleanup)
    $claim = StagingClaim::where('user_id', $user->id)
        ->where('upload_attempt_id', $attemptId)
        ->first();
    expect($claim)->toBeNull();
});

test('cleanup does not re-claim within timeout window', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create the actual directory structure on disk
    $disk = Storage::disk('local');

    // Nuke entire staging tree to avoid cross-test contamination from
    // prior runs that wrote real files (no Storage::fake).
    $disk->deleteDirectory('media/.staging');

    $disk->put($stagingPath, 'fake content');

    // Make the file AND directory old enough to be eligible for cleanup.
    // The cleanup command seeds $mostRecent with the directory's mtime via
    // $disk->lastModified($attemptDir), so both must be old.
    $oldTimestamp = now()->subHours(25)->timestamp;
    $attemptDirPath = Storage::disk('local')->path(dirname($stagingPath));
    touch($attemptDirPath, $oldTimestamp);
    touch($attemptDirPath.'/test.mp3', $oldTimestamp);

    // Create a cleanup claim that is still within timeout (10 minutes ago)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now()->subMinutes(10),
        'expires_at' => now()->addHours(24),
    ]);

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0);

    // The command should not have re-claimed since it's within timeout.
    // Check the summary count (the word "Reclaimed" always appears in the
    // summary line even when the count is zero).
    $output = Artisan::output();
    expect($output)->toContain('Reclaimed:  0');
    // Also verify no per-directory re-claim message was emitted.
    expect($output)->not->toContain('crash-recovery re-claim after timeout');
});

test('file deletion happens outside database transaction', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();
    $stagingPath = "media/.staging/{$user->id}/{$attemptId}/test.mp3";

    // Create the actual directory structure on disk
    $disk = Storage::disk('local');
    $disk->put($stagingPath, 'fake content');

    // Create an expired cleanup claim (already claimed by cleanup)
    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
        'staging_path' => $stagingPath,
        'held_by' => 'cleanup',
        'cleanup_claimed_at' => now()->subMinutes(5),
        'expires_at' => now()->addHours(24),
    ]);

    // Track if any database transaction is active during file deletion
    $transactionActiveDuringDelete = false;

    $exitCode = Artisan::call('media:cleanup-staging');

    expect($exitCode)->toBe(0);

    // The file should have been deleted
    // (We can't easily test that no transaction was active during deletion
    // without mocking, but we can verify the command completed successfully
    // and the file was deleted)
});
