<?php

namespace App\Transcription;

/**
 * Ownership invariant: a transcription belongs to a media file,
 * which belongs to a user. Cross-user isolation must be maintained.
 */
final readonly class TranscriptionOwnership
{
    public function __construct(
        public int $userId,
        public int $mediaFileId,
        public int $transcriptionId,
    ) {}

    /**
     * Check if the given user owns this transcription's media file.
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }

    /**
     * Assert ownership.
     *
     * @throws \InvalidArgumentException if user does not own the transcription
     */
    public function assertOwnership(int $userId): void
    {
        if (! $this->isOwnedBy($userId)) {
            throw new \InvalidArgumentException(
                'User '.$userId.' does not own transcription '.$this->transcriptionId.'.'
            );
        }
    }
}
