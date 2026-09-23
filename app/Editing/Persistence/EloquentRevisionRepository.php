<?php

namespace App\Editing\Persistence;

use App\Editing\RevisionConflictException;
use App\Editing\RevisionRepository;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Editing\TranscriptRevision;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Durable Eloquent implementation of the P6-001 {@see RevisionRepository}
 * contract (P6-002).
 *
 * Writes are append-only and fenced by:
 *
 * - an active-pointer compare-and-set inside a transaction (`lockForUpdate` on
 *   the transcription row where the datastore supports row locking), and
 * - the `(transcription_id, version)` unique constraint as the durable
 *   backstop, with a monotonic re-check immediately before insert.
 *
 * A unique-constraint violation from a lost allocation race is translated into
 * the canonical {@see RevisionConflictException}; a raw database uniqueness
 * exception is never the ordinary outcome.
 */
final class EloquentRevisionRepository implements RevisionRepository
{
    public function activeFor(int $transcriptionId): ?TranscriptRevision
    {
        $revisionId = Transcription::query()
            ->whereKey($transcriptionId)
            ->value('active_revision_id');

        return $revisionId === null ? null : $this->find((string) $revisionId);
    }

    public function find(string $revisionId): ?TranscriptRevision
    {
        $model = TranscriptRevisionModel::query()
            ->with('segments')
            ->find($revisionId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function historyFor(int $transcriptionId): array
    {
        $models = TranscriptRevisionModel::query()
            ->where('transcription_id', $transcriptionId)
            ->with('segments')
            ->orderBy('version')
            ->get();

        return array_values($models->map(fn (TranscriptRevisionModel $model): TranscriptRevision => $this->toDomain($model))->all());
    }

    public function childrenOf(string $revisionId): array
    {
        $models = TranscriptRevisionModel::query()
            ->where('parent_revision_id', $revisionId)
            ->with('segments')
            ->orderBy('version')
            ->get();

        return array_values($models->map(fn (TranscriptRevisionModel $model): TranscriptRevision => $this->toDomain($model))->all());
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
        $max = TranscriptRevisionModel::query()
            ->where('transcription_id', $transcriptionId)
            ->max('version');

        return ((int) $max) + 1;
    }

    public function append(TranscriptRevision $revision, ?string $expectedActiveRevisionId): TranscriptRevision
    {
        try {
            return DB::transaction(function () use ($revision, $expectedActiveRevisionId): TranscriptRevision {
                $transcription = Transcription::query()
                    ->whereKey($revision->transcriptionId)
                    ->lockForUpdate()
                    ->first();

                if ($transcription === null) {
                    throw new InvalidArgumentException('Unknown transcription id ['.$revision->transcriptionId.'].');
                }

                $current = $transcription->active_revision_id;

                if ($current !== $expectedActiveRevisionId) {
                    throw RevisionConflictException::staleBase($expectedActiveRevisionId, $current);
                }

                if ($revision->parentRevisionId !== $expectedActiveRevisionId) {
                    throw new InvalidArgumentException(sprintf(
                        'Revision parent [%s] must equal the expected active revision [%s].',
                        $revision->parentRevisionId ?? 'machine source',
                        $expectedActiveRevisionId ?? 'machine source',
                    ));
                }

                if (TranscriptRevisionModel::query()->whereKey($revision->revisionId)->exists()) {
                    throw new InvalidArgumentException('Revision id ['.$revision->revisionId.'] already exists.');
                }

                $maxExisting = $this->nextVersionFor($revision->transcriptionId) - 1;

                if ($revision->version <= $maxExisting) {
                    throw RevisionConflictException::nonMonotonicVersion($revision->version, $maxExisting);
                }

                $this->insertRevision($revision);

                $transcription->active_revision_id = $revision->revisionId;
                $transcription->save();

                return $revision;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw RevisionConflictException::nonMonotonicVersion(
                $revision->version,
                $this->nextVersionFor($revision->transcriptionId) - 1,
            );
        }
    }

    public function activate(int $transcriptionId, string $revisionId, ?string $expectedActiveRevisionId): void
    {
        DB::transaction(function () use ($transcriptionId, $revisionId, $expectedActiveRevisionId): void {
            $transcription = Transcription::query()
                ->whereKey($transcriptionId)
                ->lockForUpdate()
                ->first();

            if ($transcription === null) {
                throw new InvalidArgumentException('Unknown transcription id ['.$transcriptionId.'].');
            }

            $current = $transcription->active_revision_id;

            if ($current !== $expectedActiveRevisionId) {
                throw RevisionConflictException::staleBase($expectedActiveRevisionId, $current);
            }

            $exists = TranscriptRevisionModel::query()
                ->whereKey($revisionId)
                ->where('transcription_id', $transcriptionId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException('Unknown revision id ['.$revisionId.'] for transcription ['.$transcriptionId.'].');
            }

            $transcription->active_revision_id = $revisionId;
            $transcription->save();
        }, 5);
    }

    private function insertRevision(TranscriptRevision $revision): void
    {
        $createdAt = Carbon::instance($revision->createdAt);

        $model = new TranscriptRevisionModel([
            'transcription_id' => $revision->transcriptionId,
            'version' => $revision->version,
            'parent_revision_id' => $revision->parentRevisionId,
            'created_by' => $revision->createdBy,
        ]);

        $model->id = $revision->revisionId;
        $model->created_at = $createdAt;
        $model->updated_at = $createdAt;
        $model->save();

        foreach ($revision->segments as $segment) {
            TranscriptRevisionSegment::query()->create([
                'revision_id' => $revision->revisionId,
                'segment_key' => $segment->identity->key(),
                'position' => $segment->position,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => $segment->text,
                'language' => $segment->language->value,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function toDomain(TranscriptRevisionModel $model): TranscriptRevision
    {
        /** @var list<RevisionSegmentData> $segments */
        $segments = array_values(
            $model->segments
                ->map(fn (TranscriptRevisionSegment $segment): RevisionSegmentData => new RevisionSegmentData(
                    identity: RevisionSegmentIdentity::fromString($segment->segment_key),
                    position: $segment->position,
                    startSeconds: $segment->start_seconds,
                    endSeconds: $segment->end_seconds,
                    text: $segment->text,
                    language: $segment->language,
                ))
                ->all()
        );

        return new TranscriptRevision(
            revisionId: $model->id,
            transcriptionId: $model->transcription_id,
            version: $model->version,
            parentRevisionId: $model->parent_revision_id,
            createdBy: $model->created_by,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            segments: $segments,
        );
    }
}
