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

it('requeues a failed translation with a fresh attempt token and dispatches', function () {
    Queue::fake();

    $translation = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'failure_code' => 'PROVIDER_FAILED',
        'attempt_token' => 'old-token',
    ]);

    $retried = app(TranslationRetry::class)->retry($translation);

    expect($retried->status)->toBe(TranslationStatus::Queued)
        ->and($retried->failure_code)->toBeNull()
        ->and($retried->completed_at)->toBeNull()
        ->and($retried->attempt_token)->not->toBe('old-token');

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

it('converges on an active attempt instead of leaking a unique-index exception (X-2)', function () {
    Queue::fake();

    $transcription = translationSource();

    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_FAILED',
    ]);

    $active = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $result = app(TranslationRetry::class)->retry($failed);

    expect($result->getKey())->toBe($active->getKey())
        ->and(Translation::query()->where('transcription_id', $transcription->getKey())->where('target_language', 'ms')->count())->toBe(2);
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
