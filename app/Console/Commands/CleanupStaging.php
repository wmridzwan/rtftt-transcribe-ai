<?php

namespace App\Console\Commands;

use App\Events\StagingCleanupCandidateObserved;
use App\Models\MediaFile;
use App\Models\StagingClaim;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('media:cleanup-staging {--dry-run : Perform discovery without deleting files}')]
#[Description('Remove abandoned staging files older than the configured retention period')]
class CleanupStaging extends Command
{
    private const CRASH_RECOVERY_TIMEOUT_MINUTES = 15;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = MediaFile::storage();
        $stagingRoot = trim((string) config('media.staging_directory'), '/');
        $retentionHours = (int) config('media.temporary_retention_hours', 24);
        $cutoff = Carbon::now()->subHours($retentionHours);

        $this->newLine();
        $this->line($dryRun ? '<info>DRY RUN</info> — no files will be deleted.' : '<comment>Running cleanup…</comment>');
        $this->newLine();

        if (! $disk->exists($stagingRoot)) {
            $this->info('Staging root does not exist. Nothing to clean.');

            return self::SUCCESS;
        }

        $ownerDirs = $disk->directories($stagingRoot);
        $discovered = 0;
        $eligible = 0;
        $deleted = 0;
        $skipped = 0;
        $deferred = 0;
        $failed = 0;
        $reclaimed = 0;

