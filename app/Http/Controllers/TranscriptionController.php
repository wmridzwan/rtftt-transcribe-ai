<?php

namespace App\Http\Controllers;

use App\Actions\TranscriptionRetry;
use App\Comparison\ComparisonBuilder;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Editing\TranscriptRevision;
use App\Enums\MediaType;
use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Models\User;
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
        $retryBlocked = $retryEligible && $retry->isBlockedByActiveTranscription($transcription);

        // P6-001 §9: when an active revision exists it is authoritative for
        // presentation; otherwise the immutable machine source is shown.
        $activeRevision = $revisions->active($user, $transcription);

        $displaySegments = $this->displaySegments($transcription, $activeRevision);

        $fullTranscriptText = implode("\n", array_column($displaySegments, 'text'));

        $mediaFile = $transcription->mediaFile;

        // P7-009-CORR-01 corrective cycle 1 (F-1): a Draft/Queued
        // transcription whose dispatch never reached the queue is stranded
        // with no Retry path. The media initiation endpoint re-dispatches
        // the existing attempt idempotently (no new rows), so the detail
        // page offers the same Resume action the media page offers.
        $resumeEligible = in_array($transcription->status, [TranscriptionStatus::Draft, TranscriptionStatus::Queued], true)
            && $mediaFile !== null
            && $user !== null
            && $user->can('update', $mediaFile);

        $mediaAvailable = $mediaFile !== null && $mediaFile->hasPhysicalFile();
        $streamUrl = $mediaAvailable
            ? route('media.stream', ['mediaFile' => $mediaFile->uuid])
            : null;
        // P7-011: explicit purged-source state for the disclosure notice.
        // hasPhysicalFile() is already false for tombstoned rows (so no
        // player renders); this flag drives the visible notice.
        $mediaPurged = $mediaFile !== null && $mediaFile->purged_at !== null;
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

        // P6-005 persisted translation-invalidation presentation: only the
        // persisted marker is surfaced; no freshness is inferred.
        $staleTranslations = $transcription->translations()
            ->whereNotNull('stale_at')
            ->orderBy('target_language')
            ->get();

        // P6-008 revision-history surface: the durable revision graph in
        // version order (read-only; never mutates history or the pointer),
        // persisted author names, and persisted staleness-cause markers only.
        $revisionHistory = $revisions->history($user, $transcription);
        $historyAuthors = $this->historyAuthors($revisionHistory);
        $historyStalenessCauses = $this->historyStalenessCauses($transcription);

        // P6-007 presentation-only source / active-revision / translation
        // comparison; read-only and never writes staleness state.
        $comparisonView = $comparison->build($transcription, $activeRevision);

        return view('transcriptions.show', compact(
            'transcription',
            'retryEligible',
            'retryBlocked',
            'resumeEligible',
            'fullTranscriptText',
            'streamUrl',
            'mediaPurged',
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
            'staleTranslations',
            'revisionHistory',
            'historyAuthors',
            'historyStalenessCauses',
        ));
    }

    /**
     * Persisted author display names for the history surface (P6-008 AC2).
     * Names are read from the persisted users table, never fabricated; an
     * author row that no longer resolves renders as the persisted id.
     *
     * @param  list<TranscriptRevision>  $revisionHistory
     * @return array<int, string>
     */
    private function historyAuthors(array $revisionHistory): array
    {
        $authorIds = array_values(array_unique(array_map(
            static fn (TranscriptRevision $revision): int => $revision->createdBy,
            $revisionHistory
        )));

        if ($authorIds === []) {
            return [];
        }

        return User::query()
            ->whereKey($authorIds)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Persisted staleness-cause markers for the history surface (P6-008 AC8).
     * Maps revision id → sorted target languages whose translation row names
     * that revision as the persisted staleness cause. Only persisted
     * `stale_caused_by_revision_id` links are surfaced; no freshness is
     * inferred.
     *
     * @return array<string, list<string>>
     */
    private function historyStalenessCauses(Transcription $transcription): array
    {
        $causes = [];

        $translations = $transcription->translations()
            ->whereNotNull('stale_caused_by_revision_id')
            ->get(['stale_caused_by_revision_id', 'target_language']);

        foreach ($translations as $translation) {
            $causes[$translation->stale_caused_by_revision_id][] = $translation->target_language->value;
        }

        return array_map(function (array $languages): array {
            $languages = array_values(array_unique($languages));
            sort($languages);

            return $languages;
        }, $causes);
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
     * @return list<array{nav_index: int, position: int, segment_key: string, text_length: int, start: float, end: float, seek: string, formatted_start: string, formatted_end: string, text: string, language: string}>
     */
    private function displaySegments(Transcription $transcription, ?TranscriptRevision $activeRevision): array
    {
        if ($activeRevision !== null) {
            return array_map(fn (RevisionSegmentData $segment): array => [
                'nav_index' => $segment->position,
                'position' => $segment->position,
                'segment_key' => $segment->identity->key(),
                'text_length' => mb_strlen($segment->text),
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
                'segment_key' => 'machine:'.$segment->segment_index,
                'text_length' => mb_strlen($segment->text),
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
