<?php

namespace Tests\Support;

use App\Editing\RevisionConflictException;
use App\Editing\RevisionRepository;
use App\Editing\TranscriptRevision;
use InvalidArgumentException;

/**
 * Reference in-memory implementation of the P6-001 {@see RevisionRepository}
 * contract. It exists to pin the contract semantics (append-only history,
 * transcription-scoped monotonic version allocation, compare-and-set active
 * pointer, stale-base rejection, branch ancestry, deterministic redo target)
 * for unit tests; it is not application code and is not registered in the
 * container.
 */
final class InMemoryRevisionRepository implements RevisionRepository
{
    /** @var array<int, list<TranscriptRevision>> */
    private array $history = [];

    /** @var array<int, ?string> */
    private array $active = [];

    public function activeFor(int $transcriptionId): ?TranscriptRevision
    {
        $revisionId = $this->active[$transcriptionId] ?? null;

        return $revisionId === null ? null : $this->find($revisionId);
    }

    public function find(string $revisionId): ?TranscriptRevision
    {
        foreach ($this->history as $revisions) {
            foreach ($revisions as $revision) {
                if ($revision->revisionId === $revisionId) {
                    return $revision;
                }
            }
        }

        return null;
    }

    public function historyFor(int $transcriptionId): array
    {
        $revisions = $this->history[$transcriptionId] ?? [];

        usort($revisions, static fn (TranscriptRevision $a, TranscriptRevision $b): int => $a->version <=> $b->version);

        return $revisions;
    }

    public function childrenOf(string $revisionId): array
    {
        $children = [];

        foreach ($this->history as $revisions) {
            foreach ($revisions as $revision) {
                if ($revision->parentRevisionId === $revisionId) {
                    $children[] = $revision;
                }
            }
        }

        usort($children, static fn (TranscriptRevision $a, TranscriptRevision $b): int => $a->version <=> $b->version);

        return $children;
    }

    public function redoTargetFor(int $transcriptionId): ?TranscriptRevision
    {
        $active = $this->activeFor($transcriptionId);

        if ($active === null) {
            return null;
        }

        $children = $this->childrenOf($active->revisionId);

        return count($children) === 1 ? $children[0] : null;
    }

    public function nextVersionFor(int $transcriptionId): int
    {
        $max = 0;

        foreach ($this->history[$transcriptionId] ?? [] as $revision) {
            $max = max($max, $revision->version);
        }

        return $max + 1;
    }

    public function append(TranscriptRevision $revision, ?string $expectedActiveRevisionId): TranscriptRevision
    {
        $current = $this->active[$revision->transcriptionId] ?? null;

        if ($current !== $expectedActiveRevisionId) {
            throw RevisionConflictException::staleBase($expectedActiveRevisionId, $current);
        }

        if ($this->find($revision->revisionId) !== null) {
            throw new InvalidArgumentException('Revision id ['.$revision->revisionId.'] already exists.');
        }

        if ($revision->parentRevisionId !== $expectedActiveRevisionId) {
            throw new InvalidArgumentException(sprintf(
                'Revision parent [%s] must equal the expected active revision [%s].',
                $revision->parentRevisionId ?? 'machine source',
                $expectedActiveRevisionId ?? 'machine source',
            ));
        }

        $maxExisting = $this->nextVersionFor($revision->transcriptionId) - 1;

        if ($revision->version <= $maxExisting) {
            throw RevisionConflictException::nonMonotonicVersion($revision->version, $maxExisting);
        }

        $this->history[$revision->transcriptionId][] = $revision;
        $this->active[$revision->transcriptionId] = $revision->revisionId;

        return $revision;
    }

    public function activate(int $transcriptionId, string $revisionId, ?string $expectedActiveRevisionId): void
    {
        $current = $this->active[$transcriptionId] ?? null;

        if ($current !== $expectedActiveRevisionId) {
            throw RevisionConflictException::staleBase($expectedActiveRevisionId, $current);
        }

        $target = $this->find($revisionId);

        if ($target === null || $target->transcriptionId !== $transcriptionId) {
            throw new InvalidArgumentException('Unknown revision id ['.$revisionId.'] for transcription ['.$transcriptionId.'].');
        }

        $this->active[$transcriptionId] = $revisionId;
    }
}
