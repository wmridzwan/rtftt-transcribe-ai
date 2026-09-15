<?php

namespace App\Events;

final readonly class StagingCleanupCandidateObserved
{
    public function __construct(
        public int $userId,
        public string $uploadAttemptId,
        public string $stagingDirectory,
    ) {}
}
