<?php

namespace App\Editing;

/**
 * Explicit reason an existing translation is no longer current (D6-04).
 *
 * A translation is marked stale with one of these reasons; it is never
 * silently remapped across changed segment structure.
 */
enum TranslationStalenessReason: string
{
    case SourceTextChanged = 'SOURCE_TEXT_CHANGED';
    case TimingChanged = 'TIMING_CHANGED';
    case SegmentStructureChanged = 'SEGMENT_STRUCTURE_CHANGED';

    /**
     * Canonical precedence when more than one reason applies (most invasive
     * wins): `SegmentStructureChanged` > `TimingChanged` > `SourceTextChanged`.
     *
     * This is the same frozen ordering exposed by {@see EditKind::precedence()};
     * it selects only the canonical stored reason and never allows a stronger
     * reason to be downgraded to a weaker one.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::SegmentStructureChanged => 3,
            self::TimingChanged => 2,
            self::SourceTextChanged => 1,
        };
    }
}
