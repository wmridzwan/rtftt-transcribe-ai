<?php

namespace App\Retention;

use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\RetentionPurgeAudit;
use App\Models\StagingClaim;
use App\Models\Transcription;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scheduled retention purge (P7-011, D7-06).
 *
 * Deletes PHYSICAL BYTES for retention-eligible lifecycles and
 * tombstones the owning rows — never hard-deletes domain/history
 * records (§8.4). Idempotent and re-entry safe: tombstoned/claimed
 * objects are skipped, already-missing objects are recorded without
 * error, per-object failures never present as success, and a later run
 * resumes whatever an interrupted run left behind.
 *
 * Eligibility clock (§6.10): `transcriptions.completed_at + 30 days`
 * (`completed_at IS NULL` → never eligible); abandoned staging per
 * ADR-009 (`expires_at`, i.e. created + 24h). Quarantine, backups,
 * and history rows are never enumerated.
 */
final class RetentionPurge
{
    public const RETENTION_DAYS = 30;

    public const STAGING_RETENTION_HOURS = 24;

    /**
     * @return array{run_id: string, dry_run: bool, outcomes: array<string, int>, failures: list<string>}
     */
    public static function run(bool $dryRun = false, ?string $runId = null): array
    {
        $runId ??= (string) Str::uuid();
        $outcomes = [
            RetentionPurgeAudit::OUTCOME_PURGED => 0,
            RetentionPurgeAudit::OUTCOME_ABSENT => 0,
            RetentionPurgeAudit::OUTCOME_FAILED => 0,
            RetentionPurgeAudit::OUTCOME_SKIPPED => 0,
        ];
        $failures = [];

        $cutoff = now()->subDays(self::RETENTION_DAYS);

        Transcription::query()
            ->where('status', TranscriptionStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($transcriptions) use ($dryRun, $runId, &$outcomes, &$failures): void {
                foreach ($transcriptions as $transcription) {
                    $outcome = self::purgeTranscriptionMedia($transcription->id, $dryRun, $runId);
                    $outcomes[$outcome['outcome']]++;
                    if ($outcome['outcome'] === RetentionPurgeAudit::OUTCOME_FAILED) {
                        $failures[] = $outcome['reason'];
                    }
                }
            });

        self::purgeStaging($dryRun, $runId, $outcomes, $failures);

        Log::channel('structured')->info('retention.purge.summary', [
            'run_id' => $runId,
            'dry_run' => $dryRun,
            'outcomes' => $outcomes,
        ]);

        return ['run_id' => $runId, 'dry_run' => $dryRun, 'outcomes' => $outcomes, 'failures' => $failures];
    }

    /**
     * @return array{outcome: string, reason: string}
     */
    private static function purgeTranscriptionMedia(int $transcriptionId, bool $dryRun, string $runId): array
    {
        $transcription = Transcription::query()->find($transcriptionId);

        if ($transcription === null) {
            return self::audit($runId, 'transcription', $transcriptionId, null,
                RetentionPurgeAudit::OUTCOME_SKIPPED, 'row already removed (concurrent user deletion)');
        }

        $media = $transcription->mediaFile()->first();

        if ($media === null) {
            return self::audit($runId, 'transcription', $transcriptionId, null,
                RetentionPurgeAudit::OUTCOME_SKIPPED, 'no media row (concurrent user deletion)');
        }

        if ($media->purged_at !== null) {
            return self::audit($runId, 'media', $media->id, $media->storage_path,
                RetentionPurgeAudit::OUTCOME_SKIPPED, 'already purged (idempotent re-run)');
        }

        if ($dryRun) {
            return self::audit($runId, 'media', $media->id, $media->storage_path,
                RetentionPurgeAudit::OUTCOME_SKIPPED, 'dry-run: eligible, nothing deleted');
        }

        if (self::isProtectedPath($media->storage_path)) {
            return self::audit($runId, 'media', $media->id, $media->storage_path,
                RetentionPurgeAudit::OUTCOME_SKIPPED, 'protected storage class (quarantine or outside media tree)');
        }

        $storage = MediaFile::storage();

        try {
            // Presence is captured BEFORE acting: after a successful
            // delete the object is (correctly) gone, which must not be
            // confused with "was already absent".
            $absent = ! $storage->exists($media->storage_path);
            $finalOutcome = $absent
                ? RetentionPurgeAudit::OUTCOME_ABSENT
                : RetentionPurgeAudit::OUTCOME_PURGED;

            if (! $absent) {
                $storage->delete($media->storage_path);

                if (self::verifyStillPresent($storage, (string) $media->storage_path)) {
                    return self::audit($runId, 'media', $media->id, $media->storage_path,
                        RetentionPurgeAudit::OUTCOME_FAILED, 'filesystem deletion did not remove the object');
                }
            }

            DB::transaction(function () use ($media, $runId, $absent, $finalOutcome): void {
                $fresh = MediaFile::query()->lockForUpdate()->find($media->id);

                if ($fresh === null) {
                    throw new StalePurgeCandidate('media row removed during purge (concurrent user deletion)');
                }

                if ($fresh->purged_at !== null) {
                    return;
                }

                $fresh->forceFill(['purged_at' => now()])->save();

                RetentionPurgeAudit::query()->create([
                    'run_id' => $runId,
                    'purgeable_type' => 'media',
                    'purgeable_id' => $fresh->id,
                    'artifact_path' => $media->storage_path,
                    'outcome' => $finalOutcome,
                    'reason' => $absent
                        ? 'bytes already absent; row tombstoned'
                        : 'bytes deleted and verified; row tombstoned',
                ]);
            });
        } catch (StalePurgeCandidate $stale) {
            return self::audit($runId, 'media', $media->id, $media->storage_path,
                RetentionPurgeAudit::OUTCOME_SKIPPED, $stale->getMessage());
        } catch (Throwable $exception) {
            return self::audit($runId, 'media', $media->id, $media->storage_path,
                RetentionPurgeAudit::OUTCOME_FAILED, 'persistence failure: '.$exception->getMessage());
        }

        Log::channel('structured')->info('retention.purge.'.$finalOutcome, [
            'run_id' => $runId,
            'purgeable_type' => 'media',
            'purgeable_id' => $media->id,
            'artifact_path' => $media->storage_path,
            'reason' => 'recorded in-transaction (no duplicate audit row)',
        ]);

        return ['outcome' => $finalOutcome, 'reason' => 'recorded'];
    }

