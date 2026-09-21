<?php

namespace App\Translation;

use App\Models\Transcription;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Atomic, idempotent writer for segment-aligned translations (ADR-022,
 * D5-05/D5-06).
 *
 * Guarantees:
 * - a translation and its segments are persisted in one transaction;
 * - persisted segments are aligned to the source transcription's segment set;
 * - repeated writes for the same (transcription, target) do not duplicate rows;
 * - a completed translation is never overwritten;
 * - the source transcription and its segments are never modified.
 */
class TranslationResultWriter
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 0.0005;

    /**
     * Persist a provider result against a source transcription.
     *
     * @param  int|null  $translationId  When provided, the writer targets an
     *                                   existing non-completed, same-target
     *                                   translation row rather than resolving
     *                                   by target.
     */
    public function persist(
        Transcription $transcription,
        TranslationResult $result,
        ?int $translationId = null,
    ): Translation {
        $this->assertAligned($transcription, $result);

        try {
            return DB::transaction(function () use ($transcription, $result, $translationId): Translation {
                $translation = $this->resolveTarget($transcription, $result, $translationId);

                if ($translation->isCompleted()) {
                    return $translation;
                }

                $translation->forceFill([
                    'status' => TranslationStatus::Completed,
                    'source_language' => $translation->source_language
                        ?? $this->sourceLanguage($transcription),
                    'provider' => $result->provider,
                    'model' => $result->model,
                    'full_text' => $result->fullText,
                    'failure_code' => null,
                    'started_at' => $translation->started_at ?? now(),
                    'completed_at' => now(),
                ])->save();

                $this->replaceSegments($translation, $result);

                return $translation;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new TranslationException(
                TranslationFailure::PersistenceFailed,
                'A competing translation write prevented persistence.',
                $exception,
            );
        }
    }

    /**
     * Enforce that the translated result matches the source segment set exactly
     * (count, indices, and inherited timestamps). Re-segmentation is not
     * permitted in Phase 5 (D5-01).
     */
    private function assertAligned(Transcription $transcription, TranslationResult $result): void
    {
        $source = $transcription->segments()->orderBy('segment_index')->get();

        if ($source->count() !== count($result->segments)) {
            throw new TranslationException(
                TranslationFailure::MissingSegments,
                'Translated segment count does not match the source transcript.',
            );
        }

        $sourceByIndex = $source->keyBy('segment_index');

        foreach ($result->segments as $segment) {
            $row = $sourceByIndex->get($segment->segmentIndex);

            if ($row === null) {
                throw new TranslationException(
                    TranslationFailure::MissingSegments,
                    'Translated segment index does not exist in the source transcript.',
                );
            }

            if (abs((float) $row->start_seconds - $segment->startSeconds) > self::TIMESTAMP_TOLERANCE_SECONDS
                || abs((float) $row->end_seconds - $segment->endSeconds) > self::TIMESTAMP_TOLERANCE_SECONDS) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    'Translated segment timestamps do not match the source transcript.',
                );
            }
        }
    }

    private function resolveTarget(
        Transcription $transcription,
        TranslationResult $result,
        ?int $translationId,
    ): Translation {
        $query = Translation::query()
            ->where('transcription_id', $transcription->getKey())
            ->where('target_language', $result->targetLanguage->value);

        $completed = (clone $query)
            ->where('status', TranslationStatus::Completed->value)
            ->lockForUpdate()
            ->first();

        if ($completed !== null) {
            return $completed;
        }

        if ($translationId !== null) {
            return $this->resolveByAttemptId($transcription, $result, $translationId);
        }

        $active = (clone $query)
            ->whereIn('status', [
                TranslationStatus::Pending->value,
                TranslationStatus::Queued->value,
                TranslationStatus::Translating->value,
            ])
            ->lockForUpdate()
            ->first();

        if ($active !== null) {
            return $active;
        }

        return new Translation([
            'transcription_id' => $transcription->getKey(),
            'target_language' => $result->targetLanguage,
            'status' => TranslationStatus::Pending,
        ]);
    }

    private function resolveByAttemptId(
        Transcription $transcription,
        TranslationResult $result,
        int $translationId,
    ): Translation {
        $translation = Translation::query()
            ->whereKey($translationId)
            ->where('transcription_id', $transcription->getKey())
            ->lockForUpdate()
            ->first();

        if ($translation === null || $translation->target_language !== $result->targetLanguage) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation row does not match the requested source and target.',
            );
        }

        if ($translation->status === TranslationStatus::Failed) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'A failed translation must be requeued before it can be completed.',
            );
        }

        return $translation;
    }

    private function replaceSegments(Translation $translation, TranslationResult $result): void
    {
        $translation->segments()->delete();

        foreach ($result->segments as $segment) {
            TranslationSegment::query()->create([
                'translation_id' => $translation->getKey(),
                'segment_index' => $segment->segmentIndex,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => $segment->text,
                'source_language' => $segment->sourceLanguage,
            ]);
        }
    }

    private function sourceLanguage(Transcription $transcription): ?LanguageIdentifier
    {
        if ($transcription->detected_language === null || $transcription->detected_language === '') {
            return null;
        }

        return LanguageIdentifier::fromBcp47($transcription->detected_language);
    }
}
