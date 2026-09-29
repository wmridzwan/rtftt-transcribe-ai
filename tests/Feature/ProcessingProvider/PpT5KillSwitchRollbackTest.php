<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\SelfHostedTranscriptionProvider;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProvider;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTranslationTransport;
use Tests\Support\FakeReferenceExternalTransport;

const PP5_KS_TOKEN = 'fixture-token-pp5-ks-not-a-secret';

function pp5ksBindTranscription(): FakeReferenceExternalTransport
{
    $transport = new FakeReferenceExternalTransport;

    app()->bind('transcription.providers.external_reference', fn () => new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP5_KS_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    ));

    return $transport;
}

function pp5ksBindTranslation(): FakeReferenceExternalTranslationTransport
{
    $transport = new FakeReferenceExternalTranslationTransport;

    app()->bind('translation.providers.external_reference', fn () => new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_KS_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    ));

    return $transport;
}

function pp5ksTranscriptionInvocation(): TranscriptionInvocation
{
    return TranscriptionInvocation::create(
        transcriptionId: 501,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/pp5-ks.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 4096,
            durationSeconds: 4.0,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function pp5ksTranslationInvocation(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 502,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::fromBcp47('en')),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::fromBcp47('en')),
        ],
        translationId: 503,
    );
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp5ksCaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
}

beforeEach(function () {
    Storage::fake('local');
    config(['processing.external_kill_switch' => false]);
    config(['transcription.provider_selection' => 'self_hosted']);
    config(['translation.provider_selection' => 'self_hosted']);
    config(['transcription.timeout_seconds' => 300]);
    config(['translation.timeout_seconds' => 300]);
    config(['transcription.worker_token' => '']);
    config(['translation.worker_token' => '']);
});

it('AC3 disengaged: transcription reference path is usable per its fixture contract', function () {
    $transport = pp5ksBindTranscription();
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    config(['transcription.provider_selection' => 'external_reference']);

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class);

    $result = $resolved->transcribe(pp5ksTranscriptionInvocation());

    expect($result)->toBeInstanceOf(NormalizedTranscript::class)
        ->and($transport->dispatchCount())->toBe(1);
});

it('AC3 disengaged: translation reference path is usable per its fixture contract', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );
    app()->bind('translation.providers.external_reference', fn () => new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_KS_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    ));

    config(['translation.provider_selection' => 'external_reference']);

    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(ReferenceExternalTranslationProvider::class);

    $result = $resolved->translate(pp5ksTranslationInvocation());

    expect($result)->toBeInstanceOf(TranslationResult::class)
        ->and($transport->dispatchCount())->toBe(1);
});

it('AC3 engaged: both domains are forced self-hosted with zero reference dispatches', function () {
    $transcriptionTransport = pp5ksBindTranscription();
    $translationTransport = pp5ksBindTranslation();

    config(['transcription.provider_selection' => 'external_reference']);
    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => true]);

    $captured = pp5ksCaptureLogs(function () use (&$transcriptionResolved, &$translationResolved) {
        $transcriptionResolved = app(TranscriptionProvider::class);
        $translationResolved = app(TranslationProvider::class);
    });

    expect($transcriptionResolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($translationResolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0);

    $messages = array_map(static fn (MessageLogged $record): string => $record->message, $captured['records']);

    expect($messages)->toContain('Processing kill switch engaged; forcing self-hosted transcription.')
        ->and($messages)->toContain('Processing kill switch engaged; forcing self-hosted translation.');
});

it('AC3 invalid kill-switch value fails closed per PP-T2 semantics with no PP-T5 override', function () {
    $transcriptionTransport = pp5ksBindTranscription();
    $translationTransport = pp5ksBindTranslation();

    config(['transcription.provider_selection' => 'external_reference']);
    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => 'maybe']);

    $captured = pp5ksCaptureLogs(function () use (&$transcriptionResolved, &$translationResolved) {
        $transcriptionResolved = app(TranscriptionProvider::class);
        $translationResolved = app(TranslationProvider::class);
    });

    expect($transcriptionResolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($translationResolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0);

    $warnings = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->level === 'warning'
            && str_contains($record->message, 'Invalid processing kill-switch value'),
    ));

    expect($warnings)->toHaveCount(2);

    foreach ($warnings as $warning) {
        expect($warning->context['configured_value'])->toBe('maybe')
            ->and($warning->context['kill_switch_engaged'])->toBeTrue();
    }
});

it('AC3 no fallback chain: engaged mechanism never rewrites selection config', function () {
    pp5ksBindTranscription();
    pp5ksBindTranslation();

    config(['transcription.provider_selection' => 'external_reference']);
    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => true]);

    app(TranscriptionProvider::class);
    app(TranslationProvider::class);

    // The mechanism forces self-hosted without mutating the stored selection:
    // disengaging restores the exact prior selection (no sticky reroute).
    expect(config('transcription.provider_selection'))->toBe('external_reference')
        ->and(config('translation.provider_selection'))->toBe('external_reference');

    config(['processing.external_kill_switch' => false]);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class)
        ->and(app(TranslationProvider::class))->toBeInstanceOf(ReferenceExternalTranslationProvider::class);
});

it('AC4 rollback rehearsal restores self-hosted-only clean state without migration', function () {
    $transcriptionTransport = pp5ksBindTranscription();
    $translationTransport = pp5ksBindTranslation();

    // Preconditions: stock defaults route self-hosted.
    expect(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);

    // Simulated change: operator selects both reference paths; both serve.
    config(['transcription.provider_selection' => 'external_reference']);
    config(['translation.provider_selection' => 'external_reference']);

    $transcriptionTransport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'rehearsal', 'language' => 'en'],
        ],
    );

    app(TranscriptionProvider::class)->transcribe(pp5ksTranscriptionInvocation());

    expect($transcriptionTransport->dispatchCount())->toBe(1);

    // Safe path activation: engage the kill-switch (documented refresh step is
    // config:clear + worker recycle in real deployments; config() set here).
    config(['processing.external_kill_switch' => true]);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transcriptionTransport->dispatchCount())->toBe(1)
        ->and($translationTransport->dispatchCount())->toBe(0);

    // Restoration to the prior known-good setting.
    config(['transcription.provider_selection' => 'self_hosted']);
    config(['translation.provider_selection' => 'self_hosted']);
    config(['processing.external_kill_switch' => false]);

    $transcriptionRestored = app(TranscriptionProvider::class);
    $translationRestored = app(TranslationProvider::class);

    expect($transcriptionRestored)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($transcriptionRestored)->not->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class)
        ->and($translationRestored)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($translationRestored)->not->toBeInstanceOf(ReferenceExternalTranslationProvider::class);

    // Clean-state criteria: stock selections, disengaged switch, fixture
    // bindings present-but-unselected, zero new external attempts after refresh.
    expect(config('transcription.provider_selection'))->toBe('self_hosted')
        ->and(config('translation.provider_selection'))->toBe('self_hosted')
        ->and(config('processing.external_kill_switch'))->toBeFalse()
        ->and(app()->bound('transcription.providers.external_reference'))->toBeTrue()
        ->and(app()->bound('translation.providers.external_reference'))->toBeTrue()
        ->and($transcriptionTransport->dispatchCount())->toBe(1)
        ->and($translationTransport->dispatchCount())->toBe(0);
});
