<?php

use App\Enums\TranscriptionStatus;
use App\Transcription\TranscriptionLifecycle;

/*
 * P3-007 / ADR-018 lifecycle extension: failed → queued is the only exit from
 * the terminal failed state, reachable only through the explicit retry action.
 */

test('a failed transcription can be re-opened to queued', function () {
    expect(TranscriptionLifecycle::canTransition(TranscriptionStatus::Failed, TranscriptionStatus::Queued))->toBeTrue();
});

test('a failed transcription cannot jump directly to any other state', function () {
    expect(TranscriptionLifecycle::canTransition(TranscriptionStatus::Failed, TranscriptionStatus::Preparing))->toBeFalse()
        ->and(TranscriptionLifecycle::canTransition(TranscriptionStatus::Failed, TranscriptionStatus::Transcribing))->toBeFalse()
        ->and(TranscriptionLifecycle::canTransition(TranscriptionStatus::Failed, TranscriptionStatus::Completed))->toBeFalse()
        ->and(TranscriptionLifecycle::canTransition(TranscriptionStatus::Failed, TranscriptionStatus::Cancelled))->toBeFalse();
});

test('completed and cancelled remain terminal', function () {
    expect(TranscriptionLifecycle::canTransition(TranscriptionStatus::Completed, TranscriptionStatus::Queued))->toBeFalse()
        ->and(TranscriptionLifecycle::canTransition(TranscriptionStatus::Completed, TranscriptionStatus::Transcribing))->toBeFalse()
        ->and(TranscriptionLifecycle::canTransition(TranscriptionStatus::Cancelled, TranscriptionStatus::Queued))->toBeFalse();
});