    /**
     * Abandoned staging (ADR-009 via the P2-004A2 claim protocol): claim
     * expired unheld rows with a guarded UPDATE (a concurrent uploader
     * holding the row wins the race and the purge backs off), then
     * delete the file, verify, and remove the row. Orphan staging files
     * (no claim row, older than 24h) are deleted directly. Fresh
     * artifacts are always skipped.
     *
     * @param  array<string, int>  $outcomes
     * @param  list<string>  $failures
     */
    private static function purgeStaging(bool $dryRun, string $runId, array &$outcomes, array &$failures): void
    {
        $storage = MediaFile::storage();
        $stagingRoot = trim((string) config('media.staging_directory', 'media/.staging'), '/');

        $candidates = StagingClaim::query()
            ->where('held_by', 'upload')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        foreach ($candidates as $claim) {
            $claimed = StagingClaim::query()
                ->where('id', $claim->id)
                ->where('held_by', 'upload')
                ->update(['held_by' => 'cleanup', 'cleanup_claimed_at' => now(), 'updated_at' => now()]);

            if ($claimed === 0) {
                self::record($runId, 'staging-claim', $claim->id, $claim->staging_path,
                    RetentionPurgeAudit::OUTCOME_SKIPPED, 'lost the claim race (concurrent uploader holds it)', $outcomes, $failures);

                continue;
            }

            if ($dryRun) {
                self::record($runId, 'staging-claim', $claim->id, $claim->staging_path,
                    RetentionPurgeAudit::OUTCOME_SKIPPED, 'dry-run: eligible, nothing deleted', $outcomes, $failures);

                continue;
            }

            // The claim row is removed ONLY when the physical disposition
            // is resolved (purged or already absent). On filesystem
            // failure the row is retained and the claim is released back
            // to `upload` so a later run deterministically retries — the
            // state needed for recovery is never discarded with the
            // attempt, and no false success is recorded.
            $outcome = self::deleteStagingObject($storage, $claim->staging_path, 'staging-claim', $claim->id, $dryRun, $runId, $outcomes, $failures);

            if ($outcome === RetentionPurgeAudit::OUTCOME_FAILED) {
                // Release via query-builder UPDATE (not the stale in-memory
                // model, which never saw the claim UPDATE and would save
                // nothing): the next run deterministically re-claims.
                StagingClaim::query()->where('id', $claim->id)->update([
                    'held_by' => 'upload',
                    'cleanup_claimed_at' => null,
                    'updated_at' => now(),
                ]);

                continue;
            }

            $claim->delete();
        }

        $claimedPaths = StagingClaim::query()->pluck('staging_path')->all();

        foreach ($storage->allFiles($stagingRoot) as $path) {
            if (in_array($path, $claimedPaths, true)) {
                continue;
            }

            if (! self::isStagingOrphanEligible((int) $storage->lastModified($path), time())) {
                self::record($runId, 'staging-orphan', null, $path,
                    RetentionPurgeAudit::OUTCOME_SKIPPED, 'fresh orphan (under 24h)', $outcomes, $failures);

                continue;
            }

            if ($dryRun) {
                self::record($runId, 'staging-orphan', null, $path,
                    RetentionPurgeAudit::OUTCOME_SKIPPED, 'dry-run: eligible, nothing deleted', $outcomes, $failures);

                continue;
            }

            self::deleteStagingObject($storage, $path, 'staging-orphan', null, $dryRun, $runId, $outcomes, $failures);
        }
    }

