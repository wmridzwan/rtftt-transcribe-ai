<?php

namespace App\Actions;

use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatches a translation execution attempt onto the configured queue
 * (P5-004 cycle 2), mirroring the Phase 3 queue convention.
 *
 * P5-004B: a successful dispatch stamps `dispatched_at` on the attempt. A
 * `queued` row that was never dispatched can therefore be re-dispatched safely
 * (same attempt token, no second row) by `ensureDispatched()`.
 */
class TranslationDispatcher
{
    /**
     * @throws TranslationException when the queue rejects the message
     */
    public function dispatch(Translation $translation, string $attemptToken): void
    {
        $queue = (string) config('translation.queue', 'translation');
        $connection = config('translation.queue_connection');

        try {
            $pending = ProcessTranslation::dispatch(
                $translation->getKey(),
                $translation->transcription_id,
                $attemptToken,
            )->onQueue($queue);

            if (is_string($connection) && $connection !== '') {
                $pending->onConnection($connection);
            }

            // The message is pushed when the pending dispatch is destroyed.
            unset($pending);
        } catch (Throwable $exception) {
            Log::error('Translation dispatch failed.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            throw new TranslationException(
                TranslationFailure::ProcessingFailed,
                'The translation could not be queued.',
                $exception,
            );
        }

        // The push succeeded: the job will run even if the bookkeeping stamp
        // below fails, so a stamp failure must never surface as a dispatch
        // failure to the caller.
        try {
            Translation::query()
                ->whereKey($translation->getKey())
                ->where('attempt_token', $attemptToken)
                ->update(['dispatched_at' => now()]);
        } catch (Throwable $exception) {
            Log::warning('Translation dispatched_at stamp failed after a successful queue push.', [
                'translation_id' => $translation->getKey(),
                'transcription_id' => $translation->transcription_id,
                'attempt_token' => $attemptToken,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        Log::info('Translation dispatched to queue.', [
            'translation_id' => $translation->getKey(),
            'transcription_id' => $translation->transcription_id,
            'queue' => $queue,
            'connection' => is_string($connection) && $connection !== '' ? $connection : config('queue.default'),
        ]);
    }

    /**
     * Re-dispatch a queued attempt whose message never reached the queue.
     *
     * Duplicate messages for one attempt token are harmless (the job claim is a
     * token-fenced compare-and-set), so the only hazard avoided here is a row
     * that is stranded in `queued` forever.
     *
     * @throws TranslationException when the queue rejects the message
     */
    public function ensureDispatched(Translation $translation): void
    {
        $current = Translation::query()->whereKey($translation->getKey())->first();

        if ($current === null || ! $current->isAwaitingDispatch()) {
            return;
        }

        $this->dispatch($current, (string) $current->attempt_token);
    }
}
