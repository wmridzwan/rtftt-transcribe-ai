<?php

namespace App\Comparison;

/**
 * One read-only comparison row (P6-007): the machine source segment, the
 * corresponding active-revision segment (where the revision identity still
 * corresponds to the machine `segment_index`), and the persisted Phase 5
 * translation segment aligned by `segment_index`.
 *
 * A row is presentation data only. `revisionAligned = false` means the active
 * revision segment could not be mapped to a machine `segment_index` (for
 * example after a future structural edit); such a row is never aligned by array
 * index and the UI presents alignment as unavailable.
 */
final readonly class ComparisonRow
{
    public function __construct(
        public ?int $machineIndex,
        public ?string $machineText,
        public ?string $machineLanguage,
        public ?string $translatedText,
        public ?int $revisionPosition,
        public ?string $revisionText,
        public bool $revisionAligned,
    ) {}

    /**
     * Whether the active revision carries a machine-provenance identity whose
     * text differs from the machine source it was derived from. This is a
     * factual text comparison, not a staleness inference.
     */
    public function revisionEdited(): bool
    {
        return $this->revisionAligned
            && $this->revisionText !== null
            && $this->machineText !== null
            && $this->revisionText !== $this->machineText;
    }

    public function hasTranslation(): bool
    {
        return $this->translatedText !== null;
    }

    /**
     * Whether the per-row provenance note is factually supportable: the active
     * revision segment is machine-aligned, its text differs from the machine
     * source it was derived from, and a persisted translation of that machine
     * source exists for this row.
     *
     * Both conditions are persisted facts. Gating the note on translation
     * existence prevents a false chronology claim ("edited after the
     * translation was produced") when no translation was ever persisted, and
     * derives no freshness/staleness state.
     */
    public function hasEditedRevisionWithPersistedTranslation(): bool
    {
        return $this->revisionEdited() && $this->hasTranslation();
    }
}
