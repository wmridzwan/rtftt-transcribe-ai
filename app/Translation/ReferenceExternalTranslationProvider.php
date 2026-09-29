<?php

namespace App\Translation;

use Illuminate\Support\Facades\Log;

/**
 * Fixture-shaped reference external translation adapter (PP-T4).
 *
 * Vendor-neutral proof of the external translation boundary behind the
 * frozen T1 TranslationProvider contract. Deterministic, in-process, zero
 * network egress: invocations are dispatched once through the injected
 * ReferenceExternalTranslationTransport (fixtures/mocks in Wave 1).
 *
 * Egress disclosure (what WOULD leave on a real transport):
 * segment-aligned source text with segment indices, timestamps, and language
 * tags, per-request metadata, and the pinned model identity — never media,
 * never ownership data, never full translated text at request time.
 *
 * Single-shot 1:1 semantics (reconciled
 * DECISION-PP-T4-CONTRACT-RECONCILIATION-001): one invocation → one shaped
 * response → frozen validator → one TranslationResult → single atomic persist
 * path. Per-request audit is logs-only (ADR-027); no durable state, no
 * migration. No retries, no fallback inside the adapter: the first mapped
 * failure ends the attempt.
 */
final class ReferenceExternalTranslationProvider implements TranslationProvider
{
    /**
     * Adapter-internal ceiling defaults. Generous on purpose: they bound the
     * reference shape, never the product (translation has no product byte
     * limit). Tests inject small values to prove enforcement.
     */
    public const DEFAULT_MAX_SEGMENTS = 1000;

    public const DEFAULT_MAX_PAYLOAD_CHARS = 500000;

    /**
     * Source-language tags the reference boundary accepts. Tags outside this
     * vocabulary are rejected with UnsupportedSource before reaching the
     * frozen validator.
     */
    private const KNOWN_SOURCE_TAGS = ['ms', 'en', 'zh', 'ta', 'und'];

