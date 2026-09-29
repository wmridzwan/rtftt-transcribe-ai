<?php

namespace App\Translation;

/**
 * Outbound request for the reference external translation boundary.
 *
 * Carries small identifiers plus segment-aligned source text only: never
 * media, never ownership data. The authorization value is fixture-injected
 * transport metadata; it must never be written to logs.
 */
final readonly class ReferenceExternalTranslationRequest
{
    /**
     * @param  list<array<string, mixed>>  $segments  segment-aligned source shapes
     */
    public function __construct(
        public string $providerKey,
        public string $modelPinned,
        public string $baseUrl,
        public string $authorization,
        public string $requestId,
        public int $transcriptionId,
        public ?int $translationId,
        public string $targetLanguage,
        public array $segments,
        public int $timeoutSeconds,
    ) {}
}
