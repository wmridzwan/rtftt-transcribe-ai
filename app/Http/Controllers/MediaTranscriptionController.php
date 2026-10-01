<?php

namespace App\Http\Controllers;

use App\Actions\TranscriptionInitiator;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Transcription\TranscriptionException;
use Illuminate\Http\RedirectResponse;
use Throwable;

class MediaTranscriptionController extends Controller
{
    public function store(MediaFile $mediaFile, TranscriptionInitiator $initiator): RedirectResponse
    {
        $this->authorize('update', $mediaFile);

        try {
            $transcription = $initiator->start($mediaFile);
        } catch (TranscriptionException $exception) {
            return redirect()->route('media.show', $mediaFile)
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            // P7-009-CORR-01 corrective cycle 1 (F-1): the database rows may
            // already be committed as Queued when the queue dispatch fails.
            // Convert the failure into a recoverable redirect (never a
            // second Transcription/ProcessingJob) and point the user at the
            // existing queued attempt, which the Resume control re-dispatches
            // idempotently via this same endpoint.
            report($exception);

            $stranded = Transcription::query()
                ->where('media_file_id', $mediaFile->getKey())
                ->whereIn('status', [
                    TranscriptionStatus::Draft->value,
                    TranscriptionStatus::Queued->value,
                ])
                ->orderByDesc('id')
                ->first();

            if ($stranded !== null) {
                return redirect()->route('transcriptions.show', $stranded)
                    ->with('error', 'Transcription was saved but the queue is temporarily unavailable. Use Resume Transcription to retry dispatch.');
            }

            return redirect()->route('media.show', $mediaFile)
                ->with('error', 'Transcription could not be queued. Please try again.');
        }

        return redirect()->route('transcriptions.show', $transcription)
            ->with('success', 'Transcription queued.');
    }
}
