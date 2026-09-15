<?php

use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

test('staging claim can be created with valid attributes', function () {
    $user = User::factory()->create();
    $claim = StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => (string) Str::uuid(),
    ]);

    expect($claim)->toBeInstanceOf(StagingClaim::class)
        ->and($claim->user_id)->toBe($user->id)
        ->and($claim->upload_attempt_id)->not->toBeNull()
        ->and($claim->staging_path)->not->toBeNull()
        ->and($claim->claimed_at)->not->toBeNull()
        ->and($claim->expires_at)->not->toBeNull();
});

test('staging claim belongs to a user', function () {
    $user = User::factory()->create();
    $claim = StagingClaim::factory()->create(['user_id' => $user->id]);

    expect($claim->user)->toBeInstanceOf(User::class)
        ->and($claim->user->id)->toBe($user->id);
});

test('staging claim is active when not expired', function () {
    $claim = StagingClaim::factory()->create([
        'expires_at' => now()->addHours(1),
    ]);

    expect($claim->isActive())->toBeTrue()
        ->and($claim->isExpired())->toBeFalse();
});

test('staging claim is expired when expires_at is in the past', function () {
    $claim = StagingClaim::factory()->create([
        'expires_at' => now()->subHour(),
    ]);

    expect($claim->isExpired())->toBeTrue()
        ->and($claim->isActive())->toBeFalse();
});

test('staging claim enforces unique user attempt constraint', function () {
    $user = User::factory()->create();
    $attemptId = (string) Str::uuid();

    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
    ]);

    // Creating another claim with the same user_id and upload_attempt_id should fail
    $this->expectException(QueryException::class);

    StagingClaim::factory()->create([
        'user_id' => $user->id,
        'upload_attempt_id' => $attemptId,
    ]);
});

test('staging claim allows same attempt id for different users', function () {
    $attemptId = (string) Str::uuid();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    StagingClaim::factory()->create([
        'user_id' => $user1->id,
        'upload_attempt_id' => $attemptId,
    ]);

    $claim2 = StagingClaim::factory()->create([
        'user_id' => $user2->id,
        'upload_attempt_id' => $attemptId,
    ]);

    expect($claim2)->toBeInstanceOf(StagingClaim::class);
    expect(StagingClaim::count())->toBe(2);
});
