<?php

namespace App\Http\Controllers;

use App\Models\Transcription;
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

    public function destroy(Transcription $transcription): RedirectResponse
    {
        $this->authorize('delete', $transcription);

        $transcription->delete();

        return redirect()->route('transcriptions.index')
            ->with('success', 'Transcription deleted successfully.');
    }
}
