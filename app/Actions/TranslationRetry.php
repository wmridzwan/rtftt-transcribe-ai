<?php

namespace App\Actions;

use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * P5-005 manual domain retry (mirrors ADR-018), attempt-fenced (P5-004 cycle 2).
 *
 * Retry is manual-only. Each retry mints a new attempt token under a guarded
 * `failed → queued` compare-and-set; a late writer or recovery acting on the
 * previous token can no longer mutate the new attempt. A concurrent retry
 * converges on the existing attempt instead of leaking a unique-index
 * exception.
 *
 * P5-004B: the caller's model is never trusted. The persisted row is reloaded,
 * and retryability is part of the compare-and-set predicate, so a stale
 * in-memory model cannot requeue a currently non-retryable failure.
 *
 * Authorization is the caller's responsibility (controller/action layer).
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
        $current = Translation::query()->whereKey($translation->getKey())->first();

        if ($current === null) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation no longer exists.',
            );
        }

        if ($current->status === TranslationStatus::Completed) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'A completed translation cannot be retried.',
            );
        }

        $converged = $this->converge($current);

        if ($converged !== null) {
            return $converged;
        }

        if (! $this->isEligible($current)) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation is not retry-eligible.',
            );
        }

        $token = (string) Str::uuid();

        try {
            $won = $this->database->transaction(fn (): bool => Translation::query()
                ->whereKey($current->getKey())
                ->where('status', TranslationStatus::Failed->value)
                ->whereIn('failure_code', self::retryableFailureCodes())
                ->update([
                    'status' => TranslationStatus::Queued->value,
                    'attempt_token' => $token,
                    'dispatched_at' => null,
                    'failure_code' => null,
                    'completed_at' => null,
                    'started_at' => null,
                ]) === 1);
        } catch (UniqueConstraintViolationException) {
            $won = false;
        }

        if (! $won) {
            // A concurrent retry or request won; converge on the surviving row.
            $converged = $this->converge($current->refresh());

            if ($converged !== null) {
                return $converged;
            }

            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'The translation is not retry-eligible.',
            );
        }

        $current->refresh();

        $this->dispatcher->dispatch($current, $token);

        return $current;
    }

    /**
     * The row this retry converges on, when the target already has a live or
     * completed attempt: the row itself if it is active, otherwise any sibling
     * covered by the one-active-per-target unique index.
     */
    private function converge(Translation $translation): ?Translation
    {
        $existing = Translation::query()
            ->where('transcription_id', $translation->transcription_id)
            ->where('target_language', $translation->target_language->value)
            ->whereIn('status', [
                TranslationStatus::Queued->value,
                TranslationStatus::Translating->value,
                TranslationStatus::Pending->value,
                TranslationStatus::Completed->value,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing === null) {
            return null;
        }

        $this->dispatcher->ensureDispatched($existing);

        return $existing->refresh();
    }

    /**
     * @return list<string>
     */
    private static function retryableFailureCodes(): array
    {
        return array_values(array_map(
            static fn (TranslationFailure $failure): string => $failure->value,
            array_filter(
                TranslationFailure::cases(),
                static fn (TranslationFailure $failure): bool => $failure->isRetryable(),
            ),
        ));
    }
}
