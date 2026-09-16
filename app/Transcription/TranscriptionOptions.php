<?php

namespace App\Transcription;

/**
 * Options for a transcription request.
 *
 * requested_language is nullable: null means auto-detect.
 * When explicitly set, it remains a hint, not a guarantee.
 */
final readonly class TranscriptionOptions
{
    public function __construct(
        public ?LanguageIdentifier $requestedLanguage = null,
    ) {}
}
