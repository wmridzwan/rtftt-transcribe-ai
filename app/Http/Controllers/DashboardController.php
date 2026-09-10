<?php

namespace App\Http\Controllers;

use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $query = Transcription::query();

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        }

        $totalRecordings = (clone $query)->count();
        $completed = (clone $query)->where('status', TranscriptionStatus::Completed)->count();
        $processing = (clone $query)->whereIn('status', [
            TranscriptionStatus::Transcribing,
            TranscriptionStatus::Preparing,
        ])->count();
        $failed = (clone $query)->where('status', TranscriptionStatus::Failed)->count();

        $recentTranscriptions = (clone $query)
            ->with('mediaFile')
            ->latest()
            ->take(5)
            ->get();

        $processingJobs = null;
        if ($isAdmin) {
            $jobsQuery = ProcessingJob::query();
            $queuedJobs = (clone $jobsQuery)->where('status', ProcessingStatus::Queued)->count();
            $runningJobs = (clone $jobsQuery)->where('status', ProcessingStatus::Running)->count();
            $failedJobs = (clone $jobsQuery)->where('status', ProcessingStatus::Failed)->count();

            $processingJobs = [
                'queued' => $queuedJobs,
                'running' => $runningJobs,
                'failed' => $failedJobs,
            ];
        }

        return view('dashboard', compact(
            'totalRecordings',
            'completed',
            'processing',
            'failed',
            'recentTranscriptions',
            'processingJobs',
        ));
    }
}
