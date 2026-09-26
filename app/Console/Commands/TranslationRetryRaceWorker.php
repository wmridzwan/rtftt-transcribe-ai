<?php

namespace App\Console\Commands;

use App\Actions\TranslationRetry;
use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Testing\RaceConnectionPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Throwable;

/**
 * Test harness command for the P5-005 manual-retry concurrency race.
 *
 * Guarded to the testing environment. Each spawned process opens its own SQLite
 * connection to the shared race database and, after a filesystem rendezvous,
 * invokes the real (production) App\Actions\TranslationRetry action exactly
 * once. The queue is faked so no inference runs; the number of dispatched jobs
 * identifies the CAS winner. This proves the production retry CAS under genuine
 * independent processes/connections.
 */
#[Signature('test:translation-retry-race-worker {translationId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for the P5-005 translation retry race (testing environment only)')]
class TranslationRetryRaceWorker extends Command
{
    protected $hidden = true;

    public function handle(): int
    {
        if (app()->environment('testing') === false) {
            $this->error('This command is only available in the testing environment.');

            return self::FAILURE;
        }

        $translationId = (int) $this->argument('translationId');
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
                    'translation_id' => null,
                    'dispatched' => 0,
                    'error' => 'Timed out waiting for go signal',
                ]);

                return self::FAILURE;
            }

            usleep(10_000);
        }

        file_put_contents($barrierDir.'/'.getmypid().'.at_retry', '1');

        $barrierStart = time();

        while (count(glob($barrierDir.'/*.at_retry') ?: []) < 2) {
            if (time() - $barrierStart > $timeout) {
                $this->writeResult($resultFile, [
                    'status' => 'timeout',
                    'translation_id' => null,
                    'dispatched' => 0,
                    'error' => 'Timed out waiting at the retry barrier',
                ]);

                return self::FAILURE;
            }

            usleep(5_000);
        }

        try {
            RaceConnectionPolicy::applyLockWait(10000);

            Queue::fake();

            $translation = Translation::query()->findOrFail($translationId);

            $retried = app(TranslationRetry::class)->retry($translation);

            $root = Queue::getFacadeRoot();
            $dispatched = $root instanceof QueueFake
                ? $root->pushed(ProcessTranslation::class)->count()
                : 0;

            $this->writeResult($resultFile, [
                'status' => 'ok',
                'translation_id' => $retried->getKey(),
                'attempt_token' => $retried->attempt_token,
                'dispatched' => $dispatched,
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult($resultFile, [
                'status' => 'error',
                'translation_id' => null,
                'dispatched' => 0,
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
