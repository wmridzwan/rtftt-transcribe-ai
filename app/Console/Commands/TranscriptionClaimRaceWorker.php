<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTranscription;
use App\Models\ProcessingJob;
use App\Testing\RaceConnectionPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ReflectionMethod;
use Throwable;

/**
 * Test harness command for the P3-006 processing-attempt claim race.
 *
 * Guarded to the testing environment. Each spawned process opens its own
 * SQLite connection to the shared race database and, after a filesystem
 * rendezvous, invokes the real (private) ProcessTranscription::claimAttempt()
 * implementation exactly once, writing the result to a sentinel file. This
 * proves the production CAS claim — not a re-implementation of it — under
 * genuine independent processes/connections.
 */
#[Signature('test:transcription-claim-race-worker {attemptId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for transcription attempt claim race testing (testing environment only)')]
class TranscriptionClaimRaceWorker extends Command
{
    protected $hidden = true;

    public function handle(): int
    {
        if (app()->environment('testing') === false) {
            $this->error('This command is only available in the testing environment.');

            return self::FAILURE;
        }

        $attemptId = (int) $this->argument('attemptId');
        $goFile = (string) $this->argument('goFile');
        $resultFile = (string) $this->argument('resultFile');
        $readyFile = (string) $this->argument('readyFile');
        $barrierDir = (string) $this->argument('barrierDir');

        file_put_contents($readyFile, 'ready');

        $timeout = 30;
        $start = time();

        while (! file_exists($goFile)) {
            if (time() - $start > $timeout) {
                $this->writeResult($resultFile, [
                    'status' => 'timeout',
                    'claimed' => 0,
                    'error' => 'Timed out waiting for go signal',
                ]);

                return self::FAILURE;
            }

            usleep(10_000); // 10ms
        }

        // Deterministic two-phase barrier: announce arrival at the claim point
        // and wait until the peer process has done the same, so both attempt
        // the guarded UPDATE simultaneously.
        file_put_contents($barrierDir.'/'.getmypid().'.at_claim', '1');

        $barrierStart = time();

        while (count(glob($barrierDir.'/*.at_claim') ?: []) < 2) {
            if (time() - $barrierStart > $timeout) {
                $this->writeResult($resultFile, [
                    'status' => 'timeout',
                    'claimed' => 0,
                    'error' => 'Timed out waiting at the claim barrier',
                ]);

                return self::FAILURE;
            }

            usleep(5_000); // 5ms
        }

        try {
            // Wait rather than error if the other connection briefly holds the
            // write lock; the guarded UPDATE then observes the committed
            // state. Driver-aware: SQLite waits via PRAGMA busy_timeout;
            // PostgreSQL needs no setup (MVCC row locks) and must never
            // receive a SQLite PRAGMA (it throws there).
            RaceConnectionPolicy::applyLockWait(10000);

            $attempt = ProcessingJob::query()->findOrFail($attemptId);

            $job = new ProcessTranscription($attempt->transcription_id, $attempt->getKey());

            $method = new ReflectionMethod($job, 'claimAttempt');
            $claimed = (bool) $method->invoke($job, $attempt);

            $this->writeResult($resultFile, [
                'status' => $claimed ? 'claimed' : 'lost_race',
                'claimed' => $claimed ? 1 : 0,
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult($resultFile, [
                'status' => 'error',
                'claimed' => 0,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeResult(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload));
    }
}
