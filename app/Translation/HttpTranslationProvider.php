<?php

namespace App\Translation;

use Illuminate\Support\Facades\Http;

/**
 * Self-hosted translation provider (ADR-022, D5-04).
 *
 * Calls the authenticated internal Python worker translation endpoint with a
 * text-only, segment-aligned payload. No media path or binary is sent.
 */
class HttpTranslationProvider implements TranslationProvider
{
    public function __construct(
        private readonly string $workerBaseUrl,
        private readonly string $bearerToken,
        private readonly string $providerName,
        private readonly string $model,
        private readonly string $contractVersion,
    ) {}

    public function translate(TranslationInvocation $invocation): TranslationResult
    {
        $request = TranslationRequest::create($invocation, $this->contractVersion);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout((int) config('translation.timeout_seconds', 300))->post(
            $this->workerBaseUrl.'/translate',
            $request->toArray(),
        );

        if ($response->failed()) {
            $body = $response->json();

            if (is_array($body) && isset($body['error_code'])) {
                $error = TranslationWorkerError::fromArray($body);

                throw new TranslationException(
                    failure: $error->failure,
                    message: $error->safeMessage,
                );
            }

            throw new TranslationException(
                failure: TranslationFailure::ProviderUnavailable,
                message: 'Translation worker returned HTTP '.$response->status(),
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new TranslationException(
                failure: TranslationFailure::MalformedOutput,
                message: 'Translation worker returned a non-object response.',
            );
        }

        return TranslationResponseValidator::fromArray(
            $body,
            $invocation->targetLanguage,
            $this->providerName,
            $this->model,
        );
    }
}
