<?php

use App\Actions\TranslationRetry;
use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationStatus;
use Illuminate\Support\Facades\Queue;

it('considers a failed, retryable translation eligible', function () {
    $translation = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    expect(app(TranslationRetry::class)->isEligible($translation))->toBeTrue();
});

it('does not consider a non-retryable failure eligible', function () {
    $translation = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'failure_code' => 'MALFORMED_OUTPUT',
    ]);

    expect(app(TranslationRetry::class)->isEligible($translation))->toBeFalse();
});

it('does not consider a completed translation eligible', function () {
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => translationSource()->getKey(),
    ]);

    expect(app(TranslationRetry::class)->isEligible($translation))->toBeFalse();
});

it('requeues a failed translation and dispatches the job', function () {
    Queue::fake();

    $translation = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'failure_code' => 'PROVIDER_FAILED',
    ]);

    $retried = app(TranslationRetry::class)->retry($translation);

    expect($retried->status)->toBe(TranslationStatus::Queued)
        ->and($retried->failure_code)->toBeNull()
        ->and($retried->completed_at)->toBeNull();

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('returns an already-active translation without dispatching', function () {
    Queue::fake();

    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'status' => 'queued',
        'failure_code' => null,
    ]);

    $retried = app(TranslationRetry::class)->retry($translation);

    expect($retried->getKey())->toBe($translation->getKey());
    Queue::assertNotPushed(ProcessTranslation::class);
});

it('refuses to retry a completed translation', function () {
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => translationSource()->getKey(),
    ]);

    expect(fn () => app(TranslationRetry::class)->retry($translation))
        ->toThrow(TranslationException::class);
});

it('refuses to retry a non-eligible failure', function () {
    $translation = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'failure_code' => 'MALFORMED_OUTPUT',
    ]);

    expect(fn () => app(TranslationRetry::class)->retry($translation))
        ->toThrow(TranslationException::class);
});
