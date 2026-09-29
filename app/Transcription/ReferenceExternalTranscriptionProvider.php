<?php

namespace App\Transcription;

use Illuminate\Support\Facades\Log;

/**
 * Fixture-shaped reference external transcription adapter (PP-T3).
 *
 * Vendor-neutral proof of the external transcription boundary behind the
 * frozen T1 TranscriptionProvider contract. Deterministic, in-process, zero
 * network egress: chunk payloads are dispatched through the injected
 * ReferenceExternalTransport (fixtures/mocks in Wave 1).
 *
 * Egress disclosure (what WOULD leave on a real transport): derived audio
 * chunk payloads with chunk-relative timing, per-chunk metadata, and the
 * pinned model identity — never the original media object, never ownership
 * data, never full transcript text at request time.
 *
 * Chunk semantics (reconciled DECISION-PP-T3-CONTRACT-RECONCILIATION-001):
 * fan-out into fixed windows → absolutize
 * (absolute_ms = chunk_offset_ms + relative_ms, re-validated) → deterministic
 * ordering → keep-earlier/drop-later overlap trim → exact-then-near-duplicate
 * dedup (1 ms) → chunk-manifest coverage count check → one
 * NormalizedTranscript. Per-chunk audit is logs-only (ADR-027); no durable
 * chunk state, no migration. No retries, no fallback inside the adapter:
 * the first mapped failure ends the attempt.
 */
final class ReferenceExternalTranscriptionProvider implements TranscriptionProvider
{
    /**
     * Canonical 500 MiB product-limit relationship: the adapter ceiling
     * defaults to exactly the product limit and may only be lowered.
     */
    public const PRODUCT_LIMIT_BYTES = 524288000;

    /**
     * Fixed adapter-internal chunk window. No new config key (H-2).
     */
    public const CHUNK_WINDOW_MS = 30000;

