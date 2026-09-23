<?php

namespace App\Editing;

use RuntimeException;

/**
 * Raised when `undo` cannot be performed because the requested target is not a
 * strict ancestor of the current active revision (P6-001 §2; P6-002).
 *
 * This is a navigation outcome, not a stale-write conflict and not a
 * programmer error: the target may be a real revision of the same
 * transcription, but an undo may only move the active pointer backwards along
 * the unique `parent_revision_id` ancestry chain. Self, siblings, cousins,
 * descendants, unrelated revisions, and the machine-source root are all
 * rejected; the active pointer and every revision row are left untouched.
 */
final class UndoUnavailableException extends RuntimeException
{
    public static function machineSourceActive(int $transcriptionId): self
    {
        return new self(sprintf(
            'Undo is unavailable for transcription [%d]: the machine source is active and has no ancestor.',
            $transcriptionId,
        ));
    }

    public static function targetNotAnAncestor(string $targetRevisionId, string $currentRevisionId): self
    {
        return new self(sprintf(
            'Undo target [%s] is not a strict ancestor of the active revision [%s].',
            $targetRevisionId,
            $currentRevisionId,
        ));
    }
}
