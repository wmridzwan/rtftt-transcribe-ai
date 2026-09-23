<?php

namespace App\Editing;

/**
 * Classification of an edit applied to the editable revision layer (D6-04).
 *
 * Every edit kind invalidates an existing translation: P6-001 fixes the policy
 * that a translation is never silently preserved as current across an edit.
 */
enum EditKind: string
{
    case Textual = 'textual';
    case Timing = 'timing';
    case Structural = 'structural';

    /**
     * The explicit staleness reason this edit kind forces on affected
     * translations.
     */
    public function stalenessReason(): TranslationStalenessReason
    {
        return match ($this) {
            self::Textual => TranslationStalenessReason::SourceTextChanged,
            self::Timing => TranslationStalenessReason::TimingChanged,
            self::Structural => TranslationStalenessReason::SegmentStructureChanged,
        };
    }
}
