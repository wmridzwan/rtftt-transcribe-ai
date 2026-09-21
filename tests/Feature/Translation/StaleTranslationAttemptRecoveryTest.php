<?php

use App\Actions\StaleTranslationAttemptRecovery;
use App\Models\Translation;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;

it('recovers a demonstrably stale translating translation', function () {
    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'status' => 'translating',
        'started_at' => now()->subSeconds(400),
        'failure_code' => null,
    ]);

    $recovered = app(StaleTranslationAttemptRecovery::class)->recover();

    $fresh = $translation->fresh();

    expect($recovered)->toBe(1)
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProviderTimeout);
});

it('leaves a fresh translating translation untouched', function () {
    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'status' => 'translating',
        'started_at' => now()->subSeconds(5),
    ]);

    $recovered = app(StaleTranslationAttemptRecovery::class)->recover();

    expect($recovered)->toBe(0)
        ->and($translation->fresh()->status)->toBe(TranslationStatus::Translating);
});

it('never overwrites a completed translation', function () {
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => translationSource()->getKey(),
        'started_at' => now()->subSeconds(400),
    ]);

    $recovered = app(StaleTranslationAttemptRecovery::class)->recover();

    expect($recovered)->toBe(0)
        ->and($translation->fresh()->status)->toBe(TranslationStatus::Completed);
});

it('derives the stale threshold from the provider timeout plus safety margin', function () {
    config()->set('translation.attempt_stale_seconds', null);
    config()->set('translation.timeout_seconds', 300);

    expect(app(StaleTranslationAttemptRecovery::class)->staleThresholdSeconds())->toBe(360);
});

it('honours an explicit stale threshold override', function () {
    config()->set('translation.attempt_stale_seconds', 90);

    expect(app(StaleTranslationAttemptRecovery::class)->staleThresholdSeconds())->toBe(90);
});
