<?php

use App\Actions\TranscriptionOrchestrator;
use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Http\Middleware\AssignRequestId;
use App\Jobs\ProcessTranscription;
use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationResultWriter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\RecordingTranscriptionProvider;
use Tests\Support\RecordingTranslationProvider;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

it('emits the ADR-017 correlation fields on the transcription failure record', function () {
    ['transcription' => $transcription, 'attempt' => $attempt, 'media' => $media] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    Log::spy();

    (new ProcessTranscription($transcription->getKey(), $attempt->getKey(), 'trace-http-12345678'))
        ->handle(
            new RecordingTranscriptionProvider(exception: new TranscriptionException(TranscriptionFailure::WorkerUnavailable)),
            app(TranscriptionResultWriter::class),
        );

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($transcription, $attempt, $media): bool {
        if ($message !== 'Transcription job failed.') {
            return false;
        }

        expect($context)->toHaveKeys([
            'transcription_id', 'media_file_id', 'model', 'processing_job_id',
            'stage', 'attempt_number', 'failure', 'failure_code', 'http_request_id',
        ])
            ->and($context['transcription_id'])->toBe($transcription->getKey())
            ->and($context['media_file_id'])->toBe($media->getKey())
            ->and($context['processing_job_id'])->toBe($attempt->getKey())
            ->and($context['failure_code'])->toBe(TranscriptionFailure::WorkerUnavailable->value)
            ->and($context['http_request_id'])->toBe('trace-http-12345678')
            ->and($context['attempt_number'])->toBe(1);

        return true;
    })->once();
})->group('p7-005');

it('emits the ADR-017 correlation fields on the translation failure record', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    Log::spy();

    (new ProcessTranslation($translation->getKey(), $transcription->getKey(), (string) $translation->attempt_token, 'trace-http-87654321'))
        ->handle(
            new RecordingTranslationProvider(exception: new TranslationException(TranslationFailure::ProviderTimeout, 'Timed out.')),
            app(TranslationResultWriter::class),
        );

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($translation, $transcription): bool {
        if ($message !== 'Translation job failed.') {
            return false;
        }

        expect($context)->toHaveKeys([
            'translation_id', 'transcription_id', 'target_language', 'model',
            'failure', 'failure_code', 'http_request_id',
        ])
            ->and($context['translation_id'])->toBe($translation->getKey())
            ->and($context['transcription_id'])->toBe($transcription->getKey())
            ->and($context['target_language'])->toBe('ms')
            ->and($context['failure_code'])->toBe(TranslationFailure::ProviderTimeout->value)
            ->and($context['http_request_id'])->toBe('trace-http-87654321');

        return true;
    })->once();
})->group('p7-005');

it('enriches the superseded translation failure record with correlation fields', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ta',
        'status' => 'translating',
        'attempt_token' => 'current-token',
    ]);

    Log::spy();

    (new ProcessTranslation($translation->getKey(), $transcription->getKey(), 'stale-token', 'trace-http-superseded'))
        ->failed(null);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($translation): bool {
        if ($message !== 'Translation failure ignored for a superseded attempt.') {
            return false;
        }

        expect($context)->toHaveKeys([
            'translation_id', 'transcription_id', 'target_language', 'model',
            'failure', 'failure_code', 'http_request_id',
        ])
            ->and($context['translation_id'])->toBe($translation->getKey())
            ->and($context['target_language'])->toBe('ta')
            ->and($context['failure_code'])->toBe(TranslationFailure::ProcessingFailed->value);

        return true;
    })->once();
})->group('p7-005');

it('emits duration_ms and correlation fields on the transcription completion record', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    Log::spy();

    (new ProcessTranscription($transcription->getKey(), $attempt->getKey(), 'trace-http-completion'))
        ->handle(new RecordingTranscriptionProvider, app(TranscriptionResultWriter::class));

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        if ($message !== 'Transcription job completed.') {
            return false;
        }

        expect($context)->toHaveKeys([
            'transcription_id', 'processing_job_id', 'attempt_number',
            'duration_ms', 'http_request_id',
        ])
            ->and($context['duration_ms'])->toBeInt()
            ->and($context['http_request_id'])->toBe('trace-http-completion');

        return true;
    })->once();
})->group('p7-005');

it('completes a transcription job even when the attempt-ordinal lookup fails', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    Log::spy();

    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, 'processing_jobs')) {
            throw new RuntimeException('Injected ordinal lookup failure.');
        }
    });

    (new ProcessTranscription($transcription->getKey(), $attempt->getKey()))
        ->handle(new RecordingTranscriptionProvider, app(TranscriptionResultWriter::class));

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed);

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        if ($message !== 'Transcription job completed.') {
            return false;
        }

        expect($context['attempt_number'])->toBeNull();

        return true;
    })->once();
})->group('p7-005');

it('propagates the HTTP correlation id into the dispatched transcription job payload', function () {
    Queue::fake();

    ['attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', 'trace-dispatch-1234');
    app()->instance('request', $request);
    app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    app(TranscriptionOrchestrator::class)->dispatch($attempt);

    Queue::assertPushed(
        ProcessTranscription::class,
        fn (ProcessTranscription $job): bool => $job->httpRequestId === 'trace-dispatch-1234',
    );
})->group('p7-005');
