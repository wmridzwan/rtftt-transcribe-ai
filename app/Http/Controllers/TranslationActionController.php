<?php

namespace App\Http\Controllers;

use App\Actions\TranslationOrchestrator;
use App\Actions\TranslationRetry;
use App\Http\Requests\StartTranslationRequest;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use Illuminate\Http\RedirectResponse;

/**
 * Translation start and manual retry (P5-006, ADR-022).
 *
 * Authorization is enforced HERE, before any service is called: the acting user
 * must be allowed to update the owning transcription. Retry then reloads the
 * persisted translation and evaluates eligibility against that fresh state, so
 * the UI's view of the row never decides what is allowed.
 */
class TranslationActionController extends Controller
{
    public function store(
        StartTranslationRequest $request,
        Transcription $transcription,
        TranslationOrchestrator $orchestrator,
    ): RedirectResponse {
        $target = $request->target();

        try {
            $orchestrator->request($transcription, $target);
        } catch (TranslationException $exception) {
            return $this->back($transcription, $target->value)
                ->with('translation_error', $this->requestMessage($exception));
        }

        return $this->back($transcription, $target->value)
            ->with('translation_notice', 'Translation started.');
    }

    public function retry(Translation $translation, TranslationRetry $retry): RedirectResponse
    {
        $transcription = $translation->transcription;

        $this->authorize('update', $transcription);

        // Reload persisted state; never trust the route-bound instance's age.
        $current = Translation::query()->whereKey($translation->getKey())->first();

        if ($current === null) {
            abort(404);
        }

        $target = $current->target_language->value;

        $actionable = in_array($current->status, [TranslationStatus::Queued, TranslationStatus::Translating], true)
            || $retry->isEligible($current);

        if (! $actionable) {
            return $this->back($transcription, $target)
                ->with('translation_error', 'This translation cannot be retried.');
        }

        try {
            $retry->retry($current);
        } catch (TranslationException $exception) {
            return $this->back($transcription, $target)
                ->with('translation_error', $this->requestMessage($exception));
        }

        return $this->back($transcription, $target)
            ->with('translation_notice', 'Translation retry started.');
    }

    private function back(Transcription $transcription, string $target): RedirectResponse
    {
        return redirect()->route('transcriptions.translations.show', [
            'transcription' => $transcription,
            'target' => $target,
        ]);
    }

    /**
     * Fixed, user-safe wording; exception messages and causes are never shown.
     */
    private function requestMessage(TranslationException $exception): string
    {
        return $exception->failure === TranslationFailure::InvalidRequest
            ? 'This translation cannot be started or retried right now.'
            : 'The translation could not be queued. Please try again.';
    }
}
