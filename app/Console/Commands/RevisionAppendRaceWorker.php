<?php

namespace App\Console\Commands;

use App\Editing\RevisionConflictException;
use App\Editing\RevisionFactory;
use App\Editing\RevisionRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Test harness command for the P6-002 revision-append race.
 *
 * Guarded to the testing environment. Each spawned process opens its own SQLite
 * connection to the shared race database and, after a filesystem rendezvous,
 * allocates the next transcription-scoped version and attempts to append a
 * revision through the real {@see RevisionRepository} implementation exactly
 * once, writing the outcome to a sentinel file. Proves that two independent
 * writers cannot both persist the same `(transcription_id, version)` and that a
 * lost race surfaces as the canonical domain conflict.
 */
#[Signature('test:revision-append-race-worker {transcriptionId} {baseRevisionId} {goFile} {resultFile} {readyFile} {barrierDir}')]
#[Description('Test harness for revision append race testing (testing environment only)')]
class RevisionAppendRaceWorker extends Command
{
    protected $hidden = true;

    public function handle(): int
    {
        if (app()->environment('testing') === false) {
            $this->error('This command is only available in the testing environment.');

            return self::FAILURE;
        }

        $transcriptionId = (int) $this->argument('transcriptionId');
        $baseRevisionId = (string) $this->argument('baseRevisionId');
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

        file_put_contents($barrierDir.'/'.getmypid().'.at_append', '1');

        $barrierStart = time();

        while (count(glob($barrierDir.'/*.at_append') ?: []) < 2) {
            if (time() - $barrierStart > $timeout) {
                $this->writeResult($resultFile, ['status' => 'timeout', 'error' => 'Timed out at the append barrier']);

                return self::FAILURE;
            }

            usleep(5_000);
        }

        try {
            DB::statement('PRAGMA busy_timeout = 15000');

            $repository = app(RevisionRepository::class);
            $factory = new RevisionFactory;

            $base = $repository->find($baseRevisionId);

            if ($base === null) {
                $this->writeResult($resultFile, ['status' => 'error', 'error' => 'Base revision not found']);

                return self::FAILURE;
            }

            $revision = $factory->derive($base, 1, [], $repository);
            $repository->append($revision, $baseRevisionId);

            $this->writeResult($resultFile, [
                'status' => 'success',
                'version' => $revision->version,
                'revisionId' => $revision->revisionId,
            ]);

            return self::SUCCESS;
        } catch (RevisionConflictException $exception) {
            $this->writeResult($resultFile, [
                'status' => 'conflict',
                'error' => $exception->getMessage(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeResult($resultFile, [
                'status' => 'error',
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
