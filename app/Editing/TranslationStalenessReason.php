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
}
