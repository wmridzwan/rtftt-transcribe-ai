<?php

namespace App\Translation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Self-hosted translation provider (ADR-022, D5-04).
 *
 * Calls the authenticated internal Python worker translation endpoint with a
 * text-only, segment-aligned payload. No media path or binary is sent. The
 * response is validated strictly against the invocation.
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->bearerToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout((int) config('translation.timeout_seconds', 300))->post(
                $this->workerBaseUrl.'/translate',
                $request->toArray(),
            );
        } catch (ConnectionException $exception) {
            throw new TranslationException(
                TranslationFailure::ProviderTimeout,
                'The translation worker connection timed out.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new TranslationException(
                TranslationFailure::ProviderUnavailable,
                'The translation worker connection failed.',
                $exception,
            );
        }

        if ($response->failed()) {
            $body = $response->json();

            if (is_array($body) && isset($body['error_code']) && is_string($body['error_code'])) {
                $error = TranslationWorkerError::fromArray($body);

                throw new TranslationException(
                    failure: $error->failure,
                    message: $error->safeMessage,
                );
            }

            throw new TranslationException(
                failure: self::failureForStatus($response->status()),
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
            $invocation,
            $this->providerName,
            $this->model,
        );
    }

    private static function failureForStatus(int $status): TranslationFailure
    {
        return match (true) {
            in_array($status, [401, 403], true) => TranslationFailure::ConfigurationError,
            in_array($status, [404, 405], true) => TranslationFailure::ConfigurationError,
            $status === 422 => TranslationFailure::MalformedOutput,
            $status === 429 => TranslationFailure::ProviderUnavailable,
            $status === 504 => TranslationFailure::ProviderTimeout,
            $status >= 500 => TranslationFailure::ProviderUnavailable,
            default => TranslationFailure::ProviderFailed,
        };
    }
}
