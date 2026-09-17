<?php

namespace App\Transcription;

/**
 * Logical transcription identity.
 *
 * A logical transcription represents a user's request for transcription
 * of a specific media file. It persists across retries and retranscriptions.
 */
final readonly class TranscriptionIdentity
{
    public function __construct(
        public int $transcriptionId,
        public int $mediaFileId,
        public int $userId,
    ) {
        if ($transcriptionId <= 0) {
            throw new \InvalidArgumentException('Transcription ID must be a positive integer.');
        }

        if ($mediaFileId <= 0) {
            throw new \InvalidArgumentException('Media file ID must be a positive integer.');
        }

        if ($userId <= 0) {
            throw new \InvalidArgumentException('User ID must be a positive integer.');
        }
    }

    /**
     * Create a new identity (for retranscription, a new transcription ID is used).
     */
    public static function create(
        int $transcriptionId,
        int $mediaFileId,
        int $userId,
    ): self {
        return new self(
            transcriptionId: $transcriptionId,
            mediaFileId: $mediaFileId,
            userId: $userId,
        );
    }
}
