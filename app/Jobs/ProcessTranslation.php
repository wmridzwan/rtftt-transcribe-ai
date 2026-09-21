<?php

namespace App\Jobs;

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Asynchronous translation orchestration (ADR-022).
 *
 * The payload contains only small server-controlled identifiers. At-least-once
 * delivery is made safe by a CAS claim on the translation row plus the
 * idempotent, atomic, alignment-enforcing TranslationResultWriter. The source
 * transcription is never modified.
 */
class ProcessTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Phase 5 implements manual domain retry only (mirrors ADR-018). A single
     * transport delivery keeps failures terminal and avoids duplicate
     * inference.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $translationId,
        public readonly int $transcriptionId,
    ) {
        if ($translationId <= 0 || $transcriptionId <= 0) {
            throw new InvalidArgumentException('Translation and transcription identifiers must be positive integers.');
        }
    }

    public function handle(
        TranslationProvider $provider,
        TranslationResultWriter $writer,
    ): void {
        $translation = Translation::query()->find($this->translationId);

        if ($translation === null || $translation->transcription_id !== $this->transcriptionId) {
            $this->skip('translation missing or mismatched');

            return;
        }

        if (in_array($translation->status, [
            TranslationStatus::Completed,
            TranslationStatus::Failed,
        ], true)) {
            $this->skip('translation already terminal');

            return;
        }

        if (! $this->claim($translation)) {
            $this->skip('translation already claimed');

            return;
        }

        $transcription = Transcription::query()
            ->with('segments')
            ->find($this->transcriptionId);

        if ($transcription === null) {
            $this->fail($translation, TranslationFailure::InvalidRequest, 'The source transcription is missing.');

            return;
        }

        $invocation = TranslationInvocation::create(
            transcriptionId: $transcription->getKey(),
            targetLanguage: $translation->target_language,
            segments: $this->sourceSegments($transcription),
            translationId: $translation->getKey(),
        );

        Log::info('Translation provider invocation started.', [
            'transcription_id' => $transcription->getKey(),
            'translation_id' => $translation->getKey(),
            'target_language' => $translation->target_language->value,
            'request_id' => $invocation->requestId,
        ]);

        try {
            $result = $provider->translate($invocation);
        } catch (TranslationException $exception) {
            $this->fail($translation, $exception->failure, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::error('Translation provider raised an unexpected failure.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail($translation, TranslationFailure::ProcessingFailed, 'Unexpected translation failure.');

            return;
        }

        try {
            $writer->persist($transcription, $result, $translation->getKey());
        } catch (TranslationException $exception) {
            $this->fail($translation, $exception->failure, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::error('Translation result persistence raised an unexpected failure.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail($translation, TranslationFailure::PersistenceFailed, 'Translation persistence failed.');

            return;
        }

        Log::info('Translation job completed.', [
            'translation_id' => $translation->getKey(),
        ]);
    }

    /**
     * @return list<TranslationSegmentData>
     */
    private function sourceSegments(Transcription $transcription): array
    {
        return array_values($transcription->segments
            ->map(fn (TranscriptionSegment $segment): TranslationSegmentData => new TranslationSegmentData(
                segmentIndex: (int) $segment->segment_index,
                startSeconds: (float) $segment->start_seconds,
                endSeconds: (float) $segment->end_seconds,
                text: (string) $segment->text,
                sourceLanguage: $segment->language,
            ))
            ->all());
    }

    /**
     * Atomically claim a pending/queued translation. A duplicate delivery that
     * arrives after another worker already claimed (or finished) observes zero
     * affected rows and no-ops.
     */
    private function claim(Translation $translation): bool
    {
        $claimed = Translation::query()
            ->whereKey($translation->getKey())
            ->whereIn('status', [
                TranslationStatus::Pending->value,
                TranslationStatus::Queued->value,
            ])
            ->update([
                'status' => TranslationStatus::Translating->value,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $translation->refresh();

        return true;
    }

    private function fail(Translation $translation, TranslationFailure $failure, string $safeMessage): void
    {
        DB::transaction(function () use ($translation, $failure): void {
            $fresh = Translation::query()->whereKey($translation->getKey())->lockForUpdate()->first();

            if ($fresh !== null && $fresh->status !== TranslationStatus::Completed) {
                $fresh->forceFill([
                    'status' => TranslationStatus::Failed,
                    'failure_code' => $failure,
                    'completed_at' => now(),
                ])->save();
            }
        });

        Log::warning('Translation job failed.', [
            'translation_id' => $translation->getKey(),
            'failure' => $failure->value,
            'message' => $safeMessage,
        ]);
    }

    private function skip(string $reason): void
    {
        Log::info('Translation job skipped.', [
            'translation_id' => $this->translationId,
            'transcription_id' => $this->transcriptionId,
            'reason' => $reason,
        ]);
    }
}
