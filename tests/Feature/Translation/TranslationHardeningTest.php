<?php

use App\Actions\TranslationDispatcher;
use App\Actions\TranslationOrchestrator;
use App\Actions\TranslationRetry;
use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingTranslationProvider;

/*
 * P5-004B pre-UI hardening: request convergence, queued-row re-dispatch,
 * retry compare-and-set, post-claim failure contract, and writer alignment.
 */

// --- request convergence ----------------------------------------------------

it('converges on the winning row when a concurrent first request commits between the check and the insert', function (): void {
    Queue::fake();

    $transcription = translationSource();
    $loserInsertRejected = false;
    $rivalId = null;

    // The loser's insert is rejected by the unique index...
    Translation::creating(function () use (&$loserInsertRejected): void {
        if ($loserInsertRejected) {
            return;
        }

        $loserInsertRejected = true;

        throw new UniqueConstraintViolationException(
            'sqlite',
            'insert into "translations" values (?)',
            [],
            new PDOException('UNIQUE constraint failed'),
        );
    });

    // ...because the rival's row is already committed when the loser looks again.
    DB::beforeStartingTransaction(function () use (&$loserInsertRejected, &$rivalId, $transcription): void {
        if ($loserInsertRejected && $rivalId === null) {
            $rivalId = DB::table('translations')->insertGetId([
                'transcription_id' => $transcription->getKey(),
                'target_language' => 'ms',
                'status' => 'queued',
                'attempt_token' => 'rival-token',
                'dispatched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    expect($rivalId)->not->toBeNull()
        ->and($translation->getKey())->toBe($rivalId)
        ->and($translation->attempt_token)->toBe('rival-token')
        ->and(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);

    // The rival already dispatched its own attempt; the loser dispatches nothing.
    Queue::assertNothingPushed();
});

it('reports a typed failure instead of a raw constraint exception when convergence cannot resolve', function (): void {
    Queue::fake();

    $transcription = translationSource();

    // Every insert loses to a phantom row that the lookup cannot see (a status
    // outside the active set is excluded from the lookup but still unique).
    Translation::creating(function (): void {
        throw new UniqueConstraintViolationException(
            'sqlite',
            'insert into "translations" values (?)',
            [],
            new PDOException('UNIQUE constraint failed'),
        );
    });

    expect(fn () => app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay))
        ->toThrow(TranslationException::class);
});

it('keeps the source, ownership and target guards on request', function (): void {
    Queue::fake();

    $transcription = translationSource();
    $transcription->forceFill(['status' => 'transcribing'])->save();

    expect(fn () => app(TranslationOrchestrator::class)->request($transcription->fresh(), TranslationTarget::Malay))
        ->toThrow(TranslationException::class);

    expect(Translation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

// --- queued-row re-dispatch -------------------------------------------------

it('re-dispatches the same attempt when the first dispatch failed, without a second row', function (): void {
    $transcription = translationSource();

    config()->set('translation.queue_connection', 'unconfigured-connection');

    expect(fn () => app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay))
        ->toThrow(TranslationException::class);

    $stranded = Translation::query()->sole();

    expect($stranded->status)->toBe(TranslationStatus::Queued)
        ->and($stranded->dispatched_at)->toBeNull()
        ->and($stranded->isAwaitingDispatch())->toBeTrue();

    config()->set('translation.queue_connection', null);
    Queue::fake();

    $again = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    expect($again->getKey())->toBe($stranded->getKey())
        ->and($again->attempt_token)->toBe($stranded->attempt_token)
        ->and(Translation::query()->count())->toBe(1)
        ->and($again->fresh()->dispatched_at)->not->toBeNull();

    Queue::assertPushed(ProcessTranslation::class, fn (ProcessTranslation $job): bool => $job->attemptToken === $stranded->attempt_token);
    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('does not re-dispatch a queued attempt that was already dispatched', function (): void {
    Queue::fake();

    $transcription = translationSource();
    $orchestrator = app(TranslationOrchestrator::class);

    $orchestrator->request($transcription, TranslationTarget::Malay);
    $orchestrator->request($transcription, TranslationTarget::Malay);

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('re-dispatches a stranded queued attempt through retry and keeps the attempt token', function (): void {
    Queue::fake();

    $stranded = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => 'stranded-token',
        'dispatched_at' => null,
    ]);

    $result = app(TranslationRetry::class)->retry($stranded);

    expect($result->getKey())->toBe($stranded->getKey())
        ->and($result->attempt_token)->toBe('stranded-token')
        ->and($result->dispatched_at)->not->toBeNull()
        ->and(Translation::query()->count())->toBe(1);

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('records a retry dispatch failure as a typed error and leaves a re-dispatchable row', function (): void {
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    config()->set('translation.queue_connection', 'unconfigured-connection');

    expect(fn () => app(TranslationRetry::class)->retry($failed))->toThrow(TranslationException::class);

    $row = $failed->fresh();

    expect($row->status)->toBe(TranslationStatus::Queued)
        ->and($row->isAwaitingDispatch())->toBeTrue();

    config()->set('translation.queue_connection', null);
    Queue::fake();

    app(TranslationRetry::class)->retry($row);

    expect($row->fresh()->dispatched_at)->not->toBeNull();
    Queue::assertPushed(ProcessTranslation::class, 1);
});

// --- post-claim failure contract --------------------------------------------

it('records a setup failure after the claim through the failure taxonomy instead of stranding the row', function (): void {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => 'setup-token',
    ]);

    // A source segment with an unreadable language value breaks invocation setup
    // AFTER the job has claimed its attempt.
    DB::table('transcription_segments')->where('transcription_id', $transcription->getKey())->update(['language' => 'xx-invalid']);

    $provider = new RecordingTranslationProvider(alignedTranslationResult());

    (new ProcessTranslation($translation->getKey(), $transcription->getKey(), 'setup-token'))
        ->handle($provider, app(TranslationResultWriter::class));

    $fresh = $translation->fresh();

    expect($provider->calls)->toBe(0)
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProcessingFailed)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
});

// --- retry compare-and-set --------------------------------------------------

it('does not requeue a currently non-retryable failure from a stale retryable in-memory model', function (): void {
    Queue::fake();

    $failed = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    $stale = $failed->fresh();

    // Meanwhile another actor retried and the new attempt failed non-retryably.
    DB::table('translations')->where('id', $failed->getKey())->update([
        'failure_code' => 'MALFORMED_OUTPUT',
        'attempt_token' => 'newer-token',
    ]);

    expect(app(TranslationRetry::class)->isEligible($stale))->toBeTrue();
    expect(fn () => app(TranslationRetry::class)->retry($stale))->toThrow(TranslationException::class);

    $fresh = $failed->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::MalformedOutput)
        ->and($fresh->attempt_token)->toBe('newer-token');

    Queue::assertNothingPushed();
});

it('enforces retryability in the persisted compare-and-set itself', function (): void {
    Queue::fake();

    $failed = Translation::factory()->failed()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    // The row turns non-retryable AFTER retry() read it and judged it eligible,
    // but BEFORE the compare-and-set runs (fired once, right after the sibling
    // lookup that sits between the eligibility read and the CAS).
    $armed = true;
    DB::listen(function ($query) use (&$armed, $failed): void {
        if ($armed && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '"status" in (')) {
            $armed = false;
            DB::table('translations')->where('id', $failed->getKey())->update(['failure_code' => 'CONFIGURATION_ERROR']);
        }
    });

    expect(fn () => app(TranslationRetry::class)->retry($failed))->toThrow(TranslationException::class);

    $fresh = $failed->fresh();

    expect($armed)->toBeFalse()
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ConfigurationError);

    Queue::assertNothingPushed();
});

it('converges retry on a pending or completed sibling instead of leaking a constraint exception', function (string $siblingStatus): void {
    Queue::fake();

    $transcription = translationSource();

    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_FAILED',
    ]);

    $sibling = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => $siblingStatus,
        'dispatched_at' => now(),
    ]);

    $result = app(TranslationRetry::class)->retry($failed);

    expect($result->getKey())->toBe($sibling->getKey())
        ->and($failed->fresh()->status)->toBe(TranslationStatus::Failed);
})->with(['pending', 'completed']);

