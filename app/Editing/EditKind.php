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

    /**
     * Precedence of this edit kind when a single edit spans more than one
     * category (D6-04). Higher wins: `Structural` > `Timing` > `Textual`.
     *
     * This selects only the canonical staleness reason recorded for the edit;
     * every applicable kind remains translation-invalidating regardless.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Structural => 3,
            self::Timing => 2,
            self::Textual => 1,
        };
    }
}
