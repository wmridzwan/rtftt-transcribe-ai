<?php

namespace App\Console\Commands;

use App\Actions\TranscriptionRetry;
use App\Models\Transcription;
use App\Testing\RaceConnectionPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Test harness command for the P3-007 manual-retry concurrency race.
 *
 * Guarded to the testing environment. Each spawned process opens its own
 * SQLite connection to the shared race database and, after a filesystem
 * rendezvous, invokes the real (production) App\Actions\TranscriptionRetry
 * action exactly once, writing the result to a sentinel file. This proves the
 * production retry CAS — not a re-implementation of it — under genuine
 * independent processes/connections.
 */
#[Signature('test:transcription-retry-race-worker {transcriptionId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for the P3-007 transcription retry race (testing environment only)')]
class TranscriptionRetryRaceWorker extends Command
{
    protected $hidden = true;

    public function handle(): int
    {
        if (app()->environment('testing') === false) {
            $this->error('This command is only available in the testing environment.');

            return self::FAILURE;
        }

        $transcriptionId = (int) $this->argument('transcriptionId');
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
                    'attempt_id' => null,
                    'error' => 'Timed out waiting for go signal',
                ]);

                return self::FAILURE;
            }

            usleep(10_000); // 10ms
        }

        // Deterministic two-phase barrier: announce arrival at the retry point
        // and wait until the peer process has done the same, so both attempt
        // the guarded CAS simultaneously.
        file_put_contents($barrierDir.'/'.getmypid().'.at_retry', '1');

        $barrierStart = time();

        while (count(glob($barrierDir.'/*.at_retry') ?: []) < 2) {
            if (time() - $barrierStart > $timeout) {
                $this->writeResult($resultFile, [
                    'status' => 'timeout',
                    'attempt_id' => null,
                    'error' => 'Timed out waiting at the retry barrier',
                ]);

                return self::FAILURE;
            }

            usleep(5_000); // 5ms
        }

        try {
            // Wait rather than error if the other connection briefly holds the
            // SQLite write lock; the guarded CAS then observes the committed
            // state.
            RaceConnectionPolicy::applyLockWait(10000);

            $transcription = Transcription::query()->findOrFail($transcriptionId);

            $attempt = app(TranscriptionRetry::class)->retry($transcription);

            $this->writeResult($resultFile, [
                'status' => 'ok',
                'attempt_id' => $attempt->getKey(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult($resultFile, [
                'status' => 'error',
                'attempt_id' => null,
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