// --- writer: identity and source-authoritative alignment ---------------------

it('rejects a nonexistent translation id without writing', function (): void {
    $transcription = writerSource();

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, writerResult(), 987654, 'any-token'))
        ->toThrow(TranslationException::class);

    expect(Translation::query()->count())->toBe(0)
        ->and(TranslationSegment::query()->count())->toBe(0);
});

it('rejects a translation id that belongs to another transcription', function (): void {
    $transcription = writerSource();
    $foreign = writerTranslation(writerSource(), 'translating', 'ms');

    expect(fn () => writerPersist($transcription, writerResult(), $foreign))->toThrow(TranslationException::class);

    expect($foreign->fresh()->status)->toBe(TranslationStatus::Translating)
        ->and($foreign->segments()->count())->toBe(0);
});

it('rejects a wrong-target id even when a completed same-target row exists', function (): void {
    $transcription = writerSource();

    Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
    ]);
    $wrongTarget = writerTranslation($transcription, 'translating', 'en');

    expect(fn () => writerPersist($transcription, writerResult(), $wrongTarget))->toThrow(TranslationException::class);

    expect($wrongTarget->fresh()->status)->toBe(TranslationStatus::Translating);
});

it('persists exact source-authoritative timestamps when the provider drifts within tolerance', function (): void {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    // Within the 0.0005 s validation tolerance, but not equal to the source.
    $drifted = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'Hai semua',
        segments: [
            new TranslationSegmentData(0, 0.0004, 4.9994, 'Hai semua', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.9994, 9.5004, 'Selamat datang', LanguageIdentifier::English),
        ],
        provider: 'self-hosted',
        model: 'translation-test',
    );

    writerPersist($transcription, $drifted, $translation);

    $translated = DB::table('translation_segments')->where('translation_id', $translation->getKey())->orderBy('segment_index')->get();
    $source = DB::table('transcription_segments')->where('transcription_id', $transcription->getKey())->orderBy('segment_index')->get();

    expect($translated)->toHaveCount(2);

    foreach ($translated as $index => $row) {
        expect((float) $row->start_seconds)->toBe((float) $source[$index]->start_seconds)
            ->and((float) $row->end_seconds)->toBe((float) $source[$index]->end_seconds)
            ->and($row->segment_index)->toBe($source[$index]->segment_index);
    }

    expect((float) $translated[0]->start_seconds)->not->toBe(0.0004);
});

