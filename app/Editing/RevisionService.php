<?php

namespace App\Editing;

use App\Editing\Persistence\MachineSourceMaterializer;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Authorization-fenced application service over the P6-001 revision domain
 * (P6-002).
 *
 * The {@see RevisionRepository} is a low-level persistence primitive with no
 * notion of an actor; this service is where ownership is enforced:
 * mutations require `TranscriptionPolicy::update` (owner or admin) and reads
 * require `TranscriptionPolicy::view`.
 *
 * It does not redefine any P6-001 semantics; it consumes them.
 */
final class RevisionService
{
    public function __construct(
        private readonly RevisionRepository $repository,
        private readonly RevisionFactory $factory,
        private readonly MachineSourceMaterializer $materializer,
    ) {}

    public function active(User $user, Transcription $transcription): ?TranscriptRevision
    {
        Gate::forUser($user)->authorize('view', $transcription);

        return $this->repository->activeFor($transcription->getKey());
    }

    /**
     * @return list<TranscriptRevision>
     */
    public function history(User $user, Transcription $transcription): array
    {
        Gate::forUser($user)->authorize('view', $transcription);

        return $this->repository->historyFor($transcription->getKey());
    }

    public function redoTarget(User $user, Transcription $transcription): ?TranscriptRevision
    {
        Gate::forUser($user)->authorize('view', $transcription);

        return $this->repository->redoTargetFor($transcription->getKey());
    }

    /**
     * Materialize the initial revision from the immutable machine source and
     * make it active. Only valid while no active revision exists: the machine
     * source is the expected base (`null`), so an existing active revision
     * produces a stale-write conflict.
     */
    public function materializeInitial(User $user, Transcription $transcription): TranscriptRevision
    {
        Gate::forUser($user)->authorize('update', $transcription);

        $revision = $this->materializer->materialize($transcription, $user->getKey());

        return $this->repository->append($revision, null);
    }

    /**
     * Apply an edit by branching from the stated base revision. The base must be
     * the current active revision; otherwise the write is a stale-write conflict.
     *
     * @param  list<RevisionSegmentData>  $segments
     */
    public function edit(
        User $user,
        Transcription $transcription,
        string $baseRevisionId,
        array $segments,
    ): TranscriptRevision {
        Gate::forUser($user)->authorize('update', $transcription);

        $base = $this->repository->find($baseRevisionId);

        if ($base === null || $base->transcriptionId !== $transcription->getKey()) {
            throw new InvalidArgumentException('Unknown base revision id ['.$baseRevisionId.'] for transcription ['.$transcription->getKey().'].');
        }

        $revision = $this->factory->derive($base, $user->getKey(), $segments, $this->repository);

        return $this->repository->append($revision, $baseRevisionId);
    }

    /**
     * Undo: compare-and-set the active pointer onto a strict ancestor of the
     * current active revision.
     *
     * A valid undo target is a revision of the same transcription that is
     * reached by repeatedly following `parentRevisionId` from the current
     * active revision (P6-001 §2). The current revision itself, siblings,
     * cousins, descendants, unrelated revisions, and revisions from another
     * transcription are rejected with {@see UndoUnavailableException} or
     * {@see InvalidArgumentException}; the machine source (`null`) is the
     * ancestry root and is not a reachable revision target.
     *
     * The expected active token defaults to the current persisted pointer, so
     * callers are not required to hold a fresh model instance. A stale expected
     * token is rejected as a {@see RevisionConflictException} before any
     * ancestry work, and the pointer move itself is re-checked under the
     * repository's compare-and-set.
     */
    public function undo(
        User $user,
        Transcription $transcription,
        string $targetRevisionId,
        ?string $expectedActiveRevisionId = null,
    ): void {
        Gate::forUser($user)->authorize('update', $transcription);

        $transcriptionId = $transcription->getKey();

        $current = $this->repository->activeFor($transcriptionId);
        $currentRevisionId = $current?->revisionId;
        $expected = $expectedActiveRevisionId ?? $currentRevisionId;

        if ($currentRevisionId !== $expected) {
            throw RevisionConflictException::staleBase($expected, $currentRevisionId);
        }

        if ($currentRevisionId === null) {
            throw UndoUnavailableException::machineSourceActive($transcriptionId);
        }

        if ($targetRevisionId === $currentRevisionId) {
            throw UndoUnavailableException::targetNotAnAncestor($targetRevisionId, $currentRevisionId);
        }

        $target = $this->repository->find($targetRevisionId);

        if ($target === null || $target->transcriptionId !== $transcriptionId) {
            throw new InvalidArgumentException('Unknown undo target revision id ['.$targetRevisionId.'] for transcription ['.$transcriptionId.'].');
        }

        if (! $this->isStrictAncestor($current, $targetRevisionId)) {
            throw UndoUnavailableException::targetNotAnAncestor($targetRevisionId, $currentRevisionId);
        }

        $this->repository->activate($transcriptionId, $targetRevisionId, $currentRevisionId);
    }

    /**
     * Redo: compare-and-set the active pointer onto the deterministic redo
     * target. Unavailable at a branch point (no unique child).
     */
    public function redo(User $user, Transcription $transcription, ?string $expectedActiveRevisionId = null): TranscriptRevision
    {
        Gate::forUser($user)->authorize('update', $transcription);

        $target = $this->repository->redoTargetFor($transcription->getKey());

        if ($target === null) {
            throw RedoUnavailableException::noUniqueChild($transcription->getKey());
        }

        $this->repository->activate(
            $transcription->getKey(),
            $target->revisionId,
            $expectedActiveRevisionId ?? $this->currentActiveId($transcription),
        );

        return $target;
    }

    private function currentActiveId(Transcription $transcription): ?string
    {
        return $this->repository->activeFor($transcription->getKey())?->revisionId;
    }

    /**
     * Walk the `parentRevisionId` chain from the current active revision: the
     * target is a strict ancestor only when the chain reaches it. The current
     * revision itself is never returned (the walk starts at its parent), and
     * the machine-source root (`null`) terminates the walk without matching.
     */
    private function isStrictAncestor(TranscriptRevision $current, string $targetRevisionId): bool
    {
        $cursor = $current->parentRevisionId;

        while ($cursor !== null) {
            if ($cursor === $targetRevisionId) {
                return true;
            }

            $cursor = $this->repository->find($cursor)?->parentRevisionId;
        }

        return false;
    }
}
