<?php

namespace App\Editing;

/**
 * Allocates transcription-scoped revision versions (D6-02).
 *
 * A revision `version` is a monotonic sequence scoped to the transcription and
 * independent of revision ancestry: every newly created revision receives a
 * version strictly greater than every revision already allocated for that
 * transcription. Branching from an older active revision therefore never
 * reuses an earlier version number.
 *
 * This is an abstract allocation contract, not a persistence contract: the
 * domain layer depends only on this interface, never on Eloquent, SQL, or any
 * storage engine. The {@see RevisionRepository} implementation owns the
 * durable allocation (P6-002).
 */
interface RevisionVersionAllocator
{
    /**
     * The next transcription-scoped version: strictly greater than every
     * version already allocated for the transcription, or 1 when the
     * transcription has no revisions yet.
     */
    public function nextVersionFor(int $transcriptionId): int;
}
