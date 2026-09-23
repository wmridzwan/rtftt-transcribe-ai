<?php

namespace App\Editing;

/**
 * Persistence contract for the editable revision layer (P6-001).
 *
 * This is a contract only; the implementation and migrations are owned by
 * P6-002. All writes are append-only and compare-and-set against the active
 * revision to enforce optimistic concurrency (there is no in-place revision
 * mutation and no silent merge).
 */
interface RevisionRepository
{
    /**
     * The active revision for a transcription, or null when the immutable
     * machine source is authoritative.
     */
    public function activeFor(int $transcriptionId): ?TranscriptRevision;

    public function find(string $revisionId): ?TranscriptRevision;

    /**
     * All revisions for a transcription, ordered by version ascending.
     *
     * @return list<TranscriptRevision>
     */
    public function historyFor(int $transcriptionId): array;

    /**
     * Persist a new revision. The write is applied only when
     * `$expectedActiveRevisionId` equals the current active revision id (null
     * for the machine source).
     *
     * @throws RevisionConflictException when the expectation does not hold
     */
    public function append(TranscriptRevision $revision, ?string $expectedActiveRevisionId): TranscriptRevision;

    /**
     * Compare-and-set the active revision pointer.
     *
     * @throws RevisionConflictException when the expectation does not hold
     */
    public function activate(int $transcriptionId, string $revisionId, ?string $expectedActiveRevisionId): void;
}
