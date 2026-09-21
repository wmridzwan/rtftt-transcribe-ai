<?php

use App\Translation\TranslationStatus;

it('exposes the canonical translation statuses', function () {
    expect(TranslationStatus::cases())->toHaveCount(5);
    expect(TranslationStatus::Pending->value)->toBe('pending');
    expect(TranslationStatus::Queued->value)->toBe('queued');
    expect(TranslationStatus::Translating->value)->toBe('translating');
    expect(TranslationStatus::Completed->value)->toBe('completed');
    expect(TranslationStatus::Failed->value)->toBe('failed');
});

it('treats completed and failed as terminal', function () {
    expect(TranslationStatus::Completed->isTerminal())->toBeTrue();
    expect(TranslationStatus::Failed->isTerminal())->toBeTrue();
    expect(TranslationStatus::Pending->isTerminal())->toBeFalse();
    expect(TranslationStatus::Queued->isTerminal())->toBeFalse();
    expect(TranslationStatus::Translating->isTerminal())->toBeFalse();
});
