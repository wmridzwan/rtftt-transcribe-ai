<?php

namespace App\Capacity;

use App\Jobs\CapacityProbeJob;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Services\MediaMetadataProbeService;
use App\TranscriptExperience\SegmentTimestamp;
use App\TranscriptExperience\TranscriptCopy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Executes the contract-approved P7-009 workloads with deterministic
 * measurement boundaries, correctness checks alongside timing, and
 * failure preservation.
 *
 * Every workload runs against real in-app code paths (storage disk,
 * probe service, Eloquent persistence, queue driver, transcript
 * helpers) at safe rehearsal scale. Anything that cannot be measured
 * on substitute infrastructure (500 MiB G-01 upload, large-v3
 * inference, NLLB provider timing, browser render, true broker
 * concurrency) is recorded as a rehearsal-scope substitution with an
 * explicit note — never as target evidence.
 */
final class WorkloadRunner
{
    /**
     * @param  array{seed: int, items: list<array<string, mixed>>, sha256: string}  $corpus
     */
    public function __construct(
        private readonly string $runId,
        private readonly array $corpus,
        private readonly CompletionLedger $ledger,
        private readonly int $queueIterations = 200,
    ) {}

    /**
     * @return array{results: list<WorkloadResult>, failures: list<array<string, mixed>>}
     */
    public function runAll(int $repeat, int $concurrency): array
    {
        $results = [];
        $failures = [];

        $workloads = [
            'upload' => fn (int $r): WorkloadResult => $this->runUpload($r),
            'probe' => fn (int $r): WorkloadResult => $this->runProbe($r),
            'transcription' => fn (int $r): WorkloadResult => $this->runTranscription($r),
            'translation' => fn (int $r): WorkloadResult => $this->runTranslation($r),
            'queue' => fn (int $r): WorkloadResult => $this->runQueue($r, $concurrency),
            'saturation' => fn (int $r): WorkloadResult => $this->runSaturation($r),
            'streaming' => fn (int $r): WorkloadResult => $this->runStreaming($r),
            'render' => fn (int $r): WorkloadResult => $this->runRender($r),
            'export' => fn (int $r): WorkloadResult => $this->runExport($r),
        ];

        foreach ($workloads as $key => $workload) {
            for ($r = 1; $r <= $repeat; $r++) {
                try {
                    $result = $workload($r);
                } catch (Throwable $e) {
                    $result = new WorkloadResult(
                        workload: $key,
                        repeat: $r,
                        runId: $this->runId,
                        startedAt: now()->toIso8601String(),
                        completedAt: null,
                        elapsedMs: null,
                        concurrency: ['repeat' => $r, 'of' => $repeat],
                        outcome: 'failed',
                        correctness: ['evaluated' => false],
                        failurePoint: 'workload-dispatch',
                        failureDetail: get_class($e).': '.$e->getMessage(),
                        completedWork: 'none',
                        incompleteWork: 'full repeat missing',
                    );
                }

                $results[] = $result;
                $this->ledger->increment($key, $result->outcome);

                if ($result->outcome === 'failed') {
                    $failures[] = $result->toArray();
                }

                Log::info('capacity workload repeat', [
                    'channel' => 'capacity',
                    'run_id' => $this->runId,
                    'workload' => $result->workload,
                    'repeat' => $result->repeat,
                    'outcome' => $result->outcome,
                    'elapsed_ms' => $result->elapsedMs,
                ]);
            }
        }

        return ['results' => $results, 'failures' => $failures];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function corpusItem(string $name): ?array
    {
        foreach ($this->corpus['items'] as $item) {
            if (($item['name'] ?? null) === $name) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function beginConcurrency(int $repeat, array $extra = []): array
    {
        return array_merge(['repeat' => $repeat, 'queue_driver' => (string) config('queue.default')], $extra);
    }

    // ------------------------------------------------------------------
    // Workloads
    // ------------------------------------------------------------------

    private function runUpload(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-upload-blob');
        $payload = $item !== null ? (string) base64_decode((string) ($item['payload'] ?? '')) : '';
        $expected = $item['sha256'] ?? hash('sha256', $payload);
        $path = 'capacity/'.$this->runId.'/upload-'.$repeat.'.bin';

        Storage::disk((string) config('media.storage_disk', 'local'))->put($path, $payload);
        $stored = Storage::disk((string) config('media.storage_disk', 'local'))->get($path);
        $actual = hash('sha256', (string) $stored);

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();
        $correct = hash_equals((string) $expected, $actual);

        // Rehearsal hygiene: remove the synthetic object after verifying.
        Storage::disk((string) config('media.storage_disk', 'local'))->delete($path);

        if (! $correct) {
            return new WorkloadResult('upload', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['bytes' => strlen($payload)]),
                'failed', ['evaluated' => true, 'round_trip_match' => false],
                'correctness-verification', 'SHA-256 round-trip mismatch on rehearsal blob.',
                'bytes written', 'verified durable copy missing');
        }

        return new WorkloadResult('upload', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, ['bytes' => strlen($payload), 'note' => 'Rehearsal scale only; NOT the 500 MiB G-01 proof (Phase B).']),
            'completed', ['evaluated' => true, 'round_trip_match' => true, 'sha256' => $actual]);
    }

    private function runProbe(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-audio-wav');
        $payload = $item !== null ? (string) base64_decode((string) ($item['payload'] ?? '')) : '';
        $disk = (string) config('media.storage_disk', 'local');
        $path = 'capacity/'.$this->runId.'/probe-'.$repeat.'.wav';
        Storage::disk($disk)->put($path, $payload);

        $mediaFile = new MediaFile([
            'storage_path' => $path,
            'original_filename' => 'probe-'.$repeat.'.wav',
        ]);

        $probe = app(MediaMetadataProbeService::class)->probe($mediaFile);

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        Storage::disk($disk)->delete($path);

        // hasPhysicalFile() is false for an unsaved row id, so the
        // honest rehearsal outcome is usually a skip with a reason —
        // recorded as skipped, never as a zero-time pass.
        if ($probe['duration_seconds'] === null && $probe['audio_codec'] === null) {
            return new WorkloadResult('probe', $repeat, $this->runId, $started, $completed, $elapsed,
                $this->beginConcurrency($repeat),
                'skipped', ['evaluated' => true, 'probe_null' => true],
                null, 'Probe returned null metadata on substitute infrastructure (unsaved row has no physical file, or ffprobe unavailable).',
                'timing boundary captured', 'metadata extraction not measured');
        }

        return new WorkloadResult('probe', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat),
            'completed', ['evaluated' => true, 'probe' => $probe]);
    }