        foreach ($ownerDirs as $ownerDir) {
            $ownerId = basename($ownerDir);

            if (! is_numeric($ownerId)) {
                $this->warn("  Skipped non-numeric owner directory: {$ownerDir}");
                $skipped++;

                continue;
            }

            $attemptDirs = $disk->directories($ownerDir);

            foreach ($attemptDirs as $attemptDir) {
                $discovered++;
                $attemptId = basename($attemptDir);

                if (! Str::isUuid($attemptId)) {
                    $this->warn("  Skipped non-UUID attempt directory: {$attemptDir}");
                    $skipped++;

                    continue;
                }

                // Crash-recovery: re-claim rows stuck in held_by='cleanup'
                // beyond the timeout window. A successfully reclaimed row
                // proceeds directly to re-verify + delete — the reclaim and
                // deletion path are one composed decision, not two
                // independent guarded statements.
                $reclaimedCount = DB::table('staging_claims')
                    ->where('user_id', (int) $ownerId)
                    ->where('upload_attempt_id', $attemptId)
                    ->where('held_by', 'cleanup')
                    ->where('cleanup_claimed_at', '<', Carbon::now()->subMinutes(self::CRASH_RECOVERY_TIMEOUT_MINUTES))
                    ->update([
                        'cleanup_claimed_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                if ($reclaimedCount > 0) {
                    $this->line("  <info>Reclaimed</info>: {$attemptDir} (crash-recovery re-claim after timeout)");
                    $reclaimed++;

                    // Re-verify that no MediaFile was committed for this attempt
                    // before deleting (defensive, cheap, not atomic with claim).
                    if (MediaFile::where('user_id', (int) $ownerId)
                        ->where('upload_attempt_id', $attemptId)
                        ->exists()) {
                        // Release the claim since a MediaFile was committed.
                        DB::table('staging_claims')
                            ->where('user_id', (int) $ownerId)
                            ->where('upload_attempt_id', $attemptId)
                            ->where('held_by', 'cleanup')
                            ->delete();

                        $this->line("  <comment>Skipped</comment>: {$attemptDir} (committed MediaFile discovered during crash-recovery)");
                        $skipped++;

                        continue;
                    }

                    // Step 3: Delete files OUTSIDE any database transaction.
                    $this->deleteAttemptFiles($disk, $attemptDir);

                    // Clean up the claim row after successful deletion.
                    DB::table('staging_claims')
                        ->where('user_id', (int) $ownerId)
                        ->where('upload_attempt_id', $attemptId)
                        ->where('held_by', 'cleanup')
                        ->delete();

                    $this->line("  <info>Deleted</info>: {$attemptDir}");
                    $deleted++;

                    continue;
                }

                // Check if there is an active upload claim for this attempt
                $activeClaim = StagingClaim::where('user_id', (int) $ownerId)
                    ->where('upload_attempt_id', $attemptId)
                    ->where('held_by', 'upload')
                    ->where('expires_at', '>', now())
                    ->first();

                if ($activeClaim !== null) {
                    $this->line("  <comment>Deferred</comment>: {$attemptDir} (active upload claim, expires {$activeClaim->expires_at->diffForHumans()})");
                    $deferred++;

                    continue;
                }

                // Check if there is a committed MediaFile for this attempt
                $mediaFile = MediaFile::where('user_id', (int) $ownerId)
                    ->where('upload_attempt_id', $attemptId)
                    ->first();

                if ($mediaFile !== null) {
                    $this->line("  <comment>Skipped</comment>: {$attemptDir} (committed MediaFile #{$mediaFile->id})");
                    $skipped++;

                    continue;
                }

                // Check age using the most recent file modification time
                $files = $disk->allFiles($attemptDir);
                $mostRecent = $disk->lastModified($attemptDir);

                foreach ($files as $file) {
                    $lastModified = $disk->lastModified($file);
                    if ($lastModified > $mostRecent) {
                        $mostRecent = $lastModified;
                    }
                }

                $lastModifiedCarbon = Carbon::createFromTimestamp($mostRecent);

                if ($lastModifiedCarbon->isAfter($cutoff)) {
                    $this->line("  <comment>Preserved</comment>: {$attemptDir} (last modified {$lastModifiedCarbon->diffForHumans()})");
                    $skipped++;

                    continue;
                }

                // Candidate is eligible for cleanup
                $eligible++;

                if ($dryRun) {
                    $this->line("  <info>Would delete</info>: {$attemptDir} (last modified {$lastModifiedCarbon->diffForHumans()})");
                    $deleted++;

                    continue;
                }

                try {
                    event(new StagingCleanupCandidateObserved((int) $ownerId, $attemptId, $attemptDir));

                    // Step 2: Claim the attempt atomically via guarded conditional
                    // UPDATE. No read-then-write. If this returns 0, the attempt
                    // is either: not present, still active, or already claimed by
                    // cleanup. In every case: do not delete. Defer.
                    $claimed = DB::table('staging_claims')
                        ->where('user_id', (int) $ownerId)
                        ->where('upload_attempt_id', $attemptId)
                        ->where('held_by', 'upload')
                        ->where('expires_at', '<', now())
                        ->update([
                            'held_by' => 'cleanup',
                            'cleanup_claimed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if ($claimed === 0) {
                        // No eligible claim found. Try insertOrIgnore for the
                        // no-row gap case.
                        try {
                            $inserted = DB::table('staging_claims')->insertOrIgnore([
                                'user_id' => (int) $ownerId,
                                'upload_attempt_id' => $attemptId,
                                'staging_path' => $attemptDir,
                                'held_by' => 'cleanup',
                                'cleanup_claimed_at' => now(),
                                'claimed_at' => now(),
                                'expires_at' => now()->addHours($retentionHours),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            // Foreign key constraint violation (user_id doesn't
                            // exist in users table). Defer this candidate.
                            $this->line("  <comment>Deferred</comment>: {$attemptDir} (user not found or constraint violation)");
                            $deferred++;

                            continue;
                        }

                        if ($inserted === 0) {
                            // Another process already claimed this row. Defer.
                            $this->line("  <comment>Deferred</comment>: {$attemptDir} (claim contention or no eligible row)");
                            $deferred++;

                            continue;
                        }

                        // insertOrIgnore succeeded — we now own the cleanup claim.
                    }

                    // Re-verify that no MediaFile was committed for this attempt
                    // before deleting (defensive, cheap, not atomic with claim).
                    if (MediaFile::where('user_id', (int) $ownerId)
                        ->where('upload_attempt_id', $attemptId)
                        ->exists()) {
                        // Release the claim since a MediaFile was committed.
                        DB::table('staging_claims')
                            ->where('user_id', (int) $ownerId)
                            ->where('upload_attempt_id', $attemptId)
                            ->where('held_by', 'cleanup')
                            ->delete();

                        $this->line("  <comment>Skipped</comment>: {$attemptDir} (committed MediaFile discovered before deletion)");
                        $skipped++;

                        continue;
                    }

                    // Step 3: Delete files OUTSIDE any database transaction.
                    $this->deleteAttemptFiles($disk, $attemptDir);

                    // Clean up the claim row after successful deletion.
                    DB::table('staging_claims')
                        ->where('user_id', (int) $ownerId)
                        ->where('upload_attempt_id', $attemptId)
                        ->where('held_by', 'cleanup')
                        ->delete();

                    $this->line("  <info>Deleted</info>: {$attemptDir}");
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->error("  Failed to delete {$attemptDir}: {$e->getMessage()}");
                    $failed++;
                }
            }

            // Remove empty owner directory if it has no remaining attempt directories
            if (! $dryRun && $disk->directories($ownerDir) === [] && $disk->files($ownerDir) === []) {
                $disk->deleteDirectory($ownerDir);
            }
        }

        // Summary
        $this->newLine();
        $this->line('<comment>Summary:</comment>');
        $this->line("  Discovered: {$discovered} attempt directories");
        $this->line("  Eligible:   {$eligible} candidates for cleanup");
        $this->line("  Deleted:    {$deleted} directories removed");
        $this->line("  Deferred:   {$deferred} active claims preserved");
        $this->line("  Skipped:    {$skipped} directories preserved");
        $this->line("  Reclaimed:  {$reclaimed} crash-recovery re-claims");
        $this->line("  Failed:     {$failed} deletion failures");
        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run complete. No files were deleted.');
        } else {
            $this->info('Cleanup complete.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function deleteAttemptFiles(FilesystemAdapter $disk, string $attemptDir): void
    {
        foreach ($disk->allFiles($attemptDir) as $file) {
            if (! $disk->delete($file)) {
                throw new \RuntimeException("Failed to delete file: {$file}");
            }
        }

        if (! $disk->deleteDirectory($attemptDir)) {
            throw new \RuntimeException("Failed to delete directory: {$attemptDir}");
        }
    }
}
