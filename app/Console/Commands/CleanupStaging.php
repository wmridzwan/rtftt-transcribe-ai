<?php

namespace App\Console\Commands;

use App\Events\StagingCleanupCandidateObserved;
use App\Models\MediaFile;
use App\Models\StagingClaim;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('media:cleanup-staging {--dry-run : Perform discovery without deleting files}')]
#[Description('Remove abandoned staging files older than the configured retention period')]
class CleanupStaging extends Command
{
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

                // Check if there is an active claim for this attempt
                $claim = StagingClaim::where('user_id', (int) $ownerId)
                    ->where('upload_attempt_id', $attemptId)
                    ->first();

                if ($claim !== null && $claim->isActive()) {
                    $this->line("  <comment>Deferred</comment>: {$attemptDir} (active claim, expires {$claim->expires_at->diffForHumans()})");
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

                    $deletedCandidate = DB::transaction(function () use ($disk, $ownerId, $attemptId, $attemptDir): bool {
                        $claim = StagingClaim::query()
                            ->where('user_id', (int) $ownerId)
                            ->where('upload_attempt_id', $attemptId)
                            ->lockForUpdate()
                            ->first();

                        if ($claim !== null && $claim->isActive()) {
                            return false;
                        }

                        if (MediaFile::query()
                            ->where('user_id', (int) $ownerId)
                            ->where('upload_attempt_id', $attemptId)
                            ->exists()) {
                            return false;
                        }

                        if ($claim === null) {
                            // A cleanup claim closes the no-row gap. An upload
                            // claim upsert blocks on this row until deletion
                            // commits, then writes only after the directory is
                            // gone.
                            $claim = StagingClaim::query()->create([
                                'user_id' => (int) $ownerId,
                                'upload_attempt_id' => $attemptId,
                                'staging_path' => $attemptDir,
                                'claimed_at' => now(),
                                'expires_at' => now()->addHours((int) config('media.temporary_retention_hours', 24)),
                            ]);
                        } else {
                            $claim->update([
                                'staging_path' => $attemptDir,
                                'claimed_at' => now(),
                                'expires_at' => now()->addHours((int) config('media.temporary_retention_hours', 24)),
                            ]);
                        }

                        foreach ($disk->allFiles($attemptDir) as $file) {
                            if (! $disk->delete($file)) {
                                throw new \RuntimeException("Failed to delete file: {$file}");
                            }
                        }

                        if (! $disk->deleteDirectory($attemptDir)) {
                            throw new \RuntimeException("Failed to delete directory: {$attemptDir}");
                        }

                        $claim->delete();

                        return true;
                    });

                    if ($deletedCandidate) {
                        $this->line("  <info>Deleted</info>: {$attemptDir}");
                        $deleted++;
                    } else {
                        $this->line("  <comment>Deferred</comment>: {$attemptDir} (claim or committed media observed before deletion)");
                        $deferred++;
                    }
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
        $this->line("  Failed:     {$failed} deletion failures");
        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run complete. No files were deleted.');
        } else {
            $this->info('Cleanup complete.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
