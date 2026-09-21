<?php

namespace App\Translation;

use App\Models\Transcription;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use Illuminate\Support\Facades\DB;

/**
 * Atomic, idempotent writer for segment-aligned translations (ADR-022,
 * D5-05/D5-06).
 *
 * Guarantees:
 * - a translation and its segments are persisted in one transaction;
 * - repeated writes for the same (transcription, target) do not duplicate rows;
 * - a completed translation is never overwritten;
 * - the source transcription and its segments are never modified.
 */
class TranslationResultWriter
{
    /**
     * Persist a provider result against a source transcription.
     *
     * @param  int|null  $translationId  When provided, the writer targets an
     *                                   existing non-completed translation row rather than resolving by target.
     */
    public function persist(
        Transcription $transcription,
        TranslationResult $result,
        ?int $translationId = null,
    ): Translation {
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
            $translation = Translation::query()
                ->whereKey($translationId)
                ->where('transcription_id', $transcription->getKey())
                ->lockForUpdate()
                ->first();

            if ($translation !== null) {
                return $translation;
            }
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
