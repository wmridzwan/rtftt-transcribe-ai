<?php

namespace App\Comparison;

use App\Editing\RevisionSegmentData;
use App\Editing\TranscriptRevision;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationStatus;

/**
 * Builds the read-only P6-007 comparison view model.
 *
 * Alignment baseline: persisted Phase 5 `translation_segments.segment_index`
 * aligns to the **machine** `transcription_segments.segment_index`. A persisted
 * translation is therefore a translation of the machine source. The active
 * revision is aligned to a machine index only where its segment identity is
 * machine provenance (`machine:<index>`); structurally changed revisions are
 * left unaligned rather than mapped by array index.
 */
final class ComparisonBuilder
{
    public function build(Transcription $transcription, ?TranscriptRevision $activeRevision): TranscriptComparison
    {
        $machineSegments = $transcription->segments()->orderBy('segment_index')->get();

        $translation = $this->latestCompletedTranslation($transcription);

        /** @var array<int, string> $translatedByIndex */
        $translatedByIndex = [];

        if ($translation !== null) {
            foreach ($translation->segments()->orderBy('segment_index')->get() as $translationSegment) {
                $translatedByIndex[(int) $translationSegment->segment_index] = $translationSegment->text;
            }
        }

        /** @var array<int, RevisionSegmentData> $revisionByMachineIndex */
        $revisionByMachineIndex = [];

        /** @var list<RevisionSegmentData> $unalignedRevisionSegments */
        $unalignedRevisionSegments = [];

        if ($activeRevision !== null) {
            foreach ($activeRevision->orderedSegments() as $segment) {
                $index = self::machineIndex($segment->identity->key());

                if ($index === null) {
                    $unalignedRevisionSegments[] = $segment;

                    continue;
                }

                $revisionByMachineIndex[$index] = $segment;
            }
        }

        $rows = [];

        foreach ($machineSegments as $machineSegment) {
            $index = (int) $machineSegment->segment_index;
            $revisionSegment = $revisionByMachineIndex[$index] ?? null;

            $rows[] = new ComparisonRow(
                machineIndex: $index,
                machineText: $machineSegment->text,
                machineLanguage: $machineSegment->language->value,
                translatedText: $translatedByIndex[$index] ?? null,
                revisionPosition: $revisionSegment?->position,
                revisionText: $revisionSegment?->text,
                revisionAligned: $revisionSegment !== null,
            );
        }

        foreach ($unalignedRevisionSegments as $revisionSegment) {
            $rows[] = new ComparisonRow(
                machineIndex: null,
                machineText: null,
                machineLanguage: null,
                translatedText: null,
                revisionPosition: $revisionSegment->position,
                revisionText: $revisionSegment->text,
                revisionAligned: false,
            );
        }

        return new TranscriptComparison(
            rows: $rows,
            hasActiveRevision: $activeRevision !== null,
            activeRevisionVersion: $activeRevision?->version,
            hasTranslation: $translation !== null,
            translationTargetLanguage: $translation?->target_language->value,
        );
    }

    private function latestCompletedTranslation(Transcription $transcription): ?Translation
    {
        return $transcription->translations()
            ->where('status', TranslationStatus::Completed->value)
            ->orderByDesc('id')
            ->first();
    }

    private static function machineIndex(string $identityKey): ?int
    {
        if (! str_starts_with($identityKey, 'machine:')) {
            return null;
        }

        $suffix = substr($identityKey, strlen('machine:'));

        return $suffix !== '' && ctype_digit($suffix) ? (int) $suffix : null;
    }
}
