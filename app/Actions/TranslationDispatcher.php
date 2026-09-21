<?php

namespace App\Actions;

use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches a translation execution attempt onto the configured queue
 * (P5-004 cycle 2), mirroring the Phase 3 queue convention.
 */
class TranslationDispatcher
{
    public function dispatch(Translation $translation, string $attemptToken): void
    {
        $queue = (string) config('translation.queue', 'translation');
        $connection = config('translation.queue_connection');

        $pending = ProcessTranslation::dispatch(
            $translation->getKey(),
            $translation->transcription_id,
            $attemptToken,
        )->onQueue($queue);

        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }

        Log::info('Translation dispatched to queue.', [
            'translation_id' => $translation->getKey(),
            'transcription_id' => $translation->transcription_id,
            'queue' => $queue,
            'connection' => is_string($connection) && $connection !== '' ? $connection : config('queue.default'),
        ]);
    }
}
