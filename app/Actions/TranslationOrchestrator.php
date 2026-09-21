<?php

namespace App\Actions;

use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\DB;

/**
 * Requests translation of a completed transcript into a target language
 * (ADR-022, D5-03/D5-05).
 *
 * Translation is derived data: the source transcription is never modified. The
 * orchestrator resolves or creates the target translation row and dispatches
 * the asynchronous job. Content is only ever written by the translation writer.
 */
class TranslationOrchestrator
{
    public function request(Transcription $transcription, TranslationTarget $target): Translation
    {
        if ($transcription->status !== TranscriptionStatus::Completed) {
            throw new TranslationException(
                TranslationFailure::InvalidRequest,
                'Only completed transcriptions can be translated.',
            );
        }

        [$translation, $dispatch] = DB::transaction(function () use ($transcription, $target): array {
            $completed = Translation::query()
                ->where('transcription_id', $transcription->getKey())
                ->where('target_language', $target->value)
                ->where('status', TranslationStatus::Completed->value)
                ->lockForUpdate()
                ->first();

            if ($completed !== null) {
                return [$completed, false];
            }

            $active = Translation::query()
                ->where('transcription_id', $transcription->getKey())
                ->where('target_language', $target->value)
                ->whereIn('status', [
                    TranslationStatus::Pending->value,
                    TranslationStatus::Queued->value,
                    TranslationStatus::Translating->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                if ($active->status === TranslationStatus::Pending) {
                    $active->forceFill(['status' => TranslationStatus::Queued])->save();

                    return [$active, true];
                }

                return [$active, false];
            }

            $translation = new Translation([
                'transcription_id' => $transcription->getKey(),
                'target_language' => $target,
                'status' => TranslationStatus::Queued,
            ]);
            $translation->save();

            return [$translation, true];
        });

        if ($dispatch) {
            ProcessTranslation::dispatch($translation->getKey(), $transcription->getKey());
        }

        return $translation;
    }
}
