<?php

namespace App\Actions;

use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Requests translation of a completed transcript into a target language
 * (ADR-022, D5-03/D5-05; P5-004 cycle 2).
 *
 * Single identity model: a failed target is re-run through the same
 * attempt-token path as retry (never a silent second row). Every queued attempt
 * receives a fresh token. Translation is derived data: the source transcription
 * is never modified.
 */
class TranslationOrchestrator
{
    public function __construct(
        private readonly DatabaseManager $database,
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

        [$translation, $dispatchToken] = $this->database->transaction(function () use ($transcription, $target): array {
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
        });

        if ($translation->status === TranslationStatus::Failed) {
            return $this->retry->retry($translation);
        }

        if ($dispatchToken !== null) {
            $this->dispatcher->dispatch($translation, $dispatchToken);
        }

        return $translation;
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