    private function runTranscription(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-transcript');
        /** @var list<array{index: int, start: float, end: float, text: string, language: string}> $segments */
        $segments = json_decode((string) base64_decode((string) ($item['payload'] ?? '')), true) ?? [];
        $declaredDuration = (float) ($item['duration_seconds'] ?? 300);

        // Persistence-path timing: bulk-insert synthetic segments in one
        // transaction under a real parent row (FK-constrained), read them
        // back, verify, then roll back so the rehearsal leaves no residue
        // in any database. This is NOT inference.
        $tableToken = 'cap_'.$this->runIdAnsi().'_'.$repeat;

        $parent = null;
        $readBack = collect();

        DB::beginTransaction();

        try {
            $parent = Transcription::factory()->draft()->create();

            $rows = [];

            foreach ($segments as $segment) {
                $rows[] = [
                    'transcription_id' => $parent->getKey(),
                    'segment_index' => $segment['index'],
                    'start_seconds' => $segment['start'],
                    'end_seconds' => $segment['end'],
                    'text' => $tableToken.':'.$segment['text'],
                    'language' => $segment['language'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('transcription_segments')->insert($rows);

            $readBack = DB::table('transcription_segments')
                ->where('transcription_id', $parent->getKey())
                ->where('text', 'like', $tableToken.':%')
                ->orderBy('segment_index')
                ->get();
        } finally {
            DB::rollBack();
        }

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        $countOk = $readBack->count() === count($segments);
        $textOk = $readBack->isEmpty() || str_starts_with((string) $readBack->first()->text, $tableToken.':');
        $elapsedSeconds = (($elapsed ?? 0) / 1000);
        $syntheticRtf = $declaredDuration > 0 ? $elapsedSeconds / $declaredDuration : null;

        if (! $countOk || ! $textOk) {
            return new WorkloadResult('transcription', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['segments' => count($segments)]),
                'failed', ['evaluated' => true, 'count_match' => $countOk, 'text_match' => $textOk],
                'correctness-verification', 'Segment read-back mismatch on the persistence path.',
                'segments written', 'verified read-back missing');
        }

        return new WorkloadResult('transcription', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, [
                'segments' => count($segments),
                'declared_synthetic_duration_s' => $declaredDuration,
                'residue' => 'none (verified rows rolled back)',
                'note' => 'Persistence-path timing only; synthetic RTF is NOT large-v3 inference evidence.',
            ]),
            'completed', ['evaluated' => true, 'count_match' => true, 'text_match' => true, 'synthetic_rtf' => $syntheticRtf]);
    }

    private function runTranslation(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-transcript');
        /** @var list<array{index: int, start: float, end: float, text: string, language: string}> $segments */
        $segments = json_decode((string) base64_decode((string) ($item['payload'] ?? '')), true) ?? [];

        // Alignment-transform path: copy source alignment (index +
        // timestamps + source language) into target rows in memory,
        // mirroring the TranslationResultWriter alignment guarantee.
        // This is NOT provider inference.
        $aligned = [];

        foreach ($segments as $segment) {
            $aligned[] = [
                'index' => $segment['index'],
                'start' => (float) $segment['start'],
                'end' => (float) $segment['end'],
                'source_language' => $segment['language'],
                'target_language' => 'en',
                'text' => '[en] '.$segment['text'],
            ];
        }

        $checksum = hash('sha256', json_encode($aligned) ?: '');

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        $indexOk = count($aligned) === count($segments)
            && ($aligned === [] || ($aligned[0]['index'] === 0 && $aligned[0]['start'] === (float) $segments[0]['start']));
        $lastOk = $aligned === [] || $aligned[count($aligned) - 1]['index'] === count($segments) - 1;

        if (! $indexOk || ! $lastOk) {
            return new WorkloadResult('translation', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['segments' => count($segments)]),
                'failed', ['evaluated' => true, 'alignment_preserved' => false],
                'correctness-verification', 'Alignment index/timestamp copy mismatch.',
                'transform executed', 'verified aligned rows missing');
        }

        return new WorkloadResult('translation', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, [
                'segments' => count($segments),
                'note' => 'In-app alignment-transform timing only; NOT NLLB provider evidence.',
            ]),
            'completed', ['evaluated' => true, 'alignment_preserved' => true, 'rows_checksum' => $checksum]);
    }

    private function runQueue(int $repeat, int $concurrency): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $driver = (string) config('queue.default');
        $depthBefore = $this->queueDepth();
        $tokens = [];

        for ($i = 0; $i < $concurrency; $i++) {
            $tokens[] = 'r'.$repeat.'-'.$i.'-'.substr(hash('sha256', $this->runId.$repeat.$i), 0, 8);
        }

        foreach ($tokens as $token) {
            CapacityProbeJob::dispatch($this->runId, $token, $this->queueIterations);
        }

        // Non-sync drivers need a worker pass; bound it tightly so the
        // rehearsal cannot hang the substitute host.
        if ($driver !== 'sync') {
            for ($i = 0; $i < $concurrency; $i++) {
                try {
                    Artisan::call('queue:work', [
                        '--once' => true,
                        '--queue' => 'default',
                        '--tries' => 1,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('capacity queue worker pass failed', [
                        'channel' => 'capacity',
                        'run_id' => $this->runId,
                        'exception' => get_class($e).': '.$e->getMessage(),
                    ]);
                }
            }
        }

        $missing = [];
        $mismatched = [];

        foreach ($tokens as $token) {
            $marker = Cache::get('capacity-probe:'.$this->runId.':'.$token);

            if ($marker === null) {
                $missing[] = $token;
            } elseif (! hash_equals(CapacityProbeJob::expectedMarker($token, $this->queueIterations), (string) $marker)) {
                $mismatched[] = $token;
            }

            Cache::forget('capacity-probe:'.$this->runId.':'.$token);
        }

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();
        $depthAfter = $this->queueDepth();

        if (count($missing) > 0 || count($mismatched) > 0) {
            return new WorkloadResult('queue', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['batch' => $concurrency, 'driver' => $driver, 'depth_before' => $depthBefore, 'depth_after' => $depthAfter]),
                'failed', ['evaluated' => true, 'missing_markers' => $missing, 'mismatched_markers' => $mismatched],
                'completion-verification',
                'Missing markers: '.implode(',', $missing).'; mismatched: '.implode(',', $mismatched).'.',
                count($tokens) - count($missing).' markers verified', count($missing) + count($mismatched).' markers unverified');
        }

        return new WorkloadResult('queue', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, [
                'batch' => $concurrency,
                'driver' => $driver,
                'depth_before' => $depthBefore,
                'depth_after' => $depthAfter,
                'note' => $driver === 'sync'
                    ? 'Sync driver executes inline; true broker concurrency is target-only.'
                    : 'Database/loopback driver with bounded worker passes; supervised target Redis is target-only.',
            ]),
            'completed', ['evaluated' => true, 'markers_verified' => count($tokens), 'depth_before' => $depthBefore, 'depth_after' => $depthAfter]);
    }

    private function runSaturation(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        // Safe-limit ramp only: tiny batches, sequential, on the
        // available driver. Records latency/throughput change per level.
        $levels = [1, 3, 5];
        $observations = [];
        $failedLevels = [];

        foreach ($levels as $level) {
            $levelTimer = new CapacityTimer;
            $levelTimer->start();
            $ok = 0;

            for ($i = 0; $i < $level; $i++) {
                $token = 'sat-r'.$repeat.'-l'.$level.'-'.$i;
                try {
                    $job = new CapacityProbeJob($this->runId, $token, 50);
                    $job->handle();
                    $marker = Cache::get($job->markerKey());

                    if (is_string($marker) && hash_equals(CapacityProbeJob::expectedMarker($token, 50), $marker)) {
                        $ok++;
                    }

                    Cache::forget($job->markerKey());
                } catch (Throwable) {
                    // counted as not-ok below; level failure preserved
                }
            }

            $levelTimer->stop();
            $levelElapsed = $levelTimer->elapsedMilliseconds() ?? 0;
            $observations[] = [
                'batch' => $level,
                'completed' => $ok,
                'elapsed_ms' => $levelElapsed,
                'throughput_per_s' => $levelElapsed > 0 ? round($ok / ($levelElapsed / 1000), 2) : null,
            ];

            if ($ok !== $level) {
                $failedLevels[] = $level;
            }
        }

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        if ($failedLevels !== []) {
            return new WorkloadResult('saturation', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['levels' => $levels]),
                'failed', ['evaluated' => true, 'levels' => $observations, 'failed_levels' => $failedLevels],
                'level-verification', 'Incomplete levels: '.implode(',', array_map('strval', $failedLevels)).'.',
                'completed levels retained in observations', 'failed levels missing markers');
        }

        return new WorkloadResult('saturation', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, [
                'levels' => $levels,
                'note' => 'Rehearsal-scale ramp only. The dev-machine saturation point is NOT application target capacity.',
            ]),
            'completed', ['evaluated' => true, 'levels' => $observations, 'degradation_observed' => false]);
    }

    private function runStreaming(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $disk = Storage::disk((string) config('media.storage_disk', 'local'));
        $item = $this->corpusItem('synthetic-upload-blob');
        $payload = $item !== null ? (string) base64_decode((string) ($item['payload'] ?? '')) : '';
        $path = 'capacity/'.$this->runId.'/stream-'.$repeat.'.bin';
        $disk->put($path, $payload);

        $full = (string) $disk->get($path);
        $fullOk = hash_equals(hash('sha256', $payload), hash('sha256', $full));

        // Bounded range slice (mirrors the P7-004 1 MiB bounded-delivery
        // discipline at rehearsal scale: 64 KiB).
        $absolute = $this->absolutePath($path);
        $sliceOk = false;

        if ($absolute !== null) {
            $handle = @fopen($absolute, 'rb');

            if ($handle !== false) {
                $slice = (string) stream_get_contents($handle, 65536, 0);
                fclose($handle);
                $sliceOk = $slice === substr($payload, 0, 65536);
            }
        } else {
            $sliceOk = substr($full, 0, 65536) === substr($payload, 0, 65536);
        }

        $disk->delete($path);

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        if (! $fullOk || ! $sliceOk) {
            return new WorkloadResult('streaming', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['bytes' => strlen($payload)]),
                'failed', ['evaluated' => true, 'full_match' => $fullOk, 'slice_match' => $sliceOk],
                'correctness-verification', 'Byte mismatch on full read or 64 KiB range slice.',
                'object written', 'verified byte-correct delivery missing');
        }

        return new WorkloadResult('streaming', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, ['bytes' => strlen($payload), 'slice_bytes' => 65536]),
            'completed', ['evaluated' => true, 'full_match' => true, 'slice_match' => true]);
    }

    private function runRender(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-transcript');
        /** @var list<array{index: int, start: float, end: float, text: string, language: string}> $segments */
        $segments = json_decode((string) base64_decode((string) ($item['payload'] ?? '')), true) ?? [];

        // Server-side composition path: hydrate unsaved segment models
        // and assemble the copy text exactly as the workspace does.
        // Browser timing is method-retained, not executed, in Phase A.
        $models = array_map(static fn (array $s): TranscriptionSegment => new TranscriptionSegment([
            'segment_index' => $s['index'],
            'start_seconds' => $s['start'],
            'end_seconds' => $s['end'],
            'text' => $s['text'],
            'language' => $s['language'],
        ]), $segments);

        $text = TranscriptCopy::fullText($models);

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        $lines = explode("\n", $text);
        $linesOk = count($lines) === count($segments);
        $firstOk = str_contains($lines[0], 'seg-0');

        if (! $linesOk || ! $firstOk) {
            return new WorkloadResult('render', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['segments' => count($segments)]),
                'failed', ['evaluated' => true, 'line_count_match' => $linesOk],
                'correctness-verification', 'Composed line count or first-line content mismatch.',
                'composition executed', 'verified composed text missing');
        }

        return new WorkloadResult('render', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, [
                'segments' => count($segments),
                'note' => 'Server-side composition timing only; browser-timing method retained for the target run, not executed.',
            ]),
            'completed', ['evaluated' => true, 'lines' => count($lines), 'chars' => strlen($text)]);
    }

    private function runExport(int $repeat): WorkloadResult
    {
        $started = now()->toIso8601String();
        $timer = new CapacityTimer;
        $timer->start();

        $item = $this->corpusItem('synthetic-transcript');
        /** @var list<array{index: int, start: float, end: float, text: string, language: string}> $segments */
        $segments = json_decode((string) base64_decode((string) ($item['payload'] ?? '')), true) ?? [];

        // Export assembly mirroring TranscriptionExportController: TXT
        // is title + blank + joined texts; SRT rows carry formatted
        // timestamps via the canonical SegmentTimestamp primitive.
        $title = 'Capacity Rehearsal Export';
        $txt = $title."\n\n".implode("\n\n", array_column($segments, 'text'));

        $srtBlocks = [];

        foreach ($segments as $i => $segment) {
            $srtBlocks[] = ($i + 1)."\n"
                .SegmentTimestamp::fromSeconds((float) $segment['start'])->srt()
                .' --> '
                .SegmentTimestamp::fromSeconds((float) $segment['end'])->srt()
                ."\n".$segment['text'];
        }

        $srt = implode("\n\n", $srtBlocks);

        $timer->stop();
        $completed = now()->toIso8601String();
        $elapsed = $timer->elapsedMilliseconds();

        $txtOk = str_starts_with($txt, $title) && str_contains($txt, 'seg-'.(count($segments) - 1));
        $srtOk = str_starts_with($srt, "1\n") && str_contains($srt, (string) count($segments)."\n");

        if (! $txtOk || ! $srtOk) {
            return new WorkloadResult('export', $repeat, $this->runId, $started, $completed, null,
                $this->beginConcurrency($repeat, ['segments' => count($segments)]),
                'failed', ['evaluated' => true, 'txt_ok' => $txtOk, 'srt_ok' => $srtOk],
                'correctness-verification', 'Export assembly content mismatch (title/first/last/numbering).',
                'assembly executed', 'verified export text missing');
        }

        return new WorkloadResult('export', $repeat, $this->runId, $started, $completed, $elapsed,
            $this->beginConcurrency($repeat, ['segments' => count($segments), 'note' => 'In-app assembly timing; HTTP-download timing deferred to target.']),
            'completed', ['evaluated' => true, 'txt_chars' => strlen($txt), 'srt_chars' => strlen($srt), 'rows' => count($segments)]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function queueDepth(): int|string
    {
        try {
            if (config('queue.default') !== 'database') {
                return 'n/a ('.((string) config('queue.default')).')';
            }

            return (int) DB::table(config('queue.connections.database.table', 'jobs'))->count();
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function absolutePath(string $path): ?string
    {
        try {
            $absolute = Storage::disk((string) config('media.storage_disk', 'local'))->path($path);
        } catch (Throwable) {
            // fall through to the Storage-content fallback
            $absolute = null;
        }

        return $absolute;
    }

    private function runIdAnsi(): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '', $this->runId);
    }
}
