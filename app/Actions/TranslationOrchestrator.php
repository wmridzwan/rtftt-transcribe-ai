<?php

namespace App\Actions;

use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Requests translation of a completed transcript into a target language
 * (ADR-022, D5-03/D5-05; P5-004 cycle 2, P5-004B).
 *
 * Single identity model: a failed target is re-run through the same
 * attempt-token path as retry (never a silent second row). Every queued attempt
 * receives a fresh token. Translation is derived data: the source transcription
 * is never modified.
 *
 * Concurrent first requests for one (transcription, target) converge on the
 * single row that won the unique index; the loser never sees a raw constraint
 * exception. A `queued` row whose dispatch failed is re-dispatched on the next
 * request instead of staying stranded.
 *
 * Authorization is the caller's responsibility (controller/action layer).
 */
class TranslationOrchestrator
{
    private const CONVERGENCE_ATTEMPTS = 6;

    private const BACKOFF_MICROSECONDS = 25_000;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ConcurrencyErrorDetector $concurrency,
        private readonly TranslationRetry $retry,
        private readonly TranslationDispatcher $dispatcher,
    ) {}

    public function request(Transcription $transcription, TranslationTarget $target): Translation
    {
        if ($transcription->status !== TranscriptionStatus::Completed) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'Only completed transcriptions can be translated.',
            );
        }

        [$translation, $dispatchToken] = $this->resolveRow($transcription, $target);

        if ($translation->status === TranslationStatus::Failed) {
            return $this->retry->retry($translation);
        }

        if ($dispatchToken !== null) {
            $this->dispatcher->dispatch($translation, $dispatchToken);
        } else {
            $this->dispatcher->ensureDispatched($translation);
        }

        return $translation;
    }

    /**
     * Find, revive, or create the single row for the target.
     *
     * @return array{0: Translation, 1: string|null} the row and the token that
     *                                               still has to be dispatched
     */
    private function resolveRow(Transcription $transcription, TranslationTarget $target): array
    {
        $last = null;

        for ($attempt = 1; $attempt <= self::CONVERGENCE_ATTEMPTS; $attempt++) {
            try {
                return $this->database->transaction(
                    fn (): array => $this->resolveRowInTransaction($transcription, $target),
                );
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent request created the row first; the next pass
                // finds it through the normal lookup and converges on it.
                $last = $exception;
            } catch (QueryException $exception) {
                // SQLite reports a lost write-lock upgrade as "database is
                // locked" rather than a constraint error. It is the same race:
                // back off briefly and converge on the winner's row.
                if (! $this->concurrency->causedByConcurrencyError($exception)) {
                    throw $exception;
                }

                $last = $exception;
            }

            usleep(self::BACKOFF_MICROSECONDS * $attempt);
        }

        Log::warning('Translation request convergence exhausted.', [
            'transcription_id' => $transcription->getKey(),
            'target_language' => $target->value,
            'attempts' => self::CONVERGENCE_ATTEMPTS,
            'exception' => $last->getMessage(),
        ]);

        throw new TranslationException(
            TranslationFailure::ProcessingFailed,
            'Concurrent translation requests could not be resolved.',
            $last,
        );
    }

    /**
     * @return array{0: Translation, 1: string|null}
     */
    private function resolveRowInTransaction(Transcription $transcription, TranslationTarget $target): array
    {
        $completed = $this->row($transcription, $target, [TranslationStatus::Completed->value]);

        if ($completed !== null) {
            return [$completed, null];
        }

        $active = $this->row($transcription, $target, [
            TranslationStatus::Pending->value,
            TranslationStatus::Queued->value,
            TranslationStatus::Translating->value,
        ]);

        if ($active !== null) {
            if ($active->status === TranslationStatus::Pending) {
                $token = (string) Str::uuid();
                $active->forceFill([
                    'status' => TranslationStatus::Queued,
                    'attempt_token' => $token,
                    'dispatched_at' => null,
                ])->save();

                return [$active, $token];
            }

            return [$active, null];
        }

        $failed = $this->row($transcription, $target, [TranslationStatus::Failed->value]);

        if ($failed !== null) {
            // Delegate to the single retry path outside the transaction.
            return [$failed, null];
        }

        $token = (string) Str::uuid();
        $translation = new Translation([
            'transcription_id' => $transcription->getKey(),
            'target_language' => $target,
            'status' => TranslationStatus::Queued,
            'attempt_token' => $token,
        ]);
        $translation->save();

        return [$translation, $token];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function row(Transcription $transcription, TranslationTarget $target, array $statuses): ?Translation
    {
        return Translation::query()
            ->where('transcription_id', $transcription->getKey())
            ->where('target_language', $target->value)
            ->whereIn('status', $statuses)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }
}
