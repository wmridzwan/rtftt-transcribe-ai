<?php

namespace App\Editing;

/**
 * Persistence contract for the editable revision layer (P6-001).
 *
 * This is a contract only; the implementation and migrations are owned by
 * P6-002. All writes are append-only and compare-and-set against the active
 * revision to enforce optimistic concurrency (there is no in-place revision
 * mutation and no silent merge).
 *
 * Two distinct structures are exposed and must not be conflated:
 *
 * - the **durable revision graph/history**: every revision row and its
 *   ancestry, which is append-only and never rewritten; and
 * - the **user undo/redo navigation path**: a derived path over that history,
 *   expressed as active-revision-pointer movement. Undo activates a strict
 *   ancestor; redo activates the deterministic redo target (see
 *   {@see self::redoTargetFor()}). A new edit from a non-tip active revision
 *   branches the history and invalidates the prior redo path without deleting
 *   any historical revision.
 */
interface RevisionRepository extends RevisionVersionAllocator
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
     * Direct children of a revision (revisions whose `parentRevisionId` is
     * `$revisionId`), ordered by version ascending.
     *
     * Branch ancestry is expressed by `parentRevisionId`; a revision may have
     * more than one child once an undo-then-edit branch has been created. All
     * children remain durable.
     *
     * @return list<TranscriptRevision>
     */
    public function childrenOf(string $revisionId): array;

    /**
     * Deterministic redo target for a transcription's active revision: its
     * unique child, or null when redo is unavailable.
     *
     * Redo is unavailable when there is no active revision, the active
     * revision has no child, or the active revision is a branch point with
     * more than one child (automatic redo must never choose among siblings).
     * Creating a new revision from a non-tip active revision invalidates the
     * prior redo path — the abandoned branch stays durable and visible in
     * history but is no longer the automatic redo target. Explicit historical
     * revision selection is not automatic redo.
     */
    public function redoTargetFor(int $transcriptionId): ?TranscriptRevision;

    /**
     * Persist a new revision. The write is applied only when
     * `$expectedActiveRevisionId` equals the current active revision id (null
     * for the machine source).
     *
     * The revision's `parentRevisionId` must equal `$expectedActiveRevisionId`
     * (both null for the initial materialization): a new edit always branches
     * from the current active revision. The revision's `version` must be
     * strictly greater than every version already allocated for the
     * transcription; a non-monotonic version is rejected so that
     * `(transcription_id, version)` can never collide across branches.
     *
     * @throws RevisionConflictException when the active-pointer expectation or
     *                                   the monotonic-version rule does not hold
     */
    public function append(TranscriptRevision $revision, ?string $expectedActiveRevisionId): TranscriptRevision;

    /**
     * Compare-and-set the active revision pointer.
     *
     * This is a persistence-neutral pointer primitive: it enforces only the
     * active-pointer CAS and that `$revisionId` belongs to `$transcriptionId`.
     * It deliberately does not encode graph semantics. Strict-ancestor undo is
     * enforced by the application boundary
     * ({@see RevisionService::undo()}); redo activates the
     * deterministic redo target from {@see self::redoTargetFor()}; explicit
     * historical selection (P6-008) is a future, separately scoped caller.
     *
     * @throws RevisionConflictException when the expectation does not hold
     * @throws \InvalidArgumentException when the revision is unknown or belongs
     *                                   to another transcription
     */
    public function activate(int $transcriptionId, string $revisionId, ?string $expectedActiveRevisionId): void;
}
