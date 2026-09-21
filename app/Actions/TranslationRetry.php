<?php

namespace App\Actions;

use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * P5-005 manual domain retry (mirrors ADR-018).
 *
 * Retry is manual-only: there is no automatic retry scheduler or backoff. The
 * translation row is reused; a guarded compare-and-set moves it
 * `failed → queued`, and the completed state is protected. A concurrent retry
 * cannot create a second active translation because the CAS is the correctness
 * boundary.
 */
class TranslationRetry
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Retryability derives from the authoritative provider-neutral
     * TranslationFailure taxonomy, never from worker metadata.
     */
    public function isEligible(Translation $translation): bool
    {
        if ($translation->status !== TranslationStatus::Failed) {
            return false;
        }

        return $translation->failure_code?->isRetryable() ?? false;
    }

    /**
     * @throws TranslationException when the translation is not retry-eligible
     */
    public function retry(Translation $translation): Translation
    {
        if ($translation->status === TranslationStatus::Completed) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'A completed translation cannot be retried.',
            );
        }

        // Idempotent: an already-active translation is returned unchanged.
        $active = $this->active($translation);

        if ($active !== null) {
            return $active;
        }

        if (! $this->isEligible($translation)) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation is not retry-eligible.',
            );
        }

        $won = $this->database->transaction(function () use ($translation): bool {
            $affected = Translation::query()
                ->whereKey($translation->getKey())
                ->where('status', TranslationStatus::Failed->value)
                ->update([
                    'status' => TranslationStatus::Queued->value,
                    'failure_code' => null,
                    'completed_at' => null,
                    'started_at' => null,
                ]);

            return $affected === 1;
        });

        $translation->refresh();

        if (! $won) {
            // A concurrent retry won the CAS; converge on its active row.
            $active = $this->active($translation);

            if ($active !== null) {
                return $active;
            }

            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation is not retry-eligible.',
            );
        }

        ProcessTranslation::dispatch($translation->getKey(), $translation->transcription_id);

        Log::info('Translation retry queued.', [
            'translation_id' => $translation->getKey(),
            'transcription_id' => $translation->transcription_id,
        ]);

        return $translation;
    }

    private function active(Translation $translation): ?Translation
    {
        return Translation::query()
            ->whereKey($translation->getKey())
            ->whereIn('status', [
                TranslationStatus::Queued->value,
                TranslationStatus::Translating->value,
            ])
            ->first();
    }
}
