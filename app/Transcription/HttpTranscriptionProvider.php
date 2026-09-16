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
        TranscriptionMedia $media,
        TranscriptionOptions $options,
    ): NormalizedTranscript {
        $request = WorkerRequest::create(
            transcriptionId: 0, // Will be set by caller
            attemptId: 0, // Will be set by caller
            mediaReference: $media,
            requestedLanguage: $options->requestedLanguage,
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(300)->post(
            $this->workerBaseUrl.'/transcribe',
            $request->toArray(),
        );

        if ($response->failed()) {
            // Check for worker error envelope
            $body = $response->json();
            if (isset($body['error_code'])) {
                $errorResponse = WorkerErrorResponse::fromArray($body);
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
