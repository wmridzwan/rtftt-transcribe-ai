<?php

namespace App\Http\Controllers;

use App\Actions\TranscriptionRetry;
use App\Comparison\ComparisonBuilder;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Editing\TranscriptRevision;
use App\Enums\MediaType;
use App\Models\Transcription;
use App\TranscriptExperience\SegmentTimestamp;
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

    public function show(Transcription $transcription, TranscriptionRetry $retry, RevisionService $revisions, ComparisonBuilder $comparison): View
    {
        $this->authorize('view', $transcription);

        $user = request()->user();

        $transcription->load(['mediaFile', 'segments', 'processingJobs']);

        $retryEligible = $retry->isEligible($transcription);

        // P6-001 §9: when an active revision exists it is authoritative for
        // presentation; otherwise the immutable machine source is shown.
        $activeRevision = $revisions->active($user, $transcription);

        $displaySegments = $this->displaySegments($transcription, $activeRevision);

        $fullTranscriptText = implode("\n", array_column($displaySegments, 'text'));

        $mediaFile = $transcription->mediaFile;
        $mediaAvailable = $mediaFile !== null && $mediaFile->hasPhysicalFile();
        $streamUrl = $mediaAvailable
            ? route('media.stream', ['mediaFile' => $mediaFile->uuid])
            : null;
        $mediaElement = $mediaFile?->media_type === MediaType::Video ? 'video' : 'audio';

        $playbackSegments = array_map(fn (array $segment): array => [
            'index' => $segment['nav_index'],
            'start' => $segment['start'],
            'end' => $segment['end'],
        ], $displaySegments);

        $languageOrder = ['ms', 'en', 'zh', 'ta', 'und'];
        $segmentLanguages = collect($displaySegments)
            ->map(fn (array $segment): string => $segment['language'])
            ->unique()
            ->sortBy(function (string $language) use ($languageOrder): int {
                $position = array_search($language, $languageOrder, true);

                return $position === false ? count($languageOrder) : $position;
            })
            ->values()
            ->all();

        // P6-003 workspace state: active-revision indicator, undo (strict
        // ancestor = immediate parent), and redo (deterministic unique child).
        $activeRevisionId = $activeRevision?->revisionId;
        $activeRevisionVersion = $activeRevision?->version;
        $undoRevisionId = $activeRevision?->parentRevisionId;
        $redoRevision = $revisions->redoTarget($user, $transcription);

        $canEdit = $user->can('update', $transcription);

        // P6-007 presentation-only source / active-revision / translation
        // comparison; read-only and never writes staleness state.
        $comparisonView = $comparison->build($transcription, $activeRevision);

        return view('transcriptions.show', compact(
            'transcription',
            'retryEligible',
            'fullTranscriptText',
            'streamUrl',
            'mediaElement',
            'playbackSegments',
            'segmentLanguages',
            'displaySegments',
            'activeRevisionId',
            'activeRevisionVersion',
            'undoRevisionId',
            'redoRevision',
            'canEdit',
            'comparisonView',
        ));
    }

    /**
     * Build the workspace display segments: the active revision's segments when
     * one exists, otherwise the immutable machine source.
     *
     * `nav_index` is the stable numeric navigation identity (machine
     * `segment_index`, or the revision's `position` once a revision exists).
     * `position` is the revision-position used as the P6-003 text-edit key, so
     * a machine-source edit composes over the initial materialization's
     * contiguous positions even when machine `segment_index` is sparse.
     *
     * @return list<array{nav_index: int, position: int, start: float, end: float, seek: string, formatted_start: string, formatted_end: string, text: string, language: string}>
     */
    private function displaySegments(Transcription $transcription, ?TranscriptRevision $activeRevision): array
    {
        if ($activeRevision !== null) {
            return array_map(fn (RevisionSegmentData $segment): array => [
                'nav_index' => $segment->position,
                'position' => $segment->position,
                'start' => $segment->startSeconds,
                'end' => $segment->endSeconds,
                'seek' => SegmentTimestamp::fromSeconds($segment->startSeconds)->seek(),
                'formatted_start' => $this->formatStart($segment->startSeconds),
                'formatted_end' => $this->formatStart($segment->endSeconds),
                'text' => $segment->text,
                'language' => $segment->language->value,
            ], $activeRevision->orderedSegments());
        }

        $segments = [];
        $ordinal = 0;

        foreach ($transcription->segments as $segment) {
            $segments[] = [
                'nav_index' => $segment->segment_index,
                'position' => $ordinal,
                'start' => (float) $segment->start_seconds,
                'end' => (float) $segment->end_seconds,
                'seek' => $segment->seek_seconds,
                'formatted_start' => $segment->formatted_start,
                'formatted_end' => $segment->formatted_end,
                'text' => $segment->text,
                'language' => $segment->language->value,
            ];

            $ordinal++;
        }

        return $segments;
    }

    /**
     * Human-readable `MM:SS` / `H:MM:SS` start label, matching the Phase 4
     * `TranscriptionSegment::formatted_start` accessor so revision segments
     * render identically to machine segments.
     */
    private function formatStart(float $startSeconds): string
    {
        $totalSeconds = (int) floor($startSeconds);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
