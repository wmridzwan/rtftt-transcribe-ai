<?php

namespace App\Http\Controllers;

use App\Actions\TranscriptionRetry;
use App\Models\Transcription;
use App\Transcription\TranscriptionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TranscriptionActionController extends Controller
{
    public function rename(Request $request, Transcription $transcription): RedirectResponse
    {
        $this->authorize('update', $transcription);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $transcription->update(['title' => $validated['title']]);

        return redirect()->back()->with('success', 'Transcription renamed successfully.');
    }

    public function retry(Transcription $transcription, TranscriptionRetry $retry): RedirectResponse
    {
        $this->authorize('update', $transcription);

        try {
            $retry->retry($transcription);
        } catch (TranscriptionException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->route('transcriptions.show', $transcription)
            ->with('success', 'Transcription retry queued.');
    }

    public function destroy(Transcription $transcription): RedirectResponse
    {
        $this->authorize('delete', $transcription);

        $transcription->delete();

        return redirect()->route('transcriptions.index')
            ->with('success', 'Transcription deleted successfully.');
    }
}
