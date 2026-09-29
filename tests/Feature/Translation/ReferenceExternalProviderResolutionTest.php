<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptionProvider;
use App\Translation\ProviderResolutionException;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationProviderResolver;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Tests\Support\FakeReferenceExternalTranslationTransport;

const PP4_RESOLUTION_TOKEN = 'fixture-token-pp4-resolution';

function pp4ResolutionShapes(): array
{
    return [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
        ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
    ];
}

function pp4ResolutionInvocation(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::English),
        ],
    );
}

function pp4ResolutionAdapter(FakeReferenceExternalTranslationTransport $transport): ReferenceExternalTranslationProvider
{
    return new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP4_RESOLUTION_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );
}

beforeEach(function () {
    config(['translation.worker_url' => 'http://localhost:8000']);
    config(['processing.external_kill_switch' => false]);
    config(['translation.provider_selection' => 'self_hosted']);
});

it('AC1: resolves the fixture adapter only through the PP-T2 resolver seam', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4ResolutionShapes())
    );
    app()->bind('translation.providers.external_reference', fn () => pp4ResolutionAdapter($transport));

    config(['translation.provider_selection' => 'external_reference']);

    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(ReferenceExternalTranslationProvider::class);

    $result = $resolved->translate(pp4ResolutionInvocation());

    expect($result->targetLanguage)->toBe(TranslationTarget::Malay)
        ->and($transport->dispatchCount())->toBe(1);
});

it('AC1: production code constructs the adapter at no call site outside tests', function () {
    $matches = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());

        if (str_contains($code, 'new ReferenceExternalTranslationProvider')) {
            $matches[] = $file->getFilename();
        }
    }

    expect($matches)->toBe([]);
});

it('keeps the self-hosted default unchanged', function () {
    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);
});

it('rejects arbitrary external translation selections even when bound', function () {
    foreach (['external_x', 'external_acme', 'external_reference_v2'] as $selection) {
        app()->bind('translation.providers.'.$selection, fn () => new stdClass);
        config(['translation.provider_selection' => $selection]);

        try {
            app(TranslationProvider::class);
            expect(false)->toBeTrue("expected rejection of [{$selection}]");
        } catch (ProviderResolutionException $e) {
            expect($e->getMessage())->toContain('Wave 1 supports self_hosted only');
        }
    }
});

it('rejects external_reference without a container binding (fail closed, zero egress)', function () {
    config(['translation.provider_selection' => 'external_reference']);

    try {
        app(TranslationProvider::class);
        expect(false)->toBeTrue('expected binding-missing rejection');
    } catch (ProviderResolutionException $e) {
        expect($e->getMessage())->toContain('has no container binding');
    }
});

it('rejects an invalid external_reference binding that breaks the contract', function () {
    app()->bind('translation.providers.external_reference', fn () => new stdClass);
    config(['translation.provider_selection' => 'external_reference']);

    try {
        app(TranslationProvider::class);
        expect(false)->toBeTrue('expected invalid-binding rejection');
    } catch (ProviderResolutionException $e) {
        expect($e->getMessage())->toContain('does not implement TranslationProvider');
    }
});

it('AC9: kill-switch engagement forces self-hosted with zero adapter invocations', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4ResolutionShapes())
    );
    app()->bind('translation.providers.external_reference', fn () => pp4ResolutionAdapter($transport));

    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => true]);

    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transport->dispatchCount())->toBe(0);
});

it('invalid kill-switch value preserves PP-T2 fail-closed behavior', function () {
    $transport = new FakeReferenceExternalTranslationTransport;
    app()->bind('translation.providers.external_reference', fn () => pp4ResolutionAdapter($transport));

    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => 'maybe']);

    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transport->dispatchCount())->toBe(0);
});

it('boot validation accepts the scoped fixture value and still rejects vendors', function () {
    config(['translation.provider_selection' => 'external_reference']);
    TranslationProviderResolver::validateSelection();
    expect(true)->toBeTrue();

    config(['translation.provider_selection' => 'external_x']);

    try {
        TranslationProviderResolver::validateSelection();
        expect(false)->toBeTrue('expected boot rejection');
    } catch (ProviderResolutionException $e) {
        expect($e->getMessage())->toContain('Wave 1 supports self_hosted only');
    }
});

it('leaves the transcription resolver untouched', function () {
    expect(app(TranscriptionProvider::class))->not->toBeInstanceOf(ReferenceExternalTranslationProvider::class);
});
