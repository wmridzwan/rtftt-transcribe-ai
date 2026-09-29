<?php

namespace App\Transcription;

/**
 * Shaped chunk response for the reference external transcription boundary.
 *
 * Success carries chunk-relative millisecond timestamps plus text and BCP 47
 * language tags; no confidence scores, word timings, or vendor headers cross
 * this boundary (firewall rule). Failure carries a vendor-neutral kind plus
 * an advisory retryability hint that the adapter must ignore for mapping.
 */
final readonly class ReferenceExternalChunkResponse
{
    /**
     * @param  list<mixed>  $segments  chunk-relative segment shapes; validated by the adapter
     */
    private function __construct(
        public bool $ok,
        public bool $speechDetected,
        public array $segments,
        public ?int $durationMs,
        public ?ReferenceExternalChunkFailureKind $failureKind,
        public bool $retryableHint,
        public string $failureMessage,
    ) {}

    /**
     * @param  list<mixed>  $segments  chunk-relative segment shapes; validated by the adapter
     */
    public static function success(
        array $segments,
        bool $speechDetected = true,
        ?int $durationMs = null,
    ): self {
        return new self(
            ok: true,
            speechDetected: $speechDetected,
            segments: $segments,
            durationMs: $durationMs,
            failureKind: null,
            retryableHint: false,
            failureMessage: '',
        );
    }

    public static function failure(
        ReferenceExternalChunkFailureKind $kind,
        bool $retryableHint = false,
        string $failureMessage = '',
    ): self {
        return new self(
            ok: false,
            speechDetected: false,
            segments: [],
            durationMs: null,
            failureKind: $kind,
            retryableHint: $retryableHint,
            failureMessage: $failureMessage,
        );
    }
}
