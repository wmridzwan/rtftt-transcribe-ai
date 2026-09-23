<?php

namespace App\Editing;

use InvalidArgumentException;

/**
 * Canonical Phase 6 translation-invalidation policy (D6-04).
 *
 * Every edit kind invalidates affected translations explicitly. There is no
 * edit kind under which an existing translation may silently remain current,
 * and translation content is never silently remapped across changed segment
 * structure.
 *
 * When one edit operation spans more than one category, the recorded staleness
 * reason is chosen by a fixed precedence — `SegmentStructureChanged` >
 * `TimingChanged` > `SourceTextChanged` — via {@see self::reasonForKinds()}.
 * The precedence selects only the canonical reason; every applicable kind
 * remains translation-invalidating.
 *
 * The persisted staleness marker (for example `translations.stale_at` /
 * `translations.staleness_reason`) is owned and implemented by the P6-005
 * translation-invalidation contract; this class fixes only the policy.
 */
final class TranslationInvalidationPolicy
{
    public static function mustMarkStale(EditKind $kind): bool
    {
        return match ($kind) {
            EditKind::Textual,
            EditKind::Timing,
            EditKind::Structural => true,
        };
    }

    public static function reasonFor(EditKind $kind): TranslationStalenessReason
    {
        return $kind->stalenessReason();
    }

    /**
     * Canonical staleness reason for an edit spanning one or more categories.
     *
     * Precedence (most invasive wins):
     * `SegmentStructureChanged` > `TimingChanged` > `SourceTextChanged`.
     *
     * @throws InvalidArgumentException when no edit kind is supplied
     */
    public static function reasonForKinds(EditKind ...$kinds): TranslationStalenessReason
    {
        if ($kinds === []) {
            throw new InvalidArgumentException('At least one edit kind is required to select a staleness reason.');
        }

        $highest = $kinds[0];

        foreach ($kinds as $kind) {
            if ($kind->precedence() > $highest->precedence()) {
                $highest = $kind;
            }
        }

        return $highest->stalenessReason();
    }

    /**
     * A translation is never allowed to remain current across an edit.
     */
    public static function mayRemainCurrent(EditKind $kind): bool
    {
        return ! self::mustMarkStale($kind);
    }

    /**
     * Translation content is never silently remapped onto a changed structure.
     */
    public static function neverSilentlyRemaps(EditKind $kind): bool
    {
        return self::mustMarkStale($kind);
    }
}
