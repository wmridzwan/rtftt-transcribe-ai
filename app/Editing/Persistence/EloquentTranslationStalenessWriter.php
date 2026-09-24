<?php

namespace App\Editing\Persistence;

use App\Editing\TranslationStalenessReason;
use App\Editing\TranslationStalenessWriter;
use App\Models\Transcription;
use App\Models\Translation;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Persists the canonical P6-005 translation-invalidation lifecycle.
 *
 * Staleness is recorded per `translations` row (per translation target identity).
 * On the first invalidation the row receives `stale_at`, `staleness_reason`, and
 * the causing revision. On a repeated invalidation the original `stale_at` is
 * preserved, the stored reason is upgraded only when the new reason outranks it
 * by the frozen precedence
 * (`SegmentStructureChanged > TimingChanged > SourceTextChanged`), and the
 * causing revision is updated consistently with the stored canonical reason. A
 * stronger reason is never downgraded.
 *
 * Existing translation content (`translation_segments`) is never rewritten or
 * remapped. The writer performs no lifecycle transition and never clears
 * staleness (a successful retranslation uses a new canonical identity).
 */
final class EloquentTranslationStalenessWriter implements TranslationStalenessWriter
{
    /**
     * Invalidate every persisted translation row for the transcription.
     *
     * @return int the number of rows whose persisted staleness changed
     */
    public function invalidate(
        Transcription $transcription,
        TranslationStalenessReason $reason,
        ?string $causingRevisionId = null,
        ?DateTimeInterface $at = null,
    ): int {
        $instant = $at === null ? Carbon::now() : Carbon::instance($at);

        $translations = Translation::query()
            ->where('transcription_id', $transcription->getKey())
            ->get();

        $changed = 0;

        foreach ($translations as $translation) {
            if ($this->apply($translation, $reason, $causingRevisionId, $instant)) {
                $changed++;
            }
        }

        return $changed;
    }

    private function apply(
        Translation $translation,
        TranslationStalenessReason $reason,
        ?string $causingRevisionId,
        Carbon $instant,
    ): bool {
        if ($translation->stale_at === null) {
            $translation->stale_at = $instant;
            $translation->staleness_reason = $reason;
            $translation->stale_caused_by_revision_id = $causingRevisionId;
            $translation->save();

            return true;
        }

        $stored = $translation->staleness_reason;

        if ($stored === null || $reason->precedence() > $stored->precedence()) {
            $translation->staleness_reason = $reason;
            $translation->stale_caused_by_revision_id = $causingRevisionId;
            $translation->save();

            return true;
        }

        return false;
    }
}
