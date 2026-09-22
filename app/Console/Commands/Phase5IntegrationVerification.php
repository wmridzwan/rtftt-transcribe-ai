<?php

namespace App\Console\Commands;

use App\Actions\TranslationOrchestrator;
use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Http\Controllers\TranslationExportController;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\Translation;
use App\Models\User;
use App\Translation\TranslationQueueConfig;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * P5-008 Phase 5 integration verification harness (canonical real gate).
 *
 * Hidden and disabled unless RTFTT_P5008_RUN=1. Not production behavior: it
 * provisions a real completed source transcript, requests translation through
 * the real orchestrator onto the configured (Redis) queue, and asserts the
 * final authoritative state after a real queue worker consumes it through the
 * authenticated Python worker and the canonical NLLB model.
 *
 * Modes (run in order; see verification/p5-008-real-gate.mjs):
 *   --mode=preflight  --out=<json>            runtime/versions/redis/queue/timeout
 *   --mode=seed       --state=<json>          create source transcript + users
 *   --mode=dispatch   --state=<json> --target=ms
 *   --mode=redis-payload --state=<json> --out=<json>
 *   --mode=assert     --state=<json> --out=<json>
 *   --mode=export     --state=<json> --out=<json>
 *
 * The command records no secrets.
 */
#[Signature('test:p5-008-integration {--mode=preflight} {--state=} {--out=} {--target=ms}')]
#[Description('P5-008 Phase 5 integration verification harness (disabled unless RTFTT_P5008_RUN=1).')]
class Phase5IntegrationVerification extends Command
{
    protected $hidden = true;

    private const CANONICAL_MODEL = 'facebook/nllb-200-distilled-600M';

    /** @var list<string> */
    private const TARGETS = ['ms', 'en', 'zh', 'ta'];

    public function handle(TranslationOrchestrator $orchestrator): int
    {
        if (getenv('RTFTT_P5008_RUN') !== '1') {
            $this->error('P5-008 integration harness is disabled. Set RTFTT_P5008_RUN=1.');

            return self::FAILURE;
        }

        return match ((string) $this->option('mode')) {
            'preflight' => $this->preflight(),
            'seed' => $this->seed(),
            'dispatch' => $this->dispatch($orchestrator),
            'redis-payload' => $this->redisPayload(),
            'assert' => $this->assertResult(),
            'export' => $this->export(),
            default => $this->failWith('Unknown mode.'),
        };
    }

