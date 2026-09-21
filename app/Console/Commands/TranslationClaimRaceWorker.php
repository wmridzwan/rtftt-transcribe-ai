<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Throwable;

/**
 * Test harness command for the P5-004 translation claim race.
 *
 * Guarded to the testing environment. Each spawned process opens its own SQLite
 * connection to the shared race database and, after a filesystem rendezvous,
 * invokes the real (private) ProcessTranslation::claim() implementation exactly
 * once, writing the result to a sentinel file. Proves the production CAS claim
 * under genuine independent processes/connections.
 */
#[Signature('test:translation-claim-race-worker {translationId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for translation claim race testing (testing environment only)')]
class TranslationClaimRaceWorker extends Command
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
                    'claimed' => 0,
                    'error' => 'Timed out waiting for go signal',
                ]);

                return self::FAILURE;
            }

            usleep(10_000);
        }

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

            usleep(5_000);
        }

        try {
            DB::statement('PRAGMA busy_timeout = 10000');

            $translation = Translation::query()->findOrFail($translationId);

            $job = new ProcessTranslation($translation->getKey(), $translation->transcription_id);

            $method = new ReflectionMethod($job, 'claim');
            $claimed = (bool) $method->invoke($job, $translation);

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
