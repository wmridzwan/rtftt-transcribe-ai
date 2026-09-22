<?php

namespace App\Http\Controllers;

use App\Actions\TranscriptionRetry;
use App\Enums\MediaType;
use App\Models\Transcription;
use App\TranscriptExperience\TranscriptCopy;
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

    public function show(Transcription $transcription, TranscriptionRetry $retry): View
    {
        $this->authorize('view', $transcription);

        $transcription->load(['mediaFile', 'segments', 'processingJobs']);

        $retryEligible = $retry->isEligible($transcription);
        $fullTranscriptText = TranscriptCopy::fullText($transcription->segments);

        $mediaFile = $transcription->mediaFile;
        $mediaAvailable = $mediaFile !== null && $mediaFile->hasPhysicalFile();
        $streamUrl = $mediaAvailable
            ? route('media.stream', ['mediaFile' => $mediaFile->uuid])
            : null;
        $mediaElement = $mediaFile?->media_type === MediaType::Video ? 'video' : 'audio';

        $playbackSegments = $transcription->segments
            ->map(fn ($segment): array => [
                'index' => $segment->segment_index,
                'start' => (float) $segment->start_seconds,
                'end' => (float) $segment->end_seconds,
            ])
            ->values()
            ->all();

        return view('transcriptions.show', compact(
            'transcription',
            'retryEligible',
            'fullTranscriptText',
            'streamUrl',
            'mediaElement',
            'playbackSegments',
        ));
    }
}
