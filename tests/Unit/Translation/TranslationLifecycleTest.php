<?php

use App\Translation\TranslationLifecycle;
use App\Translation\TranslationStatus;

it('permits the canonical forward transitions', function () {
    expect(TranslationLifecycle::canTransition(TranslationStatus::Pending, TranslationStatus::Queued))->toBeTrue();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Queued, TranslationStatus::Translating))->toBeTrue();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Translating, TranslationStatus::Completed))->toBeTrue();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Queued, TranslationStatus::Failed))->toBeTrue();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Translating, TranslationStatus::Failed))->toBeTrue();
});

it('permits only the explicit manual-retry edge out of failed', function () {
    expect(TranslationLifecycle::canTransition(TranslationStatus::Failed, TranslationStatus::Queued))->toBeTrue();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Failed, TranslationStatus::Translating))->toBeFalse();
});

it('keeps completed terminal', function () {
    foreach (TranslationStatus::cases() as $target) {
        expect(TranslationLifecycle::canTransition(TranslationStatus::Completed, $target))->toBeFalse();
    }
});

it('rejects illegal transitions', function () {
    expect(TranslationLifecycle::canTransition(TranslationStatus::Pending, TranslationStatus::Translating))->toBeFalse();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Pending, TranslationStatus::Completed))->toBeFalse();
    expect(TranslationLifecycle::canTransition(TranslationStatus::Queued, TranslationStatus::Completed))->toBeFalse();
});

it('asserts valid transitions and throws on illegal ones', function () {
    TranslationLifecycle::assertValidTransition(TranslationStatus::Queued, TranslationStatus::Translating);

    expect(fn () => TranslationLifecycle::assertValidTransition(
        TranslationStatus::Completed,
        TranslationStatus::Queued,
    ))->toThrow(InvalidArgumentException::class);
});
