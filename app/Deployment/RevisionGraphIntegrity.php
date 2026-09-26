<?php

namespace App\Deployment;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Revision-graph integrity reconciliation (P7-002).
 *
 * The same check runs before and after the SQLite → PostgreSQL data
 * migration (and in CI on sqlite): row counts plus the Phase 6
 * structural invariants that must reconcile exactly —
 * `transcript_revisions` / `transcript_revision_segments` /
 * `transcriptions.active_revision_id` / `(transcription_id, version)`
 * uniqueness / `parent_revision_id` ancestry.
 *
 * Driver-agnostic query-builder checks so the identical logic executes on
 * both stores. Connection-injectable for tests; live failures are
 * reported as violations, never thrown past the caller (the migration
 * procedure decides resume vs rollback).
 */
final class RevisionGraphIntegrity
{
    /**
     * @return array{counts: array<string, int>, violations: list<string>}
     */
    public static function reconcile(?string $connection = null): array
    {
        $violations = [];
        $counts = [
            'transcriptions' => 0,
            'transcript_revisions' => 0,
            'transcript_revision_segments' => 0,
        ];

        try {
            $db = $connection === null ? DB::connection() : DB::connection($connection);

            $counts['transcriptions'] = (int) $db->table('transcriptions')->count();
            $counts['transcript_revisions'] = (int) $db->table('transcript_revisions')->count();
            $counts['transcript_revision_segments'] = (int) $db->table('transcript_revision_segments')->count();

            $orphanRevisions = (int) $db->table('transcript_revisions as r')
                ->leftJoin('transcriptions as t', 't.id', '=', 'r.transcription_id')
                ->whereNull('t.id')
                ->count();

            if ($orphanRevisions > 0) {
                $violations[] = sprintf('%d transcript_revisions reference a missing transcription.', $orphanRevisions);
            }

            $orphanParents = (int) $db->table('transcript_revisions as r')
                ->leftJoin('transcript_revisions as p', 'p.id', '=', 'r.parent_revision_id')
                ->whereNotNull('r.parent_revision_id')
                ->whereNull('p.id')
                ->count();

            if ($orphanParents > 0) {
                $violations[] = sprintf('%d transcript_revisions reference a missing parent_revision_id.', $orphanParents);
            }

            $badActive = (int) $db->table('transcriptions as t')
                ->leftJoin('transcript_revisions as r', 'r.id', '=', 't.active_revision_id')
                ->whereNotNull('t.active_revision_id')
                ->whereNull('r.id')
                ->count();

            if ($badActive > 0) {
                $violations[] = sprintf('%d transcriptions reference a missing active_revision_id.', $badActive);
            }

            $duplicateVersions = $db->table('transcript_revisions')
                ->selectRaw('transcription_id, version, COUNT(*) as aggregate')
                ->groupBy('transcription_id', 'version')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicateVersions > 0) {
                $violations[] = sprintf('%d (transcription_id, version) groups are duplicated.', $duplicateVersions);
            }

            $orphanSegments = (int) $db->table('transcript_revision_segments as s')
                ->leftJoin('transcript_revisions as r', 'r.id', '=', 's.revision_id')
                ->whereNull('r.id')
                ->count();

            if ($orphanSegments > 0) {
                $violations[] = sprintf('%d transcript_revision_segments reference a missing revision.', $orphanSegments);
            }
        } catch (Throwable $exception) {
            $violations[] = 'Revision-graph reconciliation could not run: '.$exception->getMessage();
        }

        return ['counts' => $counts, 'violations' => $violations];
    }
}