    private readonly int $effectiveTimeoutSeconds;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $providerKey,
        private readonly string $modelPinned,
        private readonly ReferenceExternalTransport $transport,
        ?int $timeoutSeconds = null,
        private readonly int $maxRequestBytes = self::PRODUCT_LIMIT_BYTES,
    ) {
        if ($this->token === '') {
            throw new TranscriptionException(
                TranscriptionFailure::ConfigurationError,
                'Reference external provider requires an explicitly injected token; refusing to dispatch without credentials.',
            );
        }

        $ceiling = (int) config('transcription.timeout_seconds', 300);
        $effective = $timeoutSeconds ?? $ceiling;

        if ($effective > $ceiling) {
            throw new TranscriptionException(
                TranscriptionFailure::ConfigurationError,
                'Reference external provider timeout exceeds the configured provider ceiling; refusing to silently extend it.',
            );
        }

        if ($this->maxRequestBytes <= 0) {
            throw new TranscriptionException(
                TranscriptionFailure::ConfigurationError,
                'Reference external provider ceiling must be positive.',
            );
        }

        $this->effectiveTimeoutSeconds = $effective;
    }

    public function transcribe(
        TranscriptionInvocation $invocation,
    ): NormalizedTranscript {
        $media = $invocation->media;

        if ($media->fileSizeBytes > $this->maxRequestBytes) {
            Log::warning('Reference external transcription request exceeds provider ceiling.', [
                'request_id' => $invocation->requestId,
                'domain' => 'transcription',
                'provider_key' => $this->providerKey,
                'model_pinned' => $this->modelPinned,
                'processing_attempt_id' => $invocation->processingAttemptId,
                'file_size_bytes' => $media->fileSizeBytes,
                'max_request_bytes' => $this->maxRequestBytes,
                'failure_category' => TranscriptionFailure::MediaRejected->value,
            ]);

            throw new TranscriptionException(
                TranscriptionFailure::MediaRejected,
                'Media exceeds the reference external provider request ceiling.',
            );
        }

        $plan = $this->planChunks($media);
        $attemptSeq = 0;
        $accounted = 0;

        /** @var list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}> $absolute */
        $absolute = [];
        $anySpeech = false;

        foreach ($plan as $chunkIndex => $window) {
            $chunkId = $this->chunkId($invocation->requestId, $chunkIndex, $attemptSeq);

            $request = new ReferenceExternalChunkRequest(
                providerKey: $this->providerKey,
                modelPinned: $this->modelPinned,
                baseUrl: $this->baseUrl,
                authorization: 'Bearer '.$this->token,
                requestId: $invocation->requestId,
                transcriptionId: $invocation->transcriptionId,
                processingAttemptId: $invocation->processingAttemptId,
                attemptSeq: $attemptSeq,
                chunkIndex: $chunkIndex,
                chunkId: $chunkId,
                offsetMs: $window['offsetMs'],
                windowMs: $window['windowMs'],
                mediaStorageKey: $media->storageKey,
                mimeType: $media->mimeType,
                requestedLanguage: $invocation->options->requestedLanguage?->value,
                timeoutSeconds: $this->effectiveTimeoutSeconds,
            );

            $startedAt = microtime(true);

            try {
                $response = $this->transport->dispatchChunk($request);
            } catch (TranscriptionException $e) {
                throw $e;
            } catch (\Throwable $t) {
                $this->logChunk($invocation, $chunkIndex, $chunkId, $attemptSeq, 'failed', $startedAt, TranscriptionFailure::InvalidWorkerResponse, 0, count($plan));

                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    'Reference external chunk unaccounted for; failing closed with no partial result.',
                    $t,
                );
            }

            if (! $response->ok) {
                $failure = $this->mapFailure($response);
                $this->logChunk($invocation, $chunkIndex, $chunkId, $attemptSeq, 'failed', $startedAt, $failure, 0, count($plan));

                throw new TranscriptionException(
                    $failure,
                    $response->failureMessage !== '' ? $response->failureMessage : 'Reference external chunk failed.',
                );
            }

            $chunkSegments = $this->absolutizeChunk($response, $window['offsetMs'], $chunkId);

            if ($response->speechDetected) {
                $anySpeech = true;
            }

            foreach ($chunkSegments as $segment) {
                $absolute[] = $segment;
            }

            $accounted++;

            $this->logChunk($invocation, $chunkIndex, $chunkId, $attemptSeq, 'success', $startedAt, null, count($chunkSegments), count($plan));
        }

        if ($accounted !== count($plan)) {
            throw new TranscriptionException(
                TranscriptionFailure::InvalidWorkerResponse,
                'Reference external chunk manifest coverage check failed; failing closed with no partial result.',
            );
        }

        $ordered = $this->deduplicate($absolute);
        $reconciled = $this->resolveOverlaps($ordered);
        $final = $this->deduplicate($reconciled);

        if ($anySpeech && $final === []) {
            throw new TranscriptionException(
                TranscriptionFailure::InvalidWorkerResponse,
                'Reference external provider reported speech but produced no segments.',
            );
        }

        $durationSeconds = $media->durationSeconds;
        $lastEndSeconds = $final === [] ? 0.0 : $final[count($final) - 1]['endMs'] / 1000.0;
        $duration = max($durationSeconds ?? 0.0, $lastEndSeconds);

        if (! $anySpeech) {
            $this->logCompletion($invocation, $attemptSeq, 'no_speech', 0, count($plan));

            return NormalizedTranscript::noSpeech($duration);
        }

        $texts = [];
        $languageVotes = [];
        $segments = [];

        foreach ($final as $index => $segment) {
            $texts[] = $segment['text'];
            $key = $segment['language']->value;
            $languageVotes[$key] = ($languageVotes[$key] ?? 0) + 1;
            $segments[] = new TranscriptSegmentData(
                segmentIndex: $index,
                startSeconds: $segment['startMs'] / 1000.0,
                endSeconds: $segment['endMs'] / 1000.0,
                text: $segment['text'],
                language: $segment['language'],
            );
        }

        $dominant = LanguageIdentifier::Undetermined;
        $best = -1;
        foreach ($languageVotes as $value => $votes) {
            if ($votes > $best) {
                $best = $votes;
                $dominant = LanguageIdentifier::fromBcp47($value);
            }
        }

        $this->logCompletion($invocation, $attemptSeq, 'success', count($segments), count($plan));

        return new NormalizedTranscript(
            text: implode(' ', $texts),
            detectedLanguage: $dominant,
            durationSeconds: $duration,
            speechDetected: true,
            segments: $segments,
        );
    }

    /**
     * Derive the deterministic chunk manifest from media duration.
     *
     * @return list<array{offsetMs: int, windowMs: ?int}>
     */
    private function planChunks(TranscriptionMedia $media): array
    {
        if ($media->durationSeconds === null || $media->durationSeconds <= 0) {
            return [['offsetMs' => 0, 'windowMs' => null]];
        }

        $totalMs = (int) round($media->durationSeconds * 1000);
        $plan = [];
        $offset = 0;

        while ($offset < $totalMs) {
            $remaining = $totalMs - $offset;
            $window = min(self::CHUNK_WINDOW_MS, $remaining);
            $plan[] = ['offsetMs' => $offset, 'windowMs' => $window];
            $offset += $window;
        }

        return $plan;
    }

    private function chunkId(string $requestId, int $chunkIndex, int $attemptSeq): string
    {
        $short = substr(str_replace('-', '', $requestId), 0, 8);

        return sprintf('chk_%s_%04d_%02d', $short !== '' ? $short : 'unknown', $chunkIndex, $attemptSeq);
    }

    /**
     * Validate a chunk response and absolutize its relative timestamps.
     *
     * @return list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}>
     *
     * @throws TranscriptionException
     */
    private function absolutizeChunk(
        ReferenceExternalChunkResponse $response,
        int $offsetMs,
        string $chunkId,
    ): array {
        if (! $response->speechDetected && ($response->segments !== [])) {
            throw new TranscriptionException(
                TranscriptionFailure::InvalidWorkerResponse,
                "Reference external chunk [{$chunkId}]: speech not detected but segments are non-empty.",
            );
        }

        $segments = [];

        foreach ($response->segments as $i => $raw) {
            if (! is_array($raw)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} is not an array.",
                );
            }

            $relativeStart = $raw['relative_start_ms'] ?? null;
            $relativeEnd = $raw['relative_end_ms'] ?? null;
            $text = $raw['text'] ?? null;
            $language = $raw['language'] ?? 'und';

            if (! is_int($relativeStart) && ! is_float($relativeStart)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} has a non-numeric relative start.",
                );
            }

            if (! is_int($relativeEnd) && ! is_float($relativeEnd)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} has a non-numeric relative end.",
                );
            }

            if (! is_string($text)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} has non-string text.",
                );
            }

            if (! is_string($language)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} has a non-string language tag.",
                );
            }

            $startFloat = (float) $relativeStart;
            $endFloat = (float) $relativeEnd;

            if (! is_finite($startFloat) || ! is_finite($endFloat)) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} has a non-finite timestamp.",
                );
            }

            $startMs = $offsetMs + (int) round($startFloat);
            $endMs = $offsetMs + (int) round($endFloat);

            if ($startMs < 0 || $endMs < $startMs) {
                throw new TranscriptionException(
                    TranscriptionFailure::InvalidWorkerResponse,
                    "Reference external chunk [{$chunkId}]: segment {$i} violates absolute timestamp invariants.",
                );
            }

            $trimmed = trim($text);

            if ($trimmed === '') {
                continue;
            }

            $segments[] = [
                'startMs' => $startMs,
                'endMs' => $endMs,
                'text' => $trimmed,
                'language' => LanguageIdentifier::fromBcp47($language),
            ];
        }

        return $segments;
    }

    /**
     * Deterministic ordering + exact-then-near-duplicate dedup (1 ms).
     *
     * @param  list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}>  $segments
     * @return list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}>
     */
    private function deduplicate(array $segments): array
    {
        usort($segments, static fn (array $a, array $b): int => $a['startMs'] <=> $b['startMs'] ?: $a['endMs'] <=> $b['endMs'] ?: strcmp($a['text'], $b['text']));

        $kept = [];

        foreach ($segments as $candidate) {
            $duplicate = false;

            foreach ($kept as $existing) {
                if ($candidate['text'] !== $existing['text']) {
                    continue;
                }

                if (abs($candidate['startMs'] - $existing['startMs']) <= 1
                    && abs($candidate['endMs'] - $existing['endMs']) <= 1) {
                    $duplicate = true;
                    break;
                }
            }

            if (! $duplicate) {
                $kept[] = $candidate;
            }
        }

        return $kept;
    }

    /**
     * Keep-earlier/drop-later overlap trim on absolutely-ordered segments.
     *
     * @param  list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}>  $segments
     * @return list<array{startMs: int, endMs: int, text: string, language: LanguageIdentifier}>
     */
    private function resolveOverlaps(array $segments): array
    {
        $kept = [];

        foreach ($segments as $candidate) {
            if ($kept === []) {
                $kept[] = $candidate;

                continue;
            }

            $lastIndex = count($kept) - 1;

            if ($candidate['startMs'] < $kept[$lastIndex]['endMs']) {
                $candidate['startMs'] = $kept[$lastIndex]['endMs'];

                if ($candidate['startMs'] >= $candidate['endMs']) {
                    continue;
                }
            }

            $kept[] = $candidate;
        }

        return $kept;
    }

    private function mapFailure(ReferenceExternalChunkResponse $response): TranscriptionFailure
    {
        return match ($response->failureKind) {
            ReferenceExternalChunkFailureKind::Timeout => TranscriptionFailure::WorkerTimeout,
            ReferenceExternalChunkFailureKind::RateLimited => TranscriptionFailure::WorkerSaturated,
            ReferenceExternalChunkFailureKind::ServerError,
            ReferenceExternalChunkFailureKind::Unreachable => TranscriptionFailure::WorkerUnavailable,
            ReferenceExternalChunkFailureKind::Partial,
            ReferenceExternalChunkFailureKind::Invalid,
            ReferenceExternalChunkFailureKind::Unknown,
            null => TranscriptionFailure::InvalidWorkerResponse,
        };
    }

    private function logChunk(
        TranscriptionInvocation $invocation,
        int $chunkIndex,
        string $chunkId,
        int $attemptSeq,
        string $outcome,
        float $startedAt,
        ?TranscriptionFailure $failure,
        int $segmentCount,
        int $plannedChunks,
    ): void {
        $context = [
            'request_id' => $invocation->requestId,
            'domain' => 'transcription',
            'provider_key' => $this->providerKey,
            'model_pinned' => $this->modelPinned,
            'transcription_id' => $invocation->transcriptionId,
            'processing_attempt_id' => $invocation->processingAttemptId,
            'attempt_seq' => $attemptSeq,
            'chunk_id' => $chunkId,
            'chunk_index' => $chunkIndex,
            'planned_chunks' => $plannedChunks,
            'segment_count' => $segmentCount,
            'outcome' => $outcome,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'failure_category' => $failure?->value,
        ];

        if ($failure === null) {
            Log::info('Reference external chunk transcribed.', $context);
        } else {
            Log::warning('Reference external chunk failed.', $context);
        }
    }

    private function logCompletion(
        TranscriptionInvocation $invocation,
        int $attemptSeq,
        string $outcome,
        int $segmentCount,
        int $chunkCount,
    ): void {
        Log::info('Reference external transcription completed.', [
            'request_id' => $invocation->requestId,
            'domain' => 'transcription',
            'provider_key' => $this->providerKey,
            'model_pinned' => $this->modelPinned,
            'transcription_id' => $invocation->transcriptionId,
            'processing_attempt_id' => $invocation->processingAttemptId,
            'attempt_seq' => $attemptSeq,
            'outcome' => $outcome,
            'chunk_count' => $chunkCount,
            'segment_count' => $segmentCount,
            'failure_category' => null,
        ]);
    }
}
