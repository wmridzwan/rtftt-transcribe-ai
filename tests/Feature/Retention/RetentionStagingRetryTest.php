<?php

use App\Models\StagingClaim;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Storage;

/*
 * P7-011 corrective cycle 1, Finding 2: a failed staging byte deletion
 * must retain the claim row (released for retry), never discard it.
 *
 * Sequence proven here: expired object exists → deletion forced to
 * fail → purge runs → file remains, failure reported, claim row
 * recoverable (held_by back to upload) → filesystem fixed → retry
 * completes cleanup safely with no false success anywhere.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('retains the staging claim when byte deletion fails and retries cleanly', function (): void {
    $user = User::factory()->create();
    $disk = Storage::disk(config('media.storage_disk'));

    $claim = StagingClaim::factory()->create([
        'user_id' => $user->id,
        'held_by' => 'upload',
        'expires_at' => now()->subHour(),
        'created_at' => now()->subDays(2),
    ]);

    // A directory where the file should be: the adapter cannot delete
    // it as a file, forcing deterministic filesystem failure.
    $disk->makeDirectory($claim->staging_path);

    $first = RetentionPurge::run();

    // 4. File remains.
    expect($disk->exists($claim->staging_path))->toBeTrue()
        // Failure reported, never success.
        ->and($first['outcomes']['failed'])->toBe(1)
        ->and($first['outcomes']['purged'])->toBe(0);

    // 5. Claim row retained and released for deterministic retry.
    $retained = StagingClaim::query()->find($claim->id);

    expect($retained)->not->toBeNull()
        ->and($retained->held_by)->toBe('upload')
        ->and($retained->cleanup_claimed_at)->toBeNull();

    // Filesystem fixed (blocking directory removed; bytes were never
    // there) → retry completes cleanup safely.
    $disk->deleteDirectory($claim->staging_path);

    $second = RetentionPurge::run();

    expect($second['outcomes']['absent'])->toBe(1)
        ->and($second['outcomes']['failed'])->toBe(0)
        ->and(StagingClaim::query()->find($claim->id))->toBeNull();
});