// --- P4B-1/P4B-2/P4B-3: dispatch stamp and convergence guard -----------------

it('does not surface a dispatch failure when only the dispatched_at stamp fails (P4B-1)', function (): void {
    Queue::fake();

    $transcription = translationSource();

    // The queue push succeeds, but the bookkeeping stamp write fails.
    DB::beforeExecuting(function (string $query): void {
        if (preg_match('/update\s+"translations"\s+set\s+"dispatched_at"/i', $query) === 1) {
            throw new QueryException('sqlite', $query, [], new PDOException('stamp write failed'));
        }
    });

    $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    // No raw fatal: the request returns the still-valid, queued attempt.
    expect($translation)->toBeInstanceOf(Translation::class)
        ->and($translation->status)->toBe(TranslationStatus::Queued)
        ->and($translation->fresh()->dispatched_at)->toBeNull()
        ->and($translation->fresh()->isAwaitingDispatch())->toBeTrue();

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('does not stamp dispatched_at for a superseded attempt token (P4B-2)', function (): void {
    Queue::fake();

    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => 'newer-token',
        'dispatched_at' => null,
    ]);

    app(TranslationDispatcher::class)->dispatch($translation, 'old-token');

    expect($translation->fresh()->dispatched_at)->toBeNull();

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('propagates an unrelated database error instead of treating it as a concurrency retry (P4B-3)', function (): void {
    Queue::fake();

    $transcription = translationSource();

    Translation::creating(function (): void {
        throw new QueryException(
            'sqlite',
            'insert into "translations" ("target_language") values (?)',
            [],
            new PDOException('unrelated-db-failure'),
        );
    });

    $thrown = null;

    try {
        app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull()
        ->toBeInstanceOf(QueryException::class)
        ->and($thrown)->not->toBeInstanceOf(TranslationException::class)
        ->and($thrown->getMessage())->toContain('unrelated-db-failure');

    Queue::assertNothingPushed();
});

// --- P5-004C: job timeout, failed() fence, persistence taxonomy, refresh -----

it('sets an explicit job timeout safely between provider timeout and retry_after (P5-004C)', function (): void {
    config()->set('translation.timeout_seconds', 300);
    config()->set('translation.job_timeout_seconds', 330);
    config()->set('translation.retry_after_seconds', 420);

    $job = new ProcessTranslation(1, 1, 'token');

    expect($job->timeout)->toBe(330)
        ->and($job->timeout)->toBeGreaterThan(300)
        ->and($job->timeout)->toBeLessThan(420);
});

it('marks the current attempt failed through the token-fenced failed() handler', function (): void {
    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
        'attempt_token' => 'current-token',
    ]);

    (new ProcessTranslation($translation->getKey(), $translation->transcription_id, 'current-token'))
        ->failed(new RuntimeException('worker timeout'));

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProcessingFailed)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
});