    /**
     * Filesystem re-probe after a delete attempt. A dedicated method
     * (rather than re-reading a cached check) because deletion changes
     * the answer: the object must be independently observed gone before
     * any success is recorded.
     */
    private static function verifyStillPresent(FilesystemAdapter $storage, string $path): bool
    {
        clearstatcache();

        return $storage->exists($path);
    }

    /**
     * Pure age gate for unclaimed staging files (ADR-009 24h rule).
     * Extracted so the boundary is unit-testable without filesystem
     * mtime control.
     */
    public static function isStagingOrphanEligible(int $lastModified, int $now): bool
    {
        return ($now - $lastModified) >= self::STAGING_RETENTION_HOURS * 3600;
    }

    /**
     * @param  array<string, int>  $outcomes
     * @param  list<string>  $failures
     * @return string the recorded outcome (so callers can gate row removal on resolved disposition)
     */
    private static function deleteStagingObject(FilesystemAdapter $storage, ?string $path, string $type, ?int $id, bool $dryRun, string $runId, array &$outcomes, array &$failures): string
    {
        if ($path === null || $path === '' || ! $storage->exists($path)) {
            self::record($runId, $type, $id, $path,
                RetentionPurgeAudit::OUTCOME_ABSENT, 'already absent', $outcomes, $failures);

            return RetentionPurgeAudit::OUTCOME_ABSENT;
        }

        try {
            $storage->delete($path);

            if (self::verifyStillPresent($storage, $path)) {
                self::record($runId, $type, $id, $path,
                    RetentionPurgeAudit::OUTCOME_FAILED, 'filesystem deletion did not remove the object', $outcomes, $failures);

                return RetentionPurgeAudit::OUTCOME_FAILED;
            }

            self::record($runId, $type, $id, $path,
                RetentionPurgeAudit::OUTCOME_PURGED, 'staging object deleted and verified', $outcomes, $failures);

            return RetentionPurgeAudit::OUTCOME_PURGED;
        } catch (Throwable $exception) {
            self::record($runId, $type, $id, $path,
                RetentionPurgeAudit::OUTCOME_FAILED, 'filesystem failure: '.$exception->getMessage(), $outcomes, $failures);

            return RetentionPurgeAudit::OUTCOME_FAILED;
        }
    }

    private static function isProtectedPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return true;
        }

        $mediaRoot = trim((string) config('media.storage_directory', 'media'), '/');
        $stagingRoot = trim((string) config('media.staging_directory', 'media/.staging'), '/');

        if (str_starts_with($path, 'quarantine/') || str_contains($path, '/quarantine/')) {
            return true;
        }

        return ! str_starts_with($path, $mediaRoot.'/') && ! str_starts_with($path, $stagingRoot.'/');
    }

    /**
     * @return array{outcome: string, reason: string}
     */
    private static function audit(string $runId, string $type, ?int $id, ?string $path, string $outcome, string $reason): array
    {
        RetentionPurgeAudit::query()->create([
            'run_id' => $runId,
            'purgeable_type' => $type,
            'purgeable_id' => $id,
            'artifact_path' => $path,
            'outcome' => $outcome,
            'reason' => $reason,
        ]);

        Log::channel('structured')->info('retention.purge.'.$outcome, [
            'run_id' => $runId,
            'purgeable_type' => $type,
            'purgeable_id' => $id,
            'artifact_path' => $path,
            'reason' => $reason,
        ]);

        return ['outcome' => $outcome, 'reason' => $reason];
    }

    /**
     * @param  array<string, int>  $outcomes
     * @param  list<string>  $failures
     */
    private static function record(string $runId, string $type, ?int $id, ?string $path, string $outcome, string $reason, array &$outcomes, array &$failures): void
    {
        $result = self::audit($runId, $type, $id, $path, $outcome, $reason);
        $outcomes[$outcome]++;

        if ($outcome === RetentionPurgeAudit::OUTCOME_FAILED) {
            $failures[] = $reason.' ('.$type.'#'.($id ?? '?').')';
        }
    }
}
