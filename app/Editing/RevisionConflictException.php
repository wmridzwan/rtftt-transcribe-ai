<?php

namespace App\Editing;

use RuntimeException;

/**
 * Raised when an edit or activation is composed against a revision that is no
 * longer the active revision (optimistic concurrency; D6-02).
 *
 * The write is rejected outright: there is no silent merge and no
 * last-writer-wins behaviour.
 */
final class RevisionConflictException extends RuntimeException
{
    public static function staleBase(?string $expected, ?string $actual): self
    {
        return new self(sprintf(
            'Stale revision write: expected active revision [%s] but [%s] is active.',
            self::describe($expected),
            self::describe($actual),
        ));
    }

    public static function nonMonotonicVersion(int $version, int $maxExistingVersion): self
    {
        return new self(sprintf(
            'Non-monotonic revision version [%d]: version must be strictly greater than the maximum existing version [%d] for the transcription.',
            $version,
            $maxExistingVersion,
        ));
    }

    private static function describe(?string $revisionId): string
    {
        return $revisionId === null || $revisionId === '' ? 'machine source' : $revisionId;
    }
}
