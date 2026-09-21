<?php

namespace App\Http\Controllers;

use App\Actions\TranslationRetry;
use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Translation workspace for one transcription (P5-006, ADR-022 D5-07).
 *
 * Read-only over persisted state: it never calls the provider, and rendering
 * never writes to the source transcript or its segments.
 */
class TranslationWorkspaceController extends Controller
{
    public function show(Request $request, Transcription $transcription, TranslationRetry $retry): View|RedirectResponse
    {
        $this->authorize('view', $transcription);

        $requested = $request->query('target');
        $selected = null;

        if ($requested !== null) {
            $selected = is_string($requested) ? TranslationTarget::tryFrom($requested) : null;

            if ($selected === null) {
                return redirect()
                    ->route('transcriptions.translations.show', $transcription)
                    ->with('translation_error', 'That target language is not supported.');
            }
        }

        /** @var array<string, Translation> $latest newest translation per target */
        $latest = [];

        foreach ($transcription->translations()->orderBy('id')->get() as $translation) {
            $latest[$translation->target_language->value] = $translation;
        }

        if ($selected === null && $latest !== []) {
            $selected = $this->defaultTarget($latest);
        }

        $translation = $selected !== null ? ($latest[$selected->value] ?? null) : null;

        $translatedSegments = collect();
        $sourceSegments = collect();

        if ($translation?->status === TranslationStatus::Completed) {
            $translatedSegments = $translation->segments()->orderBy('segment_index')->get();
            $sourceSegments = $transcription->segments()->orderBy('segment_index')->get()->keyBy('segment_index');
        }

        return view('translations.workspace', [
            'transcription' => $transcription,
            'isTranscriptionCompleted' => $transcription->status === TranscriptionStatus::Completed,
            'targets' => TranslationTarget::cases(),
            'latest' => $latest,
            'selected' => $selected,
            'translation' => $translation,
            'translatedSegments' => $translatedSegments,
            'sourceSegments' => $sourceSegments,
            // Retry is offered only when the canonical taxonomy classifies the
            // currently persisted failure as retryable.
            'canRetry' => $translation !== null && $retry->isEligible($translation),
        ]);
    }

    public function status(Translation $translation, TranslationRetry $retry): JsonResponse
    {
        $this->authorize('view', $translation->transcription);

        $current = Translation::query()->whereKey($translation->getKey())->firstOrFail();

        return response()->json([
            'status' => $current->status->value,
            'awaiting_dispatch' => $current->isAwaitingDispatch(),
            'failure_code' => $current->failure_code?->value,
            'retryable' => $retry->isEligible($current),
        ]);
    }

    /**
     * Prefer a completed translation, then an in-progress one, then any.
     *
     * @param  array<string, Translation>  $latest
     */
    private function defaultTarget(array $latest): TranslationTarget
    {
        foreach ([TranslationStatus::Completed, TranslationStatus::Translating, TranslationStatus::Queued] as $status) {
            foreach ($latest as $translation) {
                if ($translation->status === $status) {
                    return $translation->target_language;
                }
            }
        }

        return array_values($latest)[0]->target_language;
    }
}
