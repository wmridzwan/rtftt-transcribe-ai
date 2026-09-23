<?php

namespace App\Editing;

use RuntimeException;

/**
 * Raised when automatic redo is unavailable because the active revision has no
 * unique deterministic child (P6-001 §2; P6-002).
 *
 * This is a navigation outcome, not a stale-write conflict: a branch point has
 * more than one child, and automatic redo must never choose among siblings.
 * Historical revisions remain durable and reachable by explicit selection.
 */
final class RedoUnavailableException extends RuntimeException
{
    public static function noUniqueChild(int $transcriptionId): self
    {
        return new self(sprintf(
            'Automatic redo is unavailable for transcription [%d]: the active revision has no unique deterministic child.',
            $transcriptionId,
        ));
    }
}