it('does not let a killed stale job mark a newer attempt failed', function (): void {
    $translation = Translation::factory()->create([
        'transcription_id' => translationSource()->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => 'newer-token',
    ]);

    (new ProcessTranslation($translation->getKey(), $translation->transcription_id, 'old-token'))
        ->failed(new RuntimeException('worker timeout'));

    expect($translation->fresh()->status)->toBe(TranslationStatus::Queued)
        ->and($translation->fresh()->attempt_token)->toBe('newer-token');
});

it('maps a transient persistence/concurrency failure to a retryable failure', function (): void {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $writer = new class extends TranslationResultWriter
    {
        public function persist(
            Transcription $transcription,
            TranslationResult $result,
            int $translationId,
            string $attemptToken,
        ): Translation {
            throw new QueryException('sqlite', 'insert into "translation_segments" values (?)', [], new PDOException('database is locked'));
        }
    };

    $provider = new RecordingTranslationProvider(alignedTranslationResult());

    (new ProcessTranslation($translation->getKey(), $transcription->getKey(), (string) $translation->attempt_token))
        ->handle($provider, $writer);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProcessingFailed)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
});

it('keeps a deterministic constraint failure non-retryable', function (): void {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $writer = new class extends TranslationResultWriter
    {
        public function persist(
            Transcription $transcription,
            TranslationResult $result,
            int $translationId,
            string $attemptToken,
        ): Translation {
            throw new QueryException(
                'sqlite',
                'insert into "translation_segments" values (?)',
                [],
                new PDOException('UNIQUE constraint failed: translation_segments.translation_id, translation_segments.segment_index'),
            );
        }
    };

    $provider = new RecordingTranslationProvider(alignedTranslationResult());

    (new ProcessTranslation($translation->getKey(), $transcription->getKey(), (string) $translation->attempt_token))
        ->handle($provider, $writer);

    $fresh = $translation->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::PersistenceFailed)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeFalse();
});

it('records a post-claim refresh failure through the failure taxonomy (P5-004C)', function (): void {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'attempt_token' => 'refresh-token',
    ]);

    // A model whose refresh() fails after the claim succeeded.
    $stub = new class extends Translation
    {
        public function refresh(): static
        {
            throw new QueryException('sqlite', 'select * from "translations"', [], new PDOException('refresh failed'));
        }
    };
    $stub->setRawAttributes($translation->getAttributes(), true);
    $stub->exists = true;

    $job = new ProcessTranslation($translation->getKey(), $transcription->getKey(), 'refresh-token');

    $claimed = (new ReflectionMethod($job, 'claim'))->invoke($job, $stub);

    $fresh = $translation->fresh();

    expect($claimed)->toBeFalse()
        ->and($fresh->status)->toBe(TranslationStatus::Failed)
        ->and($fresh->failure_code)->toBe(TranslationFailure::ProcessingFailed)
        ->and(app(TranslationRetry::class)->isEligible($fresh))->toBeTrue();
});