    private readonly int $effectiveTimeoutSeconds;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $providerKey,
        private readonly string $modelPinned,
        private readonly ReferenceExternalTranslationTransport $transport,
        ?int $timeoutSeconds = null,
        private readonly int $maxSegments = self::DEFAULT_MAX_SEGMENTS,
        private readonly int $maxPayloadChars = self::DEFAULT_MAX_PAYLOAD_CHARS,
    ) {
        if ($this->token === '') {
            throw new TranslationException(
                TranslationFailure::ConfigurationError,
                'Reference external provider requires an explicitly injected token; refusing to dispatch without credentials.',
            );
        }

        $ceiling = (int) config('translation.timeout_seconds', 300);
        $effective = $timeoutSeconds ?? $ceiling;

        if ($effective > $ceiling) {
            throw new TranslationException(
                TranslationFailure::ConfigurationError,
                'Reference external provider timeout exceeds the configured provider ceiling; refusing to silently extend it.',
            );
        }

        if ($this->maxSegments <= 0 || $this->maxPayloadChars <= 0) {
            throw new TranslationException(
                TranslationFailure::ConfigurationError,
                'Reference external provider ceilings must be positive.',
            );
        }

        $this->effectiveTimeoutSeconds = $effective;
    }

    public function translate(
        TranslationInvocation $invocation,
    ): TranslationResult {
        $payloadSegments = array_map(
            static fn (TranslationSegmentData $segment): array => [
                'segment_index' => $segment->segmentIndex,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => $segment->text,
                'source_language' => $segment->sourceLanguage->value,
            ],
            $invocation->segments,
        );

        $payloadChars = array_sum(array_map(
            static fn (array $shape): int => strlen($shape['text']),
            $payloadSegments,
        ));

        if (count($payloadSegments) > $this->maxSegments || $payloadChars > $this->maxPayloadChars) {
            Log::warning('Reference external translation request exceeds provider ceiling.', [
                'request_id' => $invocation->requestId,
                'domain' => 'translation',
                'provider_key' => $this->providerKey,
                'model_pinned' => $this->modelPinned,
                'transcription_id' => $invocation->transcriptionId,
                'translation_id' => $invocation->translationId,
                'target_language' => $invocation->targetLanguage->value,
                'segment_count' => count($payloadSegments),
                'payload_chars' => $payloadChars,
                'max_segments' => $this->maxSegments,
                'max_payload_chars' => $this->maxPayloadChars,
                'failure_category' => TranslationFailure::InvalidRequest->value,
            ]);

            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'Translation exceeds the reference external provider request ceiling.',
            );
        }

        $request = new ReferenceExternalTranslationRequest(
            providerKey: $this->providerKey,
            modelPinned: $this->modelPinned,
            baseUrl: $this->baseUrl,
            authorization: 'Bearer '.$this->token,
            requestId: $invocation->requestId,
            transcriptionId: $invocation->transcriptionId,
            translationId: $invocation->translationId,
            targetLanguage: $invocation->targetLanguage->value,
            segments: $payloadSegments,
            timeoutSeconds: $this->effectiveTimeoutSeconds,
        );

        $startedAt = microtime(true);

        try {
            $response = $this->transport->dispatch($request);
        } catch (TranslationException $e) {
            $this->logOutcome($invocation, 'failed', $startedAt, $e->failure, count($payloadSegments));

            throw $e;
        } catch (\Throwable $t) {
            $this->logOutcome($invocation, 'failed', $startedAt, TranslationFailure::ProviderFailed, count($payloadSegments));

            throw new TranslationException(
                TranslationFailure::ProviderFailed,
                'Reference external translation dispatch failed.',
                $t,
            );
        }

        if (! $response->ok) {
            $failure = $this->mapFailure($response);
            $this->logOutcome($invocation, 'failed', $startedAt, $failure, count($payloadSegments));

            throw new TranslationException(
                $failure,
                $response->failureMessage !== '' ? $response->failureMessage : 'Reference external translation failed.',
            );
        }

        try {
            $result = $this->validatedResult($response, $invocation);
        } catch (TranslationException $e) {
            $this->logOutcome($invocation, 'failed', $startedAt, $e->failure, count($payloadSegments));

            throw $e;
        }

        $this->logOutcome($invocation, 'success', $startedAt, null, count($result->segments));

        return $result;
    }

    /**
     * Adapter-level shape guard plus the frozen validator.
     *
     * The adapter rejects non-object segments and out-of-vocabulary source
     * tags with the contracted codes; everything else is enforced by the
     * frozen TranslationResponseValidator (count/index/timestamps/echo/
     * target), which stays authoritative.
     *
     * @throws TranslationException
     */
    private function validatedResult(
        ReferenceExternalTranslationResponse $response,
        TranslationInvocation $invocation,
    ): TranslationResult {
        foreach ($response->segments as $i => $segment) {
            if (! is_array($segment)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Reference external translation segment {$i} is not an object.",
                );
            }

            $tag = $segment['source_language'] ?? null;

            if (! is_string($tag)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Reference external translation segment {$i} is missing the source-language echo.",
                );
            }

            if (! in_array(strtolower($tag), self::KNOWN_SOURCE_TAGS, true)) {
                throw new TranslationException(
                    TranslationFailure::UnsupportedSource,
                    "Reference external translation segment {$i} carries an unsupported source language.",
                );
            }
        }

        return TranslationResponseValidator::fromArray(
            [
                'target_language' => $response->targetLanguage,
                'text' => $response->text,
                'segments' => $response->segments,
            ],
            $invocation,
            $this->providerKey,
            $this->modelPinned,
        );
    }

    private function mapFailure(
        ReferenceExternalTranslationResponse $response,
    ): TranslationFailure {
        return match ($response->failureKind) {
            ReferenceExternalTranslationFailureKind::Timeout => TranslationFailure::ProviderTimeout,
            ReferenceExternalTranslationFailureKind::RateLimited,
            ReferenceExternalTranslationFailureKind::ServerError,
            ReferenceExternalTranslationFailureKind::Unreachable => TranslationFailure::ProviderUnavailable,
            ReferenceExternalTranslationFailureKind::Partial => TranslationFailure::MissingSegments,
            ReferenceExternalTranslationFailureKind::Invalid => TranslationFailure::MalformedOutput,
            ReferenceExternalTranslationFailureKind::Unknown,
            null => TranslationFailure::ProviderFailed,
        };
    }

    private function logOutcome(
        TranslationInvocation $invocation,
        string $outcome,
        float $startedAt,
        ?TranslationFailure $failure,
        int $segmentCount,
    ): void {
        $context = [
            'request_id' => $invocation->requestId,
            'domain' => 'translation',
            'provider_key' => $this->providerKey,
            'model_pinned' => $this->modelPinned,
            'transcription_id' => $invocation->transcriptionId,
            'translation_id' => $invocation->translationId,
            'target_language' => $invocation->targetLanguage->value,
            'segment_count' => $segmentCount,
            'outcome' => $outcome,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'failure_category' => $failure?->value,
        ];

        if ($failure === null) {
            Log::info('Reference external translation completed.', $context);
        } else {
            Log::warning('Reference external translation failed.', $context);
        }
    }
}
