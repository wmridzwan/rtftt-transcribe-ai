<?php

use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\Translation;
use App\Observability\LogContext;
use App\Transcription\NormalizedTranscript;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionProvider;
use App\Transcription\TranscriptionProviderResolver;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResult;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTransport;

const PP5_DO_RUNBOOK = 'verification/pp-t5/PP-T5-OPERATIONAL-RUNBOOK.md';

const PP5_DO_RUNBOOK_SECTIONS = [
    '## 1. Purpose and scope',
    '## 2. Preconditions',
    '## 3. Supported reference paths',
    '## 4. Required config/state inspection',
    '## 5. Kill-switch engagement',
    '## 6. Kill-switch verification',
    '## 7. Rollback/recovery',
    '## 8. Post-rollback clean-state verification',
    '## 9. Privacy / zero-egress verification',
    '## 10. Secret-handling verification',
    '## 11. Credential-rotation procedure',
    '## 12. Safety-ceiling verification',
    '## 13. Synthetic fault / alert-emission verification',
    '## 14. Correlation fields to inspect',
    '## 15. Informational spend guidance',
    '## 16. Degraded-observability behavior',
    '## 17. Evidence checklist',
    '## 18. Failure/escalation conditions',
    '## 19. Explicit PP-T6 handoff boundary',
];

function pp5doPhpFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

beforeEach(function () {
    Storage::fake('local');
    config(['processing.external_kill_switch' => false]);
    config(['transcription.provider_selection' => 'self_hosted']);
    config(['translation.provider_selection' => 'self_hosted']);
});

it('degraded observability: LogContext assembly never throws on incomplete models', function () {
    $transcriptionContext = LogContext::forTranscription(new Transcription);
    $translationContext = LogContext::forTranslation(new Translation);
    // Unsaved models carry null ids, so the ordinal lookup cannot complete
    // and deterministically takes the documented never-throw null path.
    $attemptNumber = LogContext::attemptNumber(new Transcription, new ProcessingJob);

    expect($transcriptionContext)->toBeArray()
        ->and($translationContext)->toBeArray()
        ->and($attemptNumber)->toBeNull();
});

it('degraded observability: selection is invariant to observability listeners', function () {
    $transport = new FakeReferenceExternalTransport;
    app()->bind('transcription.providers.external_reference', fn () => new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: 'fixture-token-pp5-do-not-a-secret',
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    ));

    config(['transcription.provider_selection' => 'external_reference']);

    $before = (new TranscriptionProviderResolver)->resolve('pp5-observable-1');

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $after = (new TranscriptionProviderResolver)->resolve('pp5-observable-1');

    // Logging is a write-only side effect: attaching a listener changes the
    // emitted records, never the selected provider.
    expect($before)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class)
        ->and($after)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class)
        ->and($records)->not->toBe([]);
});

it('AC8 frozen interfaces: provider contracts keep exact signatures', function () {
    $transcribe = new ReflectionMethod(TranscriptionProvider::class, 'transcribe');
    $translate = new ReflectionMethod(TranslationProvider::class, 'translate');

    expect($transcribe->getParameters())->toHaveCount(1)
        ->and($transcribe->getParameters()[0]->getType()->getName())->toBe(TranscriptionInvocation::class)
        ->and($transcribe->getReturnType()->getName())->toBe(NormalizedTranscript::class)
        ->and($translate->getParameters())->toHaveCount(1)
        ->and($translate->getParameters()[0]->getType()->getName())->toBe(TranslationInvocation::class)
        ->and($translate->getReturnType()->getName())->toBe(TranslationResult::class);
});

it('AC8 config freeze: provider key sets are exactly the PP-T2-owned sets', function () {
    expect(array_keys(config('processing')))->toBe(['external_kill_switch'])
        ->and(array_keys(config('transcription')))->toBe([
            'worker_url',
            'worker_token',
            'contract_version',
            'provider_selection',
            'model',
            'queue',
            'queue_connection',
            'timeout_seconds',
            'job_timeout_seconds',
            'retry_after_seconds',
            'attempt_stale_seconds',
            'prepared_audio_retention',
            'shared_media_root',
        ])
        ->and(array_keys(config('translation')))->toBe([
            'worker_url',
            'worker_token',
            'provider',
            'provider_selection',
            'model',
            'queue',
            'queue_connection',
            'timeout_seconds',
            'job_timeout_seconds',
            'retry_after_seconds',
            'attempt_stale_seconds',
            'contract_version',
        ]);
});

it('AC8 no production external bindings exist by default', function () {
    expect(app()->bound('transcription.providers.external_reference'))->toBeFalse()
        ->and(app()->bound('translation.providers.external_reference'))->toBeFalse();
});

it('AC8 no migration ships provider, chunk-audit, or operational schema', function () {
    $matches = array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        static fn (string $file): bool => (bool) preg_match('/provider|chunk_audit|operational/i', basename($file)),
    ));

    expect($matches)->toBe([]);
});

it('AC9 spend is informational-only: no billing, budget, quota, pricing, or spend code exists', function () {
    $targets = array_merge(
        pp5doPhpFiles(app_path('Transcription')),
        pp5doPhpFiles(app_path('Translation')),
        pp5doPhpFiles(app_path('Providers')),
        pp5doPhpFiles(app_path('Jobs')),
        [config_path('processing.php'), config_path('transcription.php'), config_path('translation.php')],
    );

    $hits = [];

    foreach ($targets as $file) {
        $code = (string) file_get_contents($file);

        if (preg_match('/billing|budget|quota|pric|spend/i', $code, $match)) {
            $hits[] = basename($file).':'.$match[0];
        }
    }

    expect($hits)->toBe([]);
});

it('PP-T6 leakage audit: final gate boundary stays authorized (no runtime leakage)', function () {
    // Corrective cycle PP-T6-REV-01 (PP-T6 Step 2, LOW, test-only): the
    // PP-T5-era version of this audit pinned the PP-T6 contract to
    // BACKLOG / NOT AUTHORIZED with no verification/pp-t6 dir. That pin is
    // stale once PP-T6 executes under DECISION-PP-T6-EXECUTION-AUTHORIZATION-001:
    // the legal lifecycle is BACKLOG → READY → IN_PROGRESS → REVIEW →
    // VERIFIED → DONE, and Step 2 is required to publish evidence under
    // verification/pp-t6/. This audit now guards the boundary that still
    // matters: any departure from BACKLOG and any gate evidence must cite
    // the execution authorization, and app/ must stay free of PP-T6 runtime.
    $contract = (string) file_get_contents(base_path('tasks/PP-T6-integration-compatibility-verification.md'));

    if (! str_contains($contract, 'BACKLOG')) {
        expect($contract)->toContain('DECISION-PP-T6-EXECUTION-AUTHORIZATION-001');
    }

    if (is_dir(base_path('verification/pp-t6'))) {
        expect($contract)->toContain('DECISION-PP-T6-EXECUTION-AUTHORIZATION-001');
    }

    $hits = [];

    foreach (pp5doPhpFiles(app_path()) as $file) {
        if (str_contains((string) file_get_contents($file), 'pp-t6') || str_contains((string) file_get_contents($file), 'PP-T6')) {
            $hits[] = basename($file);
        }
    }

    expect($hits)->toBe([]);
});

it('AC10 runbook artifact exists with all required executable sections', function () {
    $path = base_path(PP5_DO_RUNBOOK);

    expect(file_exists($path))->toBeTrue();

    $runbook = (string) file_get_contents($path);

    foreach (PP5_DO_RUNBOOK_SECTIONS as $section) {
        expect($runbook)->toContain($section);
    }
});
