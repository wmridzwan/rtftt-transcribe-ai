<?php

use App\Actions\StaleTranslationAttemptRecovery;
use App\Actions\TranslationRetry;
use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingTranslationProvider;

function translationSource(): Transcription
{
    $transcription = Transcription::factory()->completed()->create([
        'detected_language' => 'en',
        'full_text' => 'Hello Welcome',
    ]);

    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello',
        'language' => 'en',
    ]);

    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 1,
        'start_seconds' => 5,
        'end_seconds' => 10,
        'text' => 'Welcome',
        'language' => 'en',
    ]);

    return $transcription;
}

function alignedTranslationResult(): TranslationResult
{
    return new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'Hai Selamat datang',
        segments: [
            new TranslationSegmentData(0, 0.0, 5.0, 'Hai', LanguageIdentifier::English),
            new TranslationSegmentData(1, 5.0, 10.0, 'Selamat datang', LanguageIdentifier::English),
        ],
        provider: 'self-hosted',
        model: 'self-hosted-default',
    );
}

function runTranslationJob(Translation $translation, RecordingTranslationProvider $provider, ?string $token = null): void
{
    (new ProcessTranslation($translation->getKey(), $translation->transcription_id, $token ?? $translation->attempt_token))
        ->handle($provider, app(TranslationResultWriter::class));
}

it('completes a queued translation and leaves the source unchanged', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $before = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider);

    $fresh = $translation->fresh();

    expect($provider->calls)->toBe(1)
        ->and($fresh->status)->toBe(TranslationStatus::Completed)
        ->and($fresh->full_text)->toBe('Hai Selamat datang')
        ->and($fresh->segments()->pluck('text')->all())->toBe(['Hai', 'Selamat datang']);

    $after = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
    expect($after)->toBe($before);
});

it('no-ops on duplicate delivery after completion', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider);
    runTranslationJob($translation, $provider);

    expect($provider->calls)->toBe(1);
});

it('skips a terminal failed translation', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
    ]);

    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider);

    expect($provider->calls)->toBe(0);
});

it('skips an already-claimed translating translation', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
    ]);

    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider);

    expect($provider->calls)->toBe(0);
});

it('records a provider failure using the authoritative taxonomy', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $provider = new RecordingTranslationProvider(
        exception: new TranslationException(TranslationFailure::ProviderTimeout, 'Timed out.'),
    );
    runTranslationJob($translation, $provider);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProviderTimeout);
});

it('fails the translation when the provider result is misaligned', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $misaligned = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'wrong',
        segments: [new TranslationSegmentData(9, 0.0, 5.0, 'wrong', LanguageIdentifier::English)],
        provider: 'self-hosted',
        model: 'self-hosted-default',
    );

    runTranslationJob($translation, new RecordingTranslationProvider($misaligned));

    expect($translation->fresh()->status)->toBe(TranslationStatus::Failed)
        ->and($translation->fresh()->segments()->count())->toBe(0);
});

it('carries only small identifiers and the attempt token in the job payload', function () {
    $job = new ProcessTranslation(5, 9, 'token-abc');
    $serialized = json_encode($job);

    expect($serialized)->toContain('"translationId":5')
        ->and($serialized)->toContain('"attemptToken":"token-abc"')
        ->and($serialized)->not->toContain('storage')
        ->and($serialized)->not->toContain('media');
});

it('does not let a stale job overwrite a recovered attempt (X-1)', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
        'started_at' => now()->subSeconds(400),
        'attempt_token' => 'old-token',
    ]);

    app(StaleTranslationAttemptRecovery::class)->recover();

    // The old job arrives late; it must not mutate the recovered state.
    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider, 'old-token');

    $fresh = $translation->fresh();

    expect($provider->calls)->toBe(0)
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProviderTimeout)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
});

it('does not let a stale job overwrite a newer retried attempt (X-1)', function () {
    Queue::fake();

    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
        'started_at' => now()->subSeconds(400),
        'attempt_token' => 'old-token',
    ]);

    app(StaleTranslationAttemptRecovery::class)->recover();
    app(TranslationRetry::class)->retry($translation->fresh());

    $provider = new RecordingTranslationProvider(alignedTranslationResult());
    runTranslationJob($translation, $provider, 'old-token');

    $fresh = $translation->fresh();

    expect($provider->calls)->toBe(0)
        ->and($fresh->status)->toBe(TranslationStatus::Queued)
        ->and($fresh->attempt_token)->not->toBe('old-token');
});
