<?php

namespace App\Editing;

use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Constructs immutable revisions for the editable revision layer (D6-01,
 * D6-02).
 *
 * The factory is pure with respect to persistence: it generates server-side
 * revision ids, assigns contiguous positions, and links revisions through
 * `parentRevisionId`. Persisting or activating a revision is the repository's
 * responsibility (P6-002).
 *
 * Version assignment is **not** derived from the base revision. `version` is a
 * monotonic sequence scoped to the transcription and independent of ancestry,
 * so the factory obtains the next version from a
 * {@see RevisionVersionAllocator} (the repository, in practice) rather than
 * computing `$base->version + 1`. This keeps `(transcription_id, version)`
 * unique even when a new revision branches from an older active revision.
 */
final class RevisionFactory
{
    /**
     * Materialize the initial revision from the immutable machine source.
     *
     * Machine segments are ordered by `segment_index`; each is copied verbatim
     * (timing, text, language) and receives a deterministic `machine:<index>`
     * identity. No machine row is read or written here.
     *
     * @param  iterable<MachineSegmentSnapshot>  $machineSegments
     */
    public function materializeInitial(
        int $transcriptionId,
        int $createdBy,
        iterable $machineSegments,
        ?DateTimeImmutable $createdAt = null,
    ): TranscriptRevision {
        /** @var list<MachineSegmentSnapshot> $ordered */
        $ordered = [];

        foreach ($machineSegments as $snapshot) {
            $ordered[] = $snapshot;
        }

        usort($ordered, static fn (MachineSegmentSnapshot $a, MachineSegmentSnapshot $b): int => $a->segmentIndex <=> $b->segmentIndex);

        /** @var list<RevisionSegmentData> $segments */
        $segments = [];
        $position = 0;

        foreach ($ordered as $snapshot) {
            $segments[] = new RevisionSegmentData(
                identity: RevisionSegmentIdentity::forMachineSegment($snapshot->segmentIndex),
                position: $position,
                startSeconds: $snapshot->startSeconds,
                endSeconds: $snapshot->endSeconds,
                text: $snapshot->text,
                language: $snapshot->language,
            );

            $position++;
        }

        return new TranscriptRevision(
            revisionId: $this->newRevisionId(),
            transcriptionId: $transcriptionId,
            version: 1,
            parentRevisionId: null,
            createdBy: $createdBy,
            createdAt: $createdAt ?? new DateTimeImmutable('now'),
            segments: $segments,
        );
    }

    /**
     * Derive a new revision from a base revision.
     *
     * The new revision records the base as its parent. Its version is the
     * next transcription-scoped version supplied by `$allocator` — strictly
     * greater than every version already allocated for the transcription,
     * independent of the base revision's own version. Callers are responsible
     * for supplying the edited segment set (including new identities for
     * structural edits).
     *
     * @param  list<RevisionSegmentData>  $segments
     */
    public function derive(
        TranscriptRevision $base,
        int $createdBy,
        array $segments,
        RevisionVersionAllocator $allocator,
        ?DateTimeImmutable $createdAt = null,
    ): TranscriptRevision {
        $version = $allocator->nextVersionFor($base->transcriptionId);

        if ($version <= $base->version) {
            throw new InvalidArgumentException(sprintf(
                'Allocated revision version [%d] must be strictly greater than the base revision version [%d].',
                $version,
                $base->version,
            ));
        }

        return new TranscriptRevision(
            revisionId: $this->newRevisionId(),
            transcriptionId: $base->transcriptionId,
            version: $version,
            parentRevisionId: $base->revisionId,
            createdBy: $createdBy,
            createdAt: $createdAt ?? new DateTimeImmutable('now'),
            segments: $segments,
        );
    }

    private function newRevisionId(): string
    {
        return (string) Str::uuid();
    }
}
