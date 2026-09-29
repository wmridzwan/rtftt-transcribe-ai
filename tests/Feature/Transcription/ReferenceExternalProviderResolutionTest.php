<?php

use App\Transcription\NormalizedTranscript;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\SelfHostedTranscriptionProvider;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProvider;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationProvider;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTransport;

const PP3_RESOLUTION_TOKEN = 'fixture-token-pp3-resolution';

function pp3ResolutionAdapter(FakeReferenceExternalTransport $transport): ReferenceExternalTranscriptionProvider
{
    return new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP3_RESOLUTION_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );
}

beforeEach(function () {
    Storage::fake('local');
    config(['transcription.worker_url' => 'http://localhost:8000']);
    config(['processing.external_kill_switch' => false]);
    config(['transcription.provider_selection' => 'self_hosted']);
});

it('AC1: reference adapter implements the frozen provider contract', function () {
    $adapter = pp3ResolutionAdapter(new FakeReferenceExternalTransport);

    expect($adapter)->toBeInstanceOf(TranscriptionProvider::class)
        ->and($adapter)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class);
});

it('AC1: resolves only through the PP-T2 resolver seam', function () {
    $transport = new FakeReferenceExternalTransport;
    app()->bind('transcription.providers.external_reference', fn () => pp3ResolutionAdapter($transport));

    config(['transcription.provider_selection' => 'external_reference']);

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class);

    // The resolved adapter serves invocations through the frozen contract.
    $result = $resolved->transcribe(TranscriptionInvocation::create(
        transcriptionId: 5,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/pp3-resolution.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 4096,
            durationSeconds: 4.0,
        ),
    ));

    expect($result)->toBeInstanceOf(NormalizedTranscript::class)
        ->and($transport->dispatchCount())->toBe(1);
});

it('AC1: production code constructs the adapter at no call site outside tests', function () {
    $matches = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Transcription')));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());

        if (str_contains($code, 'new ReferenceExternalTranscriptionProvider')) {
            $matches[] = $file->getFilename();
        }
    }

    expect($matches)->toBe([]);
});

it('AC9: kill-switch engagement forces self-hosted with zero adapter invocations', function () {
    $transport = new FakeReferenceExternalTransport;
    $shared = pp3ResolutionAdapter($transport);
    app()->bind('transcription.providers.external_reference', fn () => $shared);

    config(['transcription.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => true]);

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($transport->dispatchCount())->toBe(0);
});

it('leaves the translation resolver untouched', function () {
    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranslationProvider::class);
});
