<?php

namespace App\Transcription;

/**
 * Validate and parse a worker response array.
 *
 * Rejects malformed responses with TranscriptionException.
 */
final class WorkerResponseValidator
{
    /**
     * Parse a worker success response from decoded JSON.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws TranscriptionException if the response is malformed
     */
    public static function fromArray(array $data): WorkerResponse
    {
        if (! is_array($data['segments'] ?? null)) {
            throw new TranscriptionException(
                failure: TranscriptionFailure::InvalidWorkerResponse,
                message: 'Worker response missing or invalid segments array.',
            );
        }

        $segments = [];
        foreach ($data['segments'] as $i => $seg) {
            if (! is_array($seg)) {
                throw new TranscriptionException(
                    failure: TranscriptionFailure::InvalidWorkerResponse,
                    message: "Worker response segment {$i} is not an array.",
                );
            }

            $segments[] = new WorkerSegmentData(
                segmentIndex: (int) ($seg['segment_index'] ?? $i),
                startSeconds: (float) ($seg['start_seconds'] ?? 0.0),
                endSeconds: (float) ($seg['end_seconds'] ?? 0.0),
                text: (string) ($seg['text'] ?? ''),
                language: LanguageIdentifier::fromBcp47((string) ($seg['language'] ?? 'und')),
            );
        }

        $text = (string) ($data['text'] ?? '');
        $speechDetected = (bool) ($data['speech_detected'] ?? true);

        // Validate no-speech consistency
        if (! $speechDetected && $text !== '') {
            throw new TranscriptionException(
                failure: TranscriptionFailure::InvalidWorkerResponse,
                message: 'Worker response: speech not detected but text is non-empty.',
            );
        }

        if (! $speechDetected && $segments !== []) {
            throw new TranscriptionException(
                failure: TranscriptionFailure::InvalidWorkerResponse,
                message: 'Worker response: speech not detected but segments are non-empty.',
            );
        }

        return new WorkerResponse(
            contractVersion: (string) ($data['contract_version'] ?? WorkerContract::VERSION),
            text: $text,
            language: LanguageIdentifier::fromBcp47((string) ($data['language'] ?? 'und')),
            durationSeconds: (float) ($data['duration_seconds'] ?? 0.0),
            speechDetected: $speechDetected,
            segments: $segments,
        );
    }
}