    private function preflight(): int
    {
        $out = (string) $this->option('out');
        $workerUrl = rtrim((string) config('translation.worker_url'), '/');
        $token = (string) config('translation.worker_token');

        $pins = $this->canonicalPins();

        $evidence = [
            'canonical_pins' => $pins,
            'canonical_model' => self::CANONICAL_MODEL,
            'worker_url' => $workerUrl,
            'worker_token_present' => $token !== '',
            'queue_connection' => config('translation.queue_connection'),
            'queue_name' => config('translation.queue'),
            'queue_default' => config('queue.default'),
            'effective_connection' => TranslationQueueConfig::effectiveConnection(),
            'provider_timeout' => TranslationQueueConfig::providerTimeoutSeconds(),
            'job_timeout' => TranslationQueueConfig::jobTimeoutSeconds(),
            'required_retry_after' => TranslationQueueConfig::requiredRetryAfterSeconds(),
            'effective_retry_after' => TranslationQueueConfig::connectionRetryAfterSeconds(),
            'queue_consistency_violation' => TranslationQueueConfig::consistencyViolation(),
            'checks' => [],
        ];

        $checks = &$evidence['checks'];

        $this->record($checks, 'worker_token_present', $token !== '', 'Translation worker token is configured.');

        // Worker health (no auth).
        try {
            $health = Http::timeout(10)->get($workerUrl.'/health');
            $this->record($checks, 'worker_health', $health->successful() && ($health->json('status') === 'ok'), 'HTTP '.$health->status());
        } catch (Throwable $exception) {
            $this->record($checks, 'worker_health', false, $exception::class);
        }

        // Worker runtime provenance (auth).
        try {
            $runtime = Http::withToken($token)->timeout(15)->get($workerUrl.'/runtime');
            $runtimeBody = $runtime->json();
            $deps = is_array($runtimeBody) && is_array($runtimeBody['dependencies'] ?? null)
                ? $runtimeBody['dependencies']
                : [];
            $evidence['worker_runtime'] = is_array($runtimeBody) ? $runtimeBody : null;

            $versionsMatch = true;

            foreach ($pins as $package => $version) {
                // Normalize PEP 440 local version identifiers (e.g. the CPU
                // wheel `2.14.0+cpu` satisfies the canonical pin `2.14.0`).
                $installed = preg_replace('/\+.*$/', '', (string) ($deps[$package] ?? ''));

                if ($installed !== $version) {
                    $versionsMatch = false;
                }
            }

            $modelMatch = is_array($runtimeBody) && ($runtimeBody['model'] ?? null) === self::CANONICAL_MODEL;

            $this->record($checks, 'worker_runtime_versions', $runtime->successful() && $versionsMatch, 'pins match installed runtime');
            $this->record($checks, 'worker_canonical_model', $modelMatch, (string) ($runtimeBody['model'] ?? 'unknown'));
        } catch (Throwable $exception) {
            $evidence['worker_runtime'] = null;
            $this->record($checks, 'worker_runtime_versions', false, $exception::class);
            $this->record($checks, 'worker_canonical_model', false, $exception::class);
        }

        // Unauthenticated translation is rejected.
        try {
            $unauth = Http::timeout(15)->post($workerUrl.'/translate', $this->probeRequest('ms'));
            $this->record($checks, 'worker_auth_enforced', $unauth->status() === 401, 'HTTP '.$unauth->status());
        } catch (Throwable $exception) {
            $this->record($checks, 'worker_auth_enforced', false, $exception::class);
        }

        // Authenticated real inference returns non-empty text.
        try {
            $probe = Http::withToken($token)->timeout(300)->post($workerUrl.'/translate', $this->probeRequest('ms'));
            $probeBody = $probe->json();
            $probeText = is_array($probeBody) ? (string) ($probeBody['text'] ?? '') : '';
            $evidence['probe_translation'] = [
                'status' => $probe->status(),
                'model' => is_array($probeBody) ? ($probeBody['model'] ?? null) : null,
                'provider' => is_array($probeBody) ? ($probeBody['provider'] ?? null) : null,
                'text' => $probeText,
            ];

            $this->record(
                $checks,
                'worker_real_inference_non_empty',
                $probe->successful() && trim($probeText) !== '',
                'text length '.strlen($probeText),
            );
        } catch (Throwable $exception) {
            $evidence['probe_translation'] = null;
            $this->record($checks, 'worker_real_inference_non_empty', false, $exception::class);
        }

        // Live Redis.
        try {
            $pong = Redis::connection()->ping();
            $this->record($checks, 'redis_reachable', $pong !== false, is_string($pong) ? $pong : 'ping');
        } catch (Throwable $exception) {
            $this->record($checks, 'redis_reachable', false, $exception::class);
        }

        // Timeout invariant.
        $invariant = TranslationQueueConfig::providerTimeoutSeconds() < TranslationQueueConfig::jobTimeoutSeconds()
            && TranslationQueueConfig::jobTimeoutSeconds() < (TranslationQueueConfig::connectionRetryAfterSeconds() ?? 0);
        $this->record($checks, 'timeout_invariant', $invariant, 'provider < job < retry_after');
        $this->record($checks, 'queue_consistent', TranslationQueueConfig::consistencyViolation() === null, (string) (TranslationQueueConfig::consistencyViolation() ?? 'ok'));

        $evidence['ok'] = ! in_array(false, array_column($checks, 'ok'), true);

        return $this->emit($out, $evidence);
    }

