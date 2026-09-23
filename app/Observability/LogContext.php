<?php

namespace App\Observability;

use App\Http\Middleware\AssignRequestId;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\Translation;
use Throwable;

/**
 * Builds the ADR-017 minimum correlation context for structured log records.
 *
 * Product-semantic-neutral (P7-005): it only assembles identifiers and runtime
 * metadata already available on the domain models; it does not change job,
 * queue, provider, or persistence behavior.
 *
 * Correlation key semantics (P7-005 corrective M-2):
 * - `http_request_id` — the per-HTTP-request correlation id assigned by
 *   {@see AssignRequestId} and propagated into queued work.
 * - `request_id` — the ADR-017 domain/transport id created by the provider
 *   invocation for an outbound worker call; it is never overwritten by the HTTP
 *   correlation id.
 * - `queue_job_id` — the framework queue job id, added by the jobs themselves.
 *
 * Best-effort contract (P7-005 corrective M-1): every method here is additive
 * and must never throw. A failure while assembling observability context must
 * not fail a transcription/translation job or corrupt lifecycle state, so all
 * model access and lookups are defensive and degrade to a partial context.
 */
final class LogContext
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forTranscription(Transcription $transcription, ?ProcessingJob $attempt = null, array $extra = []): array
    {
        $context = [];

        try {
            $context = [
                'transcription_id' => $transcription->getKey(),
                'media_file_id' => $transcription->media_file_id,
                'model' => config('transcription.model'),
            ];

            if ($attempt !== null) {
                $context['processing_job_id'] = $attempt->getKey();
                $context['stage'] = $attempt->stage->value;

                if (! array_key_exists('attempt_number', $extra)) {
                    $context['attempt_number'] = self::attemptNumber($transcription, $attempt);
                }
            }
        } catch (Throwable) {
            // Best-effort only: never break the caller for observability.
        }

        return array_merge($context, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forTranslation(Translation $translation, array $extra = []): array
    {
        $context = [];

        try {
            $context = [
                'translation_id' => $translation->getKey(),
                'transcription_id' => $translation->transcription_id,
                'target_language' => $translation->target_language->value,
                'model' => config('translation.model'),
            ];
        } catch (Throwable) {
            // Best-effort only: never break the caller for observability.
        }

        return array_merge($context, $extra);
    }

    /**
     * Best-effort attempt ordinal for a processing attempt.
     *
     * Returns `null` when the lookup cannot be completed so a transient database
     * problem in a logging path can never propagate into job control flow.
     */
    public static function attemptNumber(Transcription $transcription, ProcessingJob $attempt): ?int
    {
        try {
            return (int) ProcessingJob::query()
                ->where('transcription_id', $transcription->getKey())
                ->where('id', '<=', $attempt->getKey())
                ->count();
        } catch (Throwable) {
            return null;
        }
    }
}
