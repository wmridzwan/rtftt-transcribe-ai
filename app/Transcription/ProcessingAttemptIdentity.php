<?php

namespace App\Transcription;

use Illuminate\Support\Str;

/**
 * Processing attempt identity.
 *
 * Each attempt to transcribe a media file gets a unique attempt ID.
 * Retries of the same logical transcription use the same transcription ID
 * but different attempt IDs. Retranscription (a new user request) gets
 * both a new transcription ID and a new attempt ID.
 */
final readonly class ProcessingAttemptIdentity
{
    public function __construct(
        public int $attemptId,
        public string $requestId,
    ) {
        if ($attemptId <= 0) {
            throw new \InvalidArgumentException('Attempt ID must be a positive integer.');
        }

        if ($requestId === '') {
            throw new \InvalidArgumentException('Request ID must not be empty.');
        }
    }

    /**
     * Create a new attempt with a server-generated request ID.
     */
    public static function create(int $attemptId): self
    {
        return new self(
            attemptId: $attemptId,
            requestId: (string) Str::uuid(),
        );
    }
}