    private function seed(): int
    {
        $statePath = (string) $this->option('state');

        $owner = User::factory()->create([
            'name' => 'P5-008 Owner',
            'email' => 'p5-008@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create([
            'name' => 'P5-008 Other',
            'email' => 'p5-008-other@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $media = MediaFile::factory()->create([
            'user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'p5-008-code-switch.wav',
            'media_type' => MediaType::Audio,
            'mime_type' => 'audio/wav',
            'extension' => 'wav',
            'duration_seconds' => 25,
            'status' => MediaStatus::Ready,
        ]);

        $transcription = Transcription::factory()->completed()->create([
            'user_id' => $owner->id,
            'media_file_id' => $media->id,
            'title' => 'P5-008 code-switch integration transcript',
            'language' => 'en',
            'detected_language' => 'und',
            'model' => 'large-v3',
            'speech_detected' => true,
            'full_text' => 'Selamat pagi semua orang. Good morning everyone. 大家好. காலை வணக்கம். Welcome to the meeting.',
        ]);

        // Code-switched source: ms, en, zh, ta, und.
        $segments = [
            ['text' => 'Selamat pagi semua orang', 'language' => 'ms'],
            ['text' => 'Good morning everyone', 'language' => 'en'],
            ['text' => '大家好', 'language' => 'zh'],
            ['text' => 'காலை வணக்கம்', 'language' => 'ta'],
            ['text' => 'Welcome to the meeting', 'language' => 'und'],
        ];

        $baseline = [];

        foreach ($segments as $index => $segment) {
            $transcription->segments()->create([
                'segment_index' => $index,
                'start_seconds' => $index * 5,
                'end_seconds' => ($index + 1) * 5,
                'language' => $segment['language'],
                'text' => $segment['text'],
            ]);

            $baseline[] = [
                'segment_index' => $index,
                'start_seconds' => (float) ($index * 5),
                'end_seconds' => (float) (($index + 1) * 5),
                'language' => $segment['language'],
                'text' => $segment['text'],
            ];
        }

        $state = [
            'owner_id' => $owner->id,
            'owner_email' => $owner->email,
            'other_id' => $other->id,
            'other_email' => $other->email,
            'transcription_id' => $transcription->getKey(),
            'media_id' => $media->id,
            'source_baseline' => $baseline,
            'targets' => self::TARGETS,
            'translations' => [],
        ];

        $this->writeState($statePath, $state);
        $this->info('Seeded P5-008 source transcription '.$transcription->getKey().'.');

        return self::SUCCESS;
    }

    private function dispatch(TranslationOrchestrator $orchestrator): int
    {
        $statePath = (string) $this->option('state');
        $target = TranslationTarget::fromBcp47((string) $this->option('target'));

        if ($target === null) {
            return $this->failWith('Unsupported or missing --target.');
        }

        /** @var array<string, mixed> $state */
        $state = $this->readState($statePath);
        $transcription = Transcription::query()->findOrFail((int) $state['transcription_id']);

        $translation = $orchestrator->request($transcription, $target);

        /** @var array<string, int> $translations */
        $translations = is_array($state['translations'] ?? null) ? $state['translations'] : [];
        $translations[$target->value] = $translation->getKey();
        $state['translations'] = $translations;

        $this->writeState($statePath, $state);
        $this->info('Dispatched '.$target->value.' translation '.$translation->getKey().'.');

        return self::SUCCESS;
    }

    private function redisPayload(): int
    {
        $out = (string) $this->option('out');

        /** @var array<string, mixed> $state */
        $state = $this->readState((string) $this->option('state'));
        /** @var array<string, int> $translations */
        $translations = is_array($state['translations'] ?? null) ? $state['translations'] : [];

        $queue = (string) config('translation.queue', 'translation');
        $queueKey = 'queues:'.$queue;

        $items = Redis::connection()->lrange($queueKey, 0, -1);
        $items = is_array($items) ? $items : [];

        $payload = (string) ($items[0] ?? '');

        $evidence = [
            'queue_connection' => config('translation.queue_connection'),
            'queue_key' => $queueKey,
            'list_length' => count($items),
            'payload_bytes' => strlen($payload),
            'contains_media_path' => str_contains($payload, 'media/'),
            'contains_binary' => str_contains($payload, 'audio-bytes'),
            'contains_translation_id' => false,
        ];

        foreach ($translations as $id) {
            if ($id > 0 && str_contains($payload, (string) $id)) {
                $evidence['contains_translation_id'] = true;
            }
        }

        $evidence['ok'] = $evidence['list_length'] >= 1
            && $evidence['contains_media_path'] === false
            && $evidence['contains_binary'] === false;

        return $this->emit($out, $evidence);
    }

