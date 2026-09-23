<?php

namespace App\Observability;

use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\Translation;

/**
 * Builds the ADR-017 minimum correlation context for structured log records.
 *
 * Product-semantic-neutral (P7-005): it only assembles identifiers and runtime
 * metadata already available on the domain models; it does not change job,
 * queue, provider, or persistence behavior.
 */
final class LogContext
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forTranscription(Transcription $transcription, ?ProcessingJob $attempt = null, array $extra = []): array
    {
        $context = [
            'transcription_id' => $transcription->getKey(),
            'media_file_id' => $transcription->media_file_id,
            'model' => config('transcription.model'),
        ];

        if ($attempt !== null) {
            $context['processing_job_id'] = $attempt->getKey();
            $context['attempt_number'] = ProcessingJob::query()
                ->where('transcription_id', $transcription->getKey())
                ->where('id', '<=', $attempt->getKey())
                ->count();
            $context['stage'] = $attempt->stage->value;
        }

        return array_merge($context, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forTranslation(Translation $translation, array $extra = []): array
    {
        return array_merge([
            'translation_id' => $translation->getKey(),
            'transcription_id' => $translation->transcription_id,
            'target_language' => $translation->target_language->value,
            'model' => config('translation.model'),
        ], $extra);
    }
}
