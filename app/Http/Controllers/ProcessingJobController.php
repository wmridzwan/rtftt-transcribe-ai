<?php

namespace App\Http\Controllers;

use App\Models\ProcessingJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessingJobController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProcessingJob::with('transcription');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($stage = $request->input('stage')) {
            $query->where('stage', $stage);
        }

        $jobs = $query->latest()->paginate(15)->withQueryString();

        return view('jobs.index', compact('jobs'));
    }

    public function show(ProcessingJob $processingJob): View
    {
        $processingJob->load('transcription.mediaFile');

        return view('jobs.show', ['job' => $processingJob]);
    }
}
