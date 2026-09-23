<?php

namespace App\Editing;

/**
 * Canonical Phase 6 translation-invalidation policy (D6-04).
 *
 * Every edit kind invalidates affected translations explicitly. There is no
 * edit kind under which an existing translation may silently remain current,
 * and translation content is never silently remapped across changed segment
 * structure.
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
