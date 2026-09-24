<?php

namespace App\Editing;

use App\Models\Transcription;
use DateTimeInterface;

/**
 * Persists the canonical P6-005 translation-invalidation lifecycle.
 *
 * The domain service depends on this contract, never on Eloquent directly. The
 * durable implementation is owned by P6-005 persistence.
 */
interface TranslationStalenessWriter
{
    /**
     * Invalidate every persisted translation row for the transcription under
     * the frozen lifecycle, returning the number of rows whose persisted
     * staleness changed.
     *
     * This is idempotent with respect to the stored canonical reason: it never
     * downgrades a stronger reason to a weaker one and never refreshes a
     * previously recorded `stale_at`.
     */
    public function invalidate(
        Transcription $transcription,
        TranslationStalenessReason $reason,
        ?string $causingRevisionId = null,
        ?DateTimeInterface $at = null,
    ): int;
}
