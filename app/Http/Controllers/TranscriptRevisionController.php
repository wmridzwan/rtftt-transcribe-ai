<?php

namespace App\Http\Controllers;

use App\Editing\Persistence\MachineSourceMaterializer;
use App\Editing\RedoUnavailableException;
use App\Editing\RevisionConflictException;
use App\Editing\RevisionService;
use App\Editing\TextEditComposer;
use App\Editing\TimingEditComposer;
use App\Editing\UndoUnavailableException;
use App\Models\Transcription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Text-editing + undo/redo endpoints for the transcript workspace (P6-003).
 *
 * Thin HTTP boundary over the authorization-fenced {@see RevisionService}. It
 * classifies domain outcomes into workspace feedback and never mutates the
 * immutable machine source or existing revisions. A text edit is always a new
 * append-only revision; undo/redo are active-pointer movements.
 */
class TranscriptRevisionController extends Controller
{
    public function store(
        Request $request,
        Transcription $transcription,
        RevisionService $revisions,
        TextEditComposer $composer,
    ): RedirectResponse {
        $this->authorize('update', $transcription);

        $validated = $request->validate([
            'expected_base' => ['nullable', 'string'],
            'segments' => ['required', 'array', 'min:1'],
            'segments.*' => ['string'],
        ]);

        $user = $request->user();
        $expectedBase = $this->normalizeRevisionId($validated['expected_base'] ?? null);

        /** @var array<int, string> $texts */
        $texts = $validated['segments'];

        try {
            if ($expectedBase === null) {
                // Editing from the machine source: materialize the durable
                // initial copy, then append the text edit derived from it. The
                // materialized copy remains reachable by undo.
                $initial = $revisions->materializeInitial($user, $transcription);
                $segments = $composer->compose($initial->segments, $texts);
                $revisions->edit($user, $transcription, $initial->revisionId, $segments);
            } else {
                $active = $revisions->active($user, $transcription);

                if ($active === null || $active->revisionId !== $expectedBase) {
                    throw RevisionConflictException::staleBase($expectedBase, $active?->revisionId);
                }

                $segments = $composer->compose($active->segments, $texts);
                $revisions->edit($user, $transcription, $expectedBase, $segments);
            }
        } catch (RevisionConflictException) {
            return $this->conflictResponse($transcription);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($transcription, $exception->getMessage());
        }

        return $this->noticeResponse($transcription, 'Your edits were saved as a new revision.');
    }

    /**
     * Timing-only edit (P6-004): replace the active revision segment timing and
     * append the result as a new revision. Text, language, identity, and
     * position are preserved; the immutable machine source is never written.
     *
     * A first edit from the machine source materializes the initial (machine
     * copy) revision and appends the timing edit derived from it. A stale
     * expected base is the canonical conflict; invalid timing is a domain
     * validation error. Both leave persistence unchanged.
     */
    public function timing(
        Request $request,
        Transcription $transcription,
        RevisionService $revisions,
        TimingEditComposer $composer,
        MachineSourceMaterializer $materializer,
    ): RedirectResponse {
        $this->authorize('update', $transcription);

        $validated = $request->validate([
            'expected_base' => ['nullable', 'string'],
            'timings' => ['required', 'array', 'min:1'],
            'timings.*' => ['array'],
            'timings.*.start' => ['required', 'numeric'],
            'timings.*.end' => ['required', 'numeric'],
        ]);

        $user = $request->user();
        $expectedBase = $this->normalizeRevisionId($validated['expected_base'] ?? null);

        /** @var array<int, mixed> $timings */
        $timings = $validated['timings'];

        try {
            if ($expectedBase === null) {
                // Editing from the machine source. Compose (and therefore
                // validate) against the pure machine-source sequence *before*
                // any write, so an invalid first timing edit never leaves a
                // materialized revision behind. The machine timing is copied
                // verbatim into the initial revision; only then is the durable
                // initial revision materialized and the edit appended.
                $machineSequence = $materializer->materialize($transcription, $user->getKey());
                $segments = $composer->compose($machineSequence->segments, $timings);

                $initial = $revisions->materializeInitial($user, $transcription);
                $revisions->edit($user, $transcription, $initial->revisionId, $segments);
            } else {
                $active = $revisions->active($user, $transcription);

                if ($active === null || $active->revisionId !== $expectedBase) {
                    throw RevisionConflictException::staleBase($expectedBase, $active?->revisionId);
                }

                $segments = $composer->compose($active->segments, $timings);
                $revisions->edit($user, $transcription, $expectedBase, $segments);
            }
        } catch (RevisionConflictException) {
            return $this->timingConflictResponse($transcription);
        } catch (InvalidArgumentException $exception) {
            return $this->timingErrorResponse($transcription, $exception->getMessage());
        }

        return $this->timingNoticeResponse($transcription, 'Timing changes saved as a new revision.');
    }

    public function undo(Request $request, Transcription $transcription, RevisionService $revisions): RedirectResponse
    {
        $this->authorize('update', $transcription);

        $validated = $request->validate([
            'target' => ['required', 'string'],
            'expected_base' => ['nullable', 'string'],
        ]);

        try {
            $revisions->undo(
                $request->user(),
                $transcription,
                $validated['target'],
                $this->normalizeRevisionId($validated['expected_base'] ?? null),
            );
        } catch (RevisionConflictException) {
            return $this->conflictResponse($transcription);
        } catch (UndoUnavailableException|InvalidArgumentException $exception) {
            return $this->errorResponse($transcription, $exception->getMessage());
        }

        return $this->noticeResponse($transcription, 'Undo applied.');
    }

    public function redo(Request $request, Transcription $transcription, RevisionService $revisions): RedirectResponse
    {
        $this->authorize('update', $transcription);

        $validated = $request->validate([
            'expected_base' => ['nullable', 'string'],
        ]);

        try {
            $revisions->redo(
                $request->user(),
                $transcription,
                $this->normalizeRevisionId($validated['expected_base'] ?? null),
            );
        } catch (RevisionConflictException) {
            return $this->conflictResponse($transcription);
        } catch (RedoUnavailableException) {
            return $this->errorResponse($transcription, 'Redo is not available.');
        }

        return $this->noticeResponse($transcription, 'Redo applied.');
    }

    private function normalizeRevisionId(?string $revisionId): ?string
    {
        return $revisionId === null || $revisionId === '' ? null : $revisionId;
    }

    private function conflictResponse(Transcription $transcription): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with(
            'revision_conflict',
            'This transcript changed since you composed your edit. Nothing was saved and no merge was attempted. Reload the current version and edit again.',
        );
    }

    private function errorResponse(Transcription $transcription, string $message): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with('revision_error', $message);
    }

    private function noticeResponse(Transcription $transcription, string $message): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with('revision_notice', $message);
    }

    private function timingConflictResponse(Transcription $transcription): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with(
            'timing_conflict',
            'This transcript changed since you composed your timing edit. Nothing was saved and no merge was attempted. Reload the current version and edit again.',
        );
    }

    private function timingErrorResponse(Transcription $transcription, string $message): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with('timing_error', $message);
    }

    private function timingNoticeResponse(Transcription $transcription, string $message): RedirectResponse
    {
        return $this->redirectToWorkspace($transcription)->with('timing_notice', $message);
    }

    private function redirectToWorkspace(Transcription $transcription): RedirectResponse
    {
        return redirect()->route('transcriptions.show', $transcription);
    }
}
