<?php

namespace App\Comparison;

/**
 * Read-only source / active-revision / persisted-translation comparison
 * (P6-007; `DECISION-P6-007-SCOPE-001`).
 *
 * Presentation-only: it carries facts actually persisted (machine source,
 * active revision, completed translation aligned by `segment_index`) and never
 * infers freshness/staleness. It performs no writes and owns no translation
 * lifecycle.
 */
final readonly class TranscriptComparison
{
    /**
     * @param  list<ComparisonRow>  $rows
     */
    public function __construct(
        public array $rows,
        public bool $hasActiveRevision,
        public ?int $activeRevisionVersion,
        public bool $hasTranslation,
        public ?string $translationTargetLanguage,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * True when an active revision exists and at least one machine-aligned
     * revision segment's text differs from the machine source. Used only to show
     * the explicit "translation is of the original source" mismatch note; no
     * freshness state is derived.
     */
    public function hasEditedActiveRevision(): bool
    {
        foreach ($this->rows as $row) {
            if ($row->revisionEdited()) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the active revision contains a segment that cannot be aligned to
     * a machine `segment_index` (structural incompatibility). Translation must
     * not be mapped onto such a revision by index.
     */
    public function hasUnalignedRevisionSegments(): bool
    {
        foreach ($this->rows as $row) {
            if ($row->machineIndex === null) {
                return true;
            }
        }

        return false;
    }
}
