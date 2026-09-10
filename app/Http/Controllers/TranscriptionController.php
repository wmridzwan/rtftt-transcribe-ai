<?php

namespace App\Http\Controllers;

use App\Models\Transcription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $query = Transcription::with('mediaFile');

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        }

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($language = $request->input('language')) {
            $query->where('language', $language);
        }

        $transcriptions = $query->latest()->paginate(15)->withQueryString();

        return view('transcriptions.index', compact('transcriptions'));
    }

    public function create(): View
    {
        return view('transcriptions.create');
    }

    public function show(Transcription $transcription): View
    {
        $this->authorize('view', $transcription);

        $transcription->load(['mediaFile', 'segments', 'processingJobs']);

        return view('transcriptions.show', compact('transcription'));
    }
}
