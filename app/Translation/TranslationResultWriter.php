<?php

namespace App\Translation;

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atomic, idempotent writer for segment-aligned translations (ADR-022,
 * D5-05/D5-06).
 *
 * Guarantees:
 * - a translation and its segments are persisted in one transaction;
 * - persistence targets one explicit translation row and target;
 * - completion is only legal from the `translating` state (lifecycle-enforced);
 * - persisted alignment (index, timestamps, source language) is copied from the
 *   source transcript rows, never from provider output;
 * - a completed translation is never overwritten;
 * - the source transcription and its segments are never modified.
 */
class TranslationResultWriter
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 0.0005;

    public function persist(
        Transcription $transcription,
        TranslationResult $result,
        int $translationId,
    ): Translation {
        $sourceByIndex = $this->validatedSourceSegments($transcription, $result);

        return DB::transaction(function () use ($transcription, $result, $translationId, $sourceByIndex): Translation {
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

            if ($translation->status === TranslationStatus::Completed) {
                return $translation;
            }

            if ($translation->status === TranslationStatus::Failed) {
                throw new TranslationException(
                    TranslationFailure::InvalidRequest,
                    'A failed translation must be requeued before it can be completed.',
                );
            }

            if ($translation->status !== TranslationStatus::Translating) {
                throw new TranslationException(
                    TranslationFailure::InvalidRequest,
                    'A translation can only complete from the translating state.',
                );
            }

            TranslationLifecycle::assertValidTransition($translation->status, TranslationStatus::Completed);

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

            $this->replaceSegments($translation, $result, $sourceByIndex);

            return $translation;
        });
    }

    /**
     * Validate that the translated result matches the source segment set exactly
     * (count, indices, and timestamps) and return the authoritative source rows
     * keyed by segment index.
     *
     * @return Collection<int, TranscriptionSegment>
     */
    private function validatedSourceSegments(Transcription $transcription, TranslationResult $result): Collection
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

        return $sourceByIndex;
    }

    /**
     * @param  Collection<int, TranscriptionSegment>  $sourceByIndex
     */
    private function replaceSegments(Translation $translation, TranslationResult $result, Collection $sourceByIndex): void
    {
        $translation->segments()->delete();

        foreach ($result->segments as $segment) {
            $source = $sourceByIndex->get($segment->segmentIndex);

            if ($source === null) {
                continue;
            }

            TranslationSegment::query()->create([
                'translation_id' => $translation->getKey(),
                'segment_index' => $source->segment_index,
                'start_seconds' => $source->start_seconds,
                'end_seconds' => $source->end_seconds,
                'text' => $segment->text,
                'source_language' => $source->language,
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
