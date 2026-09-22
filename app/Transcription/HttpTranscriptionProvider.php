<?php

namespace App\Transcription;

use Illuminate\Support\Facades\Http;

/**
 * Internal HTTP transcription provider.
 *
 * Calls the authenticated internal Python worker via HTTP.
 * Media is accessed via shared-private-filesystem references,
 * not binary content in the request body.
 */
class HttpTranscriptionProvider implements TranscriptionProvider
{
    public function __construct(
        private readonly string $workerBaseUrl,
        private readonly string $bearerToken,
    ) {}

    public function transcribe(
        TranscriptionInvocation $invocation,
    ): NormalizedTranscript {
        $request = WorkerRequest::create(
            transcriptionId: $invocation->transcriptionId,
            attemptId: $invocation->processingAttemptId,
            mediaReference: $invocation->media,
            requestedLanguage: $invocation->options->requestedLanguage,
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(config('transcription.timeout_seconds', 300))->post(
            $this->workerBaseUrl.'/transcribe',
            $request->toArray(),
        );

        if ($response->failed()) {
            $body = $response->json();
            if (isset($body['error_code'])) {
                $errorResponse = WorkerErrorResponse::fromArray($body);

                // ADR-018: the worker's `retryable` flag is advisory transport
                // metadata only. Domain retryability is authoritative in
                // TranscriptionFailure::isRetryable() and is never overridden
                // here, so the cross-layer contract cannot diverge.
                throw new TranscriptionException(
                    failure: $errorResponse->toFailure(),
                    message: $errorResponse->safeMessage,
                );
            }

            throw new TranscriptionException(
                failure: TranscriptionFailure::WorkerUnavailable,
                message: 'Worker returned HTTP '.$response->status(),
            );
        }

        return WorkerResponseValidator::fromArray($response->json())->toNormalizedTranscript();
    }
}
