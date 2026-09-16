<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test harness command for staging claim race testing.
 *
 * This command is guarded to exist only in the testing environment.
 * It performs a single atomic claim operation and writes the result
 * to a sentinel file for coordination with the test process.
 */
#[Signature('test:staging-race-worker {role : upload or cleanup} {userId} {attemptId} {stagingPath} {goFile} {resultFile} {readyFile}')]
#[Description('Test harness for staging claim race testing (testing environment only)')]
class StagingRaceWorker extends Command
{
    protected $hidden = true;

    public function handle(): int
    {
        if (app()->environment('testing') === false) {
            $this->error('This command is only available in the testing environment.');

            return self::FAILURE;
        }

        $role = $this->argument('role');
        $userId = (int) $this->argument('userId');
        $attemptId = $this->argument('attemptId');
        $stagingPath = $this->argument('stagingPath');
        $goFile = $this->argument('goFile');
        $resultFile = $this->argument('resultFile');
        $readyFile = $this->argument('readyFile');

        // Write ready sentinel
        file_put_contents($readyFile, $role);

        // Poll for go signal
        $timeout = 30;
        $start = time();
        while (! file_exists($goFile)) {
            if (time() - $start > $timeout) {
                file_put_contents($resultFile, json_encode([
                    'role' => $role,
                    'status' => 'timeout',
                    'error' => 'Timed out waiting for go signal',
                ]));

                return self::FAILURE;
            }
            usleep(10_000); // 10ms
        }

        // Small additional delay to ensure both processes are ready
        usleep(50_000); // 50ms

        try {
            if ($role === 'upload') {
                return $this->attemptUploadClaim($userId, $attemptId, $stagingPath, $resultFile);
            } elseif ($role === 'cleanup') {
                return $this->attemptCleanupClaim($userId, $attemptId, $stagingPath, $resultFile);
            } else {
                file_put_contents($resultFile, json_encode([
                    'role' => $role,
                    'status' => 'error',
                    'error' => "Unknown role: {$role}",
                ]));

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            file_put_contents($resultFile, json_encode([
                'role' => $role,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]));

            return self::FAILURE;
        }
    }

    private function attemptUploadClaim(int $userId, string $attemptId, string $stagingPath, string $resultFile): int
    {
        // Step 1: InsertOrIgnore
        $inserted = DB::table('staging_claims')->insertOrIgnore([
            'user_id' => $userId,
            'upload_attempt_id' => $attemptId,
            'staging_path' => $stagingPath,
            'held_by' => 'upload',
            'claimed_at' => now(),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            // Check if cleanup owns it
            $claim = DB::table('staging_claims')
                ->where('user_id', $userId)
                ->where('upload_attempt_id', $attemptId)
                ->first();

            if ($claim !== null && $claim->held_by === 'cleanup') {
                file_put_contents($resultFile, json_encode([
                    'role' => 'upload',
                    'status' => 'lost_race',
                    'detail' => 'Cleanup has claimed this attempt',
                    'inserted' => 0,
                    'held_by' => 'cleanup',
                ]));

                return self::SUCCESS;
            }

            // Same-attempt retry renewal — check affected-row count.
            // If 0, cleanup won the race in the window between the
            // insertOrIgnore and this UPDATE.
            $renewed = DB::table('staging_claims')
                ->where('user_id', $userId)
                ->where('upload_attempt_id', $attemptId)
                ->where('held_by', 'upload')
                ->update([
                    'expires_at' => now()->addHours(24),
                    'updated_at' => now(),
                ]);

            if ($renewed === 0) {
                file_put_contents($resultFile, json_encode([
                    'role' => 'upload',
                    'status' => 'lost_race',
                    'detail' => 'Cleanup won race during renewal',
                    'inserted' => 0,
                    'renewed' => 0,
                    'held_by' => DB::table('staging_claims')
                        ->where('user_id', $userId)
                        ->where('upload_attempt_id', $attemptId)
                        ->value('held_by'),
                ]));

                return self::SUCCESS;
            }

            file_put_contents($resultFile, json_encode([
                'role' => 'upload',
                'status' => 'renewed',
                'inserted' => 0,
                'renewed' => $renewed,
            ]));

            return self::SUCCESS;
        }

        file_put_contents($resultFile, json_encode([
            'role' => 'upload',
            'status' => 'inserted',
            'inserted' => 1,
        ]));

        return self::SUCCESS;
    }

    private function attemptCleanupClaim(int $userId, string $attemptId, string $stagingPath, string $resultFile): int
    {
        // Step 2: Guarded conditional UPDATE
        $claimed = DB::table('staging_claims')
            ->where('user_id', $userId)
            ->where('upload_attempt_id', $attemptId)
            ->where('held_by', 'upload')
            ->where('expires_at', '<', now())
            ->update([
                'held_by' => 'cleanup',
                'cleanup_claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            // Try insertOrIgnore for no-row gap
            $inserted = DB::table('staging_claims')->insertOrIgnore([
                'user_id' => $userId,
                'upload_attempt_id' => $attemptId,
                'staging_path' => $stagingPath,
                'held_by' => 'cleanup',
                'cleanup_claimed_at' => now(),
                'claimed_at' => now(),
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            file_put_contents($resultFile, json_encode([
                'role' => 'cleanup',
                'status' => $inserted === 0 ? 'lost_race' : 'inserted_no_row_gap',
                'claimed' => 0,
                'inserted' => $inserted,
            ]));

            return self::SUCCESS;
        }

        file_put_contents($resultFile, json_encode([
            'role' => 'cleanup',
            'status' => 'claimed',
            'claimed' => 1,
        ]));

        return self::SUCCESS;
    }
}