    private function assertResult(): int
    {
        $out = (string) $this->option('out');

        /** @var array<string, mixed> $state */
        $state = $this->readState((string) $this->option('state'));
        /** @var array<string, int> $translations */
        $translations = is_array($state['translations'] ?? null) ? $state['translations'] : [];

        $transcription = Transcription::query()->with('segments')->findOrFail((int) $state['transcription_id']);
        $other = User::query()->findOrFail((int) $state['other_id']);

        $sourceSegments = $transcription->segments->map(static fn ($segment): array => [
            'segment_index' => (int) $segment->segment_index,
            'start_seconds' => (float) $segment->start_seconds,
            'end_seconds' => (float) $segment->end_seconds,
            'language' => $segment->language->value,
            'text' => (string) $segment->text,
        ])->all();

        /** @var list<array<string, mixed>> $baseline */
        $baseline = is_array($state['source_baseline'] ?? null) ? $state['source_baseline'] : [];

        // Normalize the persisted baseline (JSON round-trip turns 0.0 into int
        // 0) so the immutability comparison is type-stable.
        $normalizedBaseline = array_map(static fn (array $segment): array => [
            'segment_index' => (int) $segment['segment_index'],
            'start_seconds' => (float) $segment['start_seconds'],
            'end_seconds' => (float) $segment['end_seconds'],
            'language' => (string) $segment['language'],
            'text' => (string) $segment['text'],
        ], $baseline);

        $evidence = [
            'transcription_status' => $transcription->status->value,
            'source_unchanged' => $sourceSegments === $normalizedBaseline,
            'ownership_other_can_view' => Gate::forUser($other)->allows('view', $transcription),
            'results' => [],
        ];

        foreach ($translations as $target => $translationId) {
            $translation = Translation::query()->with('segments')->findOrFail($translationId);

            $aligned = true;
            $translatedSegments = $translation->segments->map(static fn ($segment): array => [
                'segment_index' => (int) $segment->segment_index,
                'start_seconds' => (float) $segment->start_seconds,
                'end_seconds' => (float) $segment->end_seconds,
                'source_language' => $segment->source_language->value,
                'text' => (string) $segment->text,
            ])->all();

            if (count($translatedSegments) !== count($sourceSegments)) {
                $aligned = false;
            } else {
                foreach ($sourceSegments as $index => $source) {
                    $translated = $translatedSegments[$index] ?? null;

                    if ($translated === null
                        || $translated['segment_index'] !== $source['segment_index']
                        || abs($translated['start_seconds'] - $source['start_seconds']) > 0.0005
                        || abs($translated['end_seconds'] - $source['end_seconds']) > 0.0005
                        || $translated['source_language'] !== $source['language']) {
                        $aligned = false;
                    }
                }
            }

            $nonEmpty = trim((string) $translation->full_text) !== ''
                && collect($translatedSegments)->contains(static fn (array $segment): bool => trim($segment['text']) !== '');

            $evidence['results'][$target] = [
                'translation_id' => $translation->getKey(),
                'status' => $translation->status->value,
                'provider' => $translation->provider,
                'model' => $translation->model,
                'aligned' => $aligned,
                'non_empty' => $nonEmpty,
                'segment_indices' => array_column($translatedSegments, 'segment_index'),
                'source_languages' => array_values(array_unique(array_column($translatedSegments, 'source_language'))),
                'full_text' => $translation->full_text,
                'segments' => $translatedSegments,
            ];
        }

        $allCompleted = ! in_array(true, array_map(
            static fn (array $result): bool => $result['status'] !== TranslationStatus::Completed->value,
            array_values($evidence['results']),
        ), true);

        $allAligned = ! in_array(true, array_map(
            static fn (array $result): bool => $result['aligned'] !== true || $result['non_empty'] !== true,
            array_values($evidence['results']),
        ), true);

        $evidence['all_completed'] = $allCompleted && $evidence['results'] !== [];
        $evidence['all_aligned_non_empty'] = $allAligned && $evidence['results'] !== [];
        $evidence['source_immutable'] = $evidence['source_unchanged'] === true;
        $evidence['ownership_isolated'] = $evidence['ownership_other_can_view'] === false;
        $evidence['ok'] = $evidence['all_completed']
            && $evidence['all_aligned_non_empty']
            && $evidence['source_immutable']
            && $evidence['ownership_isolated'];

        return $this->emit($out, $evidence);
    }

