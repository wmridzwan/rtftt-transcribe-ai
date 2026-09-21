<?php

namespace App\Actions;

use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * P5-005 manual domain retry (mirrors ADR-018), attempt-fenced (P5-004 cycle 2).
 *
 * Retry is manual-only. Each retry mints a new attempt token under a guarded
 * `failed → queued` compare-and-set; a late writer or recovery acting on the
 * previous token can no longer mutate the new attempt. A concurrent retry
 * converges on the existing active attempt instead of leaking a unique-index
 * exception.
 */
class TranslationRetry
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TranslationDispatcher $dispatcher,
    ) {}

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

        $token = (string) Str::uuid();

        $won = $this->database->transaction(function () use ($translation, $token): bool {
            $affected = Translation::query()
                ->whereKey($translation->getKey())
                ->where('status', TranslationStatus::Failed->value)
                ->update([
                    'status' => TranslationStatus::Queued->value,
                    'attempt_token' => $token,
                    'failure_code' => null,
                    'completed_at' => null,
                    'started_at' => null,
                ]);

            return $affected === 1;
        });

        $translation->refresh();

        if (! $won) {
            // A concurrent retry won the CAS; converge on the active attempt.
            $active = $this->active($translation);

            if ($active !== null) {
                return $active;
            }

            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation is not retry-eligible.',
            );
        }

        $this->dispatcher->dispatch($translation, $token);

        return $translation;
    }

    private function active(Translation $translation): ?Translation
    {
        return Translation::query()
            ->where('transcription_id', $translation->transcription_id)
            ->where('target_language', $translation->target_language->value)
            ->whereIn('status', [
                TranslationStatus::Queued->value,
                TranslationStatus::Translating->value,
            ])
            ->orderByDesc('id')
            ->first();
    }
}
