<?php

namespace App\Console\Commands;

use App\Actions\TranslationOrchestrator;
use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Testing\RaceConnectionPolicy;
use App\Translation\TranslationTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Throwable;

/**
 * Test harness command for the P5-004B concurrent first-request race.
 *
 * Guarded to the testing environment. Each spawned process opens its own SQLite
 * connection to the shared race database and, after a filesystem rendezvous,
 * invokes the real App\Actions\TranslationOrchestrator::request() exactly once
 * for the same (transcription, target). The queue is faked so no inference
 * runs. Proves that concurrent first requests converge on one row without a raw
 * constraint exception under genuine independent processes/connections.
 */
#[Signature('test:translation-request-race-worker {transcriptionId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for the P5-004B translation request race (testing environment only)')]
class TranslationRequestRaceWorker extends Command
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
                $this->writeResult($resultFile, ['status' => 'timeout', 'error' => 'Timed out waiting for go signal']);

                return self::FAILURE;
            }

            usleep(10_000);
        }

        file_put_contents($barrierDir.'/'.getmypid().'.at_request', '1');

        $barrierStart = time();

        while (count(glob($barrierDir.'/*.at_request') ?: []) < 2) {
            if (time() - $barrierStart > $timeout) {
                $this->writeResult($resultFile, ['status' => 'timeout', 'error' => 'Timed out waiting at the request barrier']);

                return self::FAILURE;
            }

            usleep(5_000);
        }

        try {
            RaceConnectionPolicy::applyLockWait(10000);

            Queue::fake();

            $transcription = Transcription::query()->findOrFail($transcriptionId);

            $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

            $root = Queue::getFacadeRoot();
            $dispatched = $root instanceof QueueFake
                ? $root->pushed(ProcessTranslation::class)->count()
                : 0;

            $this->writeResult($resultFile, [
                'status' => 'ok',
                'translation_id' => $translation->getKey(),
                'dispatched' => $dispatched,
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult($resultFile, [
                'status' => 'error',
                'error' => $exception::class.': '.$exception->getMessage(),
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
