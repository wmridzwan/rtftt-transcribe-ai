<?php

namespace App\Translation;

/**
 * Worker translation error envelope (ADR-022).
 *
 * Carries the worker's advisory code/message. Laravel's TranslationFailure
 * taxonomy remains authoritative for retryability.
 */
final readonly class TranslationWorkerError
{
    public function __construct(
        public TranslationFailure $failure,
        public string $safeMessage,
        public string $requestId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $code = (string) ($data['error_code'] ?? '');

        return new self(
            failure: TranslationResponseValidator::failureFromCode($code),
            safeMessage: (string) ($data['safe_message'] ?? 'Translation worker reported an error.'),
            requestId: (string) ($data['request_id'] ?? ''),
        );
    }
}
