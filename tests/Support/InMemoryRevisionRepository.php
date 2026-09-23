<?php

namespace Tests\Support;

use App\Editing\RevisionConflictException;
use App\Editing\RevisionRepository;
use App\Editing\TranscriptRevision;
use InvalidArgumentException;

/**
 * Reference in-memory implementation of the P6-001 {@see RevisionRepository}
 * contract. It exists to pin the contract semantics (append-only history,
 * compare-and-set active pointer, stale-base rejection) for unit tests; it is
 * not application code and is not registered in the container.
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

        return array_values($revisions);
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

        if ($this->find($revisionId) === null) {
            throw new InvalidArgumentException('Unknown revision id ['.$revisionId.'].');
        }

        $this->active[$transcriptionId] = $revisionId;
    }
}
