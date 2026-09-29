<?php

namespace App\Translation;

/**
 * Shaped response for the reference external translation boundary.
 *
 * Success carries the target tag, full text, and segment shapes; alignment
 * (indices, timestamps, source-language echo) is validated by the adapter
 * against the frozen TranslationResponseValidator, never trusted. Failure
 * carries a vendor-neutral kind plus an advisory retryability hint that the
 * adapter must ignore for mapping.
 */
final readonly class ReferenceExternalTranslationResponse
{
    /**
     * @param  list<mixed>  $segments  translated segment shapes; validated by the adapter
     */
    private function __construct(
        public bool $ok,
        public ?string $targetLanguage,
        public ?string $text,
        public array $segments,
        public ?ReferenceExternalTranslationFailureKind $failureKind,
        public bool $retryableHint,
        public string $failureMessage,
    ) {}

    /**
     * @param  list<mixed>  $segments  translated segment shapes; validated by the adapter
     */
    public static function success(
        string $targetLanguage,
        ?string $text,
        array $segments,
    ): self {
        return new self(
            ok: true,
            targetLanguage: $targetLanguage,
            text: $text,
            segments: $segments,
            failureKind: null,
            retryableHint: false,
            failureMessage: '',
        );
    }

    public static function failure(
        ReferenceExternalTranslationFailureKind $kind,
        bool $retryableHint = false,
        string $failureMessage = '',
    ): self {
        return new self(
            ok: false,
            targetLanguage: null,
            text: null,
            segments: [],
            failureKind: $kind,
            retryableHint: $retryableHint,
            failureMessage: $failureMessage,
        );
    }
}