    private function export(): int
    {
        $out = (string) $this->option('out');

        /** @var array<string, mixed> $state */
        $state = $this->readState((string) $this->option('state'));
        /** @var array<string, int> $translations */
        $translations = is_array($state['translations'] ?? null) ? $state['translations'] : [];

        $controller = app(TranslationExportController::class);
        $ownerId = (int) $state['owner_id'];
        $other = User::query()->findOrFail((int) $state['other_id']);

        Auth::loginUsingId($ownerId);

        $evidence = ['formats' => [], 'ownership_isolated' => null];

        try {
            foreach ($translations as $target => $translationId) {
                $translation = Translation::query()->with(['segments', 'transcription'])->findOrFail($translationId);

                $responses = [
                    'txt' => $controller->exportTxt($translation),
                    'srt' => $controller->exportSrt($translation),
                    'vtt' => $controller->exportVtt($translation),
                    'docx' => $controller->exportDocx($translation),
                ];

                foreach ($responses as $format => $response) {
                    $content = (string) $response->getContent();
                    $disposition = (string) $response->headers->get('Content-Disposition');

                    $evidence['formats'][$format] = [
                        'target' => $target,
                        'status' => $response->getStatusCode(),
                        'content_type' => (string) $response->headers->get('Content-Type'),
                        'filename_has_target_suffix' => str_contains($disposition, '-'.$target.'.'.$format),
                        'bytes' => strlen($content),
                    ];
                }

                // SRT/VTT must inherit authoritative source timestamps.
                $sourceStarts = $translation->segments->sortBy('segment_index')->values()->map(
                    static fn ($segment): string => number_format((float) $segment->start_seconds, 3, ',', ''),
                )->all();
                $evidence['first_source_timestamp'] = $sourceStarts[0] ?? null;

                // Non-owner export must be denied.
                Auth::logout();
                Auth::loginUsingId($other->getKey());
                try {
                    $controller->exportTxt($translation);
                    $evidence['ownership_isolated'] = false;
                } catch (Throwable) {
                    $evidence['ownership_isolated'] = true;
                }
                Auth::logout();
                Auth::loginUsingId($ownerId);
            }
        } finally {
            Auth::logout();
        }

        $evidence['all_exports_ok'] = $evidence['formats'] !== []
            && ! in_array(true, array_map(
                static fn (array $format): bool => $format['status'] !== 200 || $format['filename_has_target_suffix'] !== true || $format['bytes'] <= 0,
                array_values($evidence['formats']),
            ), true);

        $evidence['ok'] = $evidence['all_exports_ok'] && $evidence['ownership_isolated'] !== false;

        return $this->emit($out, $evidence);
    }

    /**
     * @return array<string, mixed>
     */
    private function probeRequest(string $target): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'transcription_id' => 0,
            'target_language' => $target,
            'segments' => [[
                'segment_index' => 0,
                'start_seconds' => 0.0,
                'end_seconds' => 5.0,
                'text' => 'Good morning, welcome to the meeting.',
                'source_language' => 'en',
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function canonicalPins(): array
    {
        $pins = [];
        $contents = File::get(base_path('worker/requirements.txt'));

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '==')) {
                continue;
            }

            [$name, $version] = explode('==', $line, 2);
            $pins[trim($name)] = trim($version);
        }

        return $pins;
    }

    /**
     * @param  array<int, array{name: string, ok: bool, detail: string}>  $checks
     */
    private function record(array &$checks, string $name, bool $ok, string $detail): void
    {
        $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function emit(string $out, array $evidence): int
    {
        $encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($out !== '') {
            file_put_contents($out, $encoded);
        }

        $this->info($encoded === false ? '{}' : $encoded);

        $ok = ! array_key_exists('ok', $evidence) || $evidence['ok'] !== false;

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(string $path, array $state): void
    {
        if ($path === '') {
            return;
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid state file: '.$path);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
