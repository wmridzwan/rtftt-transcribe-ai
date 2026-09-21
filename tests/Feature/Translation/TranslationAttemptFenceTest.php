<?php

use App\Actions\StaleTranslationAttemptRecovery;
use App\Actions\TranslationRetry;
use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingTranslationProvider;

/*
 * P5-004B attempt-fence regression coverage.
 *
 * Every test below drives an IN-FLIGHT job: it has already claimed its attempt
 * and is running the provider when stale recovery and/or a manual retry change
 * the row underneath it. The late outcome (failure or success) must never
 * mutate the recovered or newer attempt. Removing the attempt-token predicate
 * from ProcessTranslation::fail(), the writer, or the recovery compare-and-set
 * makes at least one test here fail.
 */

function fenceQueuedAttempt(string $token = 'old-token'): Translation
{
    return Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => $token,
        'dispatched_at' => now(),
    ]);
}

function fenceRecover(Translation $translation): void
{
    Translation::query()->whereKey($translation->getKey())->update(['started_at' => now()->subSeconds(1000)]);

    expect(app(StaleTranslationAttemptRecovery::class)->recover())->toBe(1);
}

function fenceRunOldJob(Translation $translation, RecordingTranslationProvider $provider): void
{
    (new ProcessTranslation($translation->getKey(), $translation->transcription_id, 'old-token'))
        ->handle($provider, app(TranslationResultWriter::class));
}

dataset('late in-flight outcomes', ['typed provider failure', 'unexpected provider failure']);

function fenceLateFailure(string $kind): array
{
    return $kind === 'typed provider failure'
        ? [new TranslationException(TranslationFailure::MalformedOutput, 'late'), null]
        : [null, new RuntimeException('late boom')];
}

it('does not restamp a recovered attempt when the in-flight job fails late', function (string $kind): void {
    $translation = fenceQueuedAttempt();
    [$typed, $unexpected] = fenceLateFailure($kind);

    $provider = new RecordingTranslationProvider(
        exception: $typed,
        whileInFlight: fn () => fenceRecover($translation),
        inFlightFailure: $unexpected,
    );

    fenceRunOldJob($translation, $provider);

    $fresh = $translation->fresh();

    expect($provider->calls)->toBe(1)
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProviderTimeout)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
})->with('late in-flight outcomes');

it('does not mutate a newer retry attempt when the in-flight job fails late', function (string $kind): void {
    Queue::fake();

    $translation = fenceQueuedAttempt();
    [$typed, $unexpected] = fenceLateFailure($kind);

    $provider = new RecordingTranslationProvider(
        exception: $typed,
        whileInFlight: function () use ($translation): void {
            fenceRecover($translation);
            app(TranslationRetry::class)->retry($translation);
        },
        inFlightFailure: $unexpected,
    );

    fenceRunOldJob($translation, $provider);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Queued)
        ->and($fresh->attempt_token)->not->toBe('old-token')
        ->and($fresh->failure_code)->toBeNull()
        ->and($fresh->completed_at)->toBeNull();
})->with('late in-flight outcomes');

it('rejects a late in-flight success after recovery and after a newer retry', function (): void {
    Queue::fake();

    $translation = fenceQueuedAttempt();

    $afterRecovery = new RecordingTranslationProvider(
        alignedTranslationResult(),
        whileInFlight: fn () => fenceRecover($translation),
    );
    fenceRunOldJob($translation, $afterRecovery);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Failed)
        ->and($translation->fresh()->segments()->count())->toBe(0);

    app(TranslationRetry::class)->retry($translation);
    $retried = $translation->fresh();

    // The old attempt is long gone; a second late delivery of its outcome must
    // not touch the newer attempt either.
    DB::table('translations')->where('id', $translation->getKey())->update(['status' => 'translating']);

    expect(fn () => app(TranslationResultWriter::class)->persist(
        $translation->transcription,
        alignedTranslationResult(),
        $translation->getKey(),
        'old-token',
    ))->toThrow(TranslationException::class);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Translating)
        ->and($fresh->attempt_token)->toBe($retried->attempt_token)
        ->and($fresh->segments()->count())->toBe(0);
});

it('does not let a slow old attempt overwrite a newer attempt that already completed', function (): void {
    Queue::fake();

    $translation = fenceQueuedAttempt();

    $provider = new RecordingTranslationProvider(
        alignedTranslationResult(),
        whileInFlight: function () use ($translation): void {
            fenceRecover($translation);
            $retried = app(TranslationRetry::class)->retry($translation);

            $newer = new RecordingTranslationProvider(new TranslationResult(
                targetLanguage: TranslationTarget::Malay,
                fullText: 'Baharu Baharu',
                segments: [
                    new TranslationSegmentData(0, 0.0, 5.0, 'Baharu', LanguageIdentifier::English),
                    new TranslationSegmentData(1, 5.0, 10.0, 'Baharu 2', LanguageIdentifier::English),
                ],
                provider: 'self-hosted',
                model: 'newer-model',
            ));

            (new ProcessTranslation($retried->getKey(), $retried->transcription_id, (string) $retried->attempt_token))
                ->handle($newer, app(TranslationResultWriter::class));
        },
    );

    fenceRunOldJob($translation, $provider);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Completed)
        ->and($fresh->model)->toBe('newer-model')
        ->and($fresh->segments()->pluck('text')->all())->toBe(['Baharu', 'Baharu 2']);
});

it('recovery cannot mutate a translation after its attempt token changes', function (): void {
    Queue::fake();

    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
        'started_at' => now()->subSeconds(1000),
        'attempt_token' => 'stale-token',
    ]);

    // The recovery command selected this attempt (its in-memory copy is stale)...
    $selected = $translation->fresh();

    // ...then the attempt was recovered, retried, and re-claimed under a new token.
    $translation->forceFill([
        'status' => TranslationStatus::Translating,
        'attempt_token' => 'newer-token',
        'started_at' => now()->subSeconds(1000),
    ])->save();

    expect(app(StaleTranslationAttemptRecovery::class)->recoverAttempt($selected))->toBeFalse();

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Translating)
        ->and($fresh->attempt_token)->toBe('newer-token')
        ->and($fresh->failure_code)->toBeNull();
});

it('recovery refuses an attempt that has no identity to fence on', function (): void {
    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
        'started_at' => now()->subSeconds(1000),
        'attempt_token' => null,
    ]);

    expect(app(StaleTranslationAttemptRecovery::class)->recoverAttempt($translation))->toBeFalse()
        ->and($translation->fresh()->status)->toBe(TranslationStatus::Translating);
});
