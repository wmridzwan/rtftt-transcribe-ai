<?php

namespace App\Transcription;

/**
 * Opaque storage-backed media reference.
 *
 * Passed to the worker instead of binary content or absolute paths.
 * The worker resolves beneath a configured shared-media root.
 */
final readonly class TranscriptionMedia
{
    public function __construct(
        public string $storageKey,
        public string $mimeType,
        public int $fileSizeBytes,
        public ?float $durationSeconds = null,
    ) {
        if ($storageKey === '') {
            throw new \InvalidArgumentException('Storage key must not be empty.');
        }

        if ($fileSizeBytes < 0) {
            throw new \InvalidArgumentException('File size must be non-negative.');
        }

        // Reject absolute paths (Unix and Windows)
        if (str_starts_with($storageKey, '/') || str_starts_with($storageKey, '\\')) {
            throw new \InvalidArgumentException('Storage key must be a relative path.');
        }

        // Reject Windows drive-letter paths (e.g., C:\...)
        if (preg_match('/\A[a-zA-Z]:\\\\/i', $storageKey) === 1) {
            throw new \InvalidArgumentException('Storage key must be a relative path.');
        }

        // Reject traversal
        if (str_contains($storageKey, '..')) {
            throw new \InvalidArgumentException('Storage key must not contain path traversal.');
        }
    }
}
