<?php

use App\Enums\TranscriptionStatus;
use App\Transcription\ProcessingAttemptIdentity;
use App\Transcription\TranscriptionIdentity;
use App\Transcription\TranscriptionLifecycle;
use App\Transcription\TranscriptionOwnership;

it('validates correct lifecycle transitions', function () {
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Queued,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Queued,
        TranscriptionStatus::Preparing,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Preparing,
        TranscriptionStatus::Transcribing,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Transcribing,
        TranscriptionStatus::Completed,
    ))->toBeTrue();
});

it('rejects invalid lifecycle transitions', function () {
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Completed,
    ))->toBeFalse();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Transcribing,
    ))->toBeFalse();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Completed,
        TranscriptionStatus::Draft,
    ))->toBeFalse();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Failed,
        TranscriptionStatus::Transcribing,
    ))->toBeFalse();
});

it('allows cancellation from active states', function () {
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Cancelled,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Queued,
        TranscriptionStatus::Cancelled,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Preparing,
        TranscriptionStatus::Cancelled,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Transcribing,
        TranscriptionStatus::Cancelled,
    ))->toBeTrue();
});

it('allows failure from active states', function () {
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Queued,
        TranscriptionStatus::Failed,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Preparing,
        TranscriptionStatus::Failed,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Transcribing,
        TranscriptionStatus::Failed,
    ))->toBeTrue();
});

it('asserts valid transition', function () {
    TranscriptionLifecycle::assertValidTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Queued,
    );
    // No exception means success
    expect(true)->toBeTrue();
});

it('throws on invalid transition assertion', function () {
    TranscriptionLifecycle::assertValidTransition(
        TranscriptionStatus::Draft,
        TranscriptionStatus::Completed,
    );
})->throws(InvalidArgumentException::class, 'Invalid lifecycle transition: draft → completed.');

it('terminal states have no transitions except the canonical retry re-open', function () {
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Completed,
        TranscriptionStatus::Draft,
    ))->toBeFalse();

    // P3-007 / ADR-018: failed → queued is the single canonical retry
    // re-open transition; all other exits from failed remain invalid.
    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Failed,
        TranscriptionStatus::Queued,
    ))->toBeTrue();

    expect(TranscriptionLifecycle::canTransition(
        TranscriptionStatus::Cancelled,
        TranscriptionStatus::Draft,
    ))->toBeFalse();
});

it('creates valid transcription identity', function () {
    $identity = new TranscriptionIdentity(
        transcriptionId: 1,
        mediaFileId: 10,
        userId: 100,
    );

    expect($identity->transcriptionId)->toBe(1);
    expect($identity->mediaFileId)->toBe(10);
    expect($identity->userId)->toBe(100);
});

it('rejects invalid transcription identity', function () {
    new TranscriptionIdentity(
        transcriptionId: 0,
        mediaFileId: 10,
        userId: 100,
    );
})->throws(InvalidArgumentException::class, 'Transcription ID must be a positive integer.');

it('creates processing attempt identity', function () {
    $attempt = ProcessingAttemptIdentity::create(attemptId: 1);

    expect($attempt->attemptId)->toBe(1);
    expect($attempt->requestId)->not->toBeEmpty();
});

it('retry preserves transcription identity but uses different attempt', function () {
    $original = TranscriptionIdentity::create(
        transcriptionId: 1,
        mediaFileId: 10,
        userId: 100,
    );
    $attempt1 = ProcessingAttemptIdentity::create(attemptId: 1);
    $attempt2 = ProcessingAttemptIdentity::create(attemptId: 2);

    // Same logical transcription
    expect($original->transcriptionId)->toBe(1);
    // Different attempts
    expect($attempt1->attemptId)->not->toBe($attempt2->attemptId);
    // Different request IDs
    expect($attempt1->requestId)->not->toBe($attempt2->requestId);
});

it('retranscription creates independent identity', function () {
    $original = TranscriptionIdentity::create(
        transcriptionId: 1,
        mediaFileId: 10,
        userId: 100,
    );
    $retranscription = TranscriptionIdentity::create(
        transcriptionId: 2,
        mediaFileId: 10,
        userId: 100,
    );

    // Different transcription IDs
    expect($original->transcriptionId)->not->toBe($retranscription->transcriptionId);
    // Same media and user
    expect($original->mediaFileId)->toBe($retranscription->mediaFileId);
    expect($original->userId)->toBe($retranscription->userId);
});

it('validates ownership', function () {
    $ownership = new TranscriptionOwnership(
        userId: 100,
        mediaFileId: 10,
        transcriptionId: 1,
    );

    expect($ownership->isOwnedBy(100))->toBeTrue();
    expect($ownership->isOwnedBy(200))->toBeFalse();
});

it('asserts ownership', function () {
    $ownership = new TranscriptionOwnership(
        userId: 100,
        mediaFileId: 10,
        transcriptionId: 1,
    );

    $ownership->assertOwnership(100);
    expect(true)->toBeTrue();
});

it('throws on ownership violation', function () {
    $ownership = new TranscriptionOwnership(
        userId: 100,
        mediaFileId: 10,
        transcriptionId: 1,
    );

    $ownership->assertOwnership(200);
})->throws(InvalidArgumentException::class, 'User 200 does not own transcription 1.');
