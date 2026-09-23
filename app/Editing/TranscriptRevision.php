<?php

namespace App\Editing;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An immutable editable revision of a completed machine transcription (D6-01,
 * D6-02).
 *
 * A revision is a durable snapshot: its ordered segments and provenance are
 * never mutated. Edits produce a new revision linked through
 * `parentRevisionId`. Exactly one revision may be active for a transcription
 * (tracked outside this value object by `transcriptions.active_revision_id`).
 */
final readonly class TranscriptRevision
{
    /**
     * @param  list<RevisionSegmentData>  $segments
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $revisionId,
        public int $transcriptionId,
        public int $version,
        public ?string $parentRevisionId,
        public int $createdBy,
        public DateTimeImmutable $createdAt,
        public array $segments,
    ) {
        if (trim($revisionId) === '') {
            throw new InvalidArgumentException('Revision id must be non-empty.');
        }

        if ($transcriptionId <= 0) {
            throw new InvalidArgumentException('Transcription id must be positive.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('Revision version must be at least 1.');
        }

        if ($parentRevisionId !== null && trim($parentRevisionId) === '') {
            throw new InvalidArgumentException('Parent revision id must be null or non-empty.');
        }

        if ($createdBy <= 0) {
            throw new InvalidArgumentException('Revision author id must be positive.');
        }

        TimingInvariants::assertValidSequence($segments);
    }

    public function segmentCount(): int
    {
        return count($this->segments);
    }

    public function isEmpty(): bool
    {
        return $this->segments === [];
    }

    public function isInitial(): bool
    {
        return $this->version === 1 && $this->parentRevisionId === null;
    }

    public function segmentByPosition(int $position): ?RevisionSegmentData
    {
        foreach ($this->segments as $segment) {
            if ($segment->position === $position) {
                return $segment;
            }
        }

        return null;
    }

    public function segmentByIdentity(RevisionSegmentIdentity $identity): ?RevisionSegmentData
    {
        foreach ($this->segments as $segment) {
            if ($segment->identity->equals($identity)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * @return list<RevisionSegmentData> ordered by position ascending
     */
    public function orderedSegments(): array
    {
        $ordered = $this->segments;

        usort($ordered, static fn (RevisionSegmentData $a, RevisionSegmentData $b): int => $a->position <=> $b->position);

        return $ordered;
    }
}
