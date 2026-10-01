<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DemoTranscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // P7-009-CORR-01: defense in depth if a route cache was built under another env.
        abort_if(app()->environment('production'), 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'language' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        $extension = 'mp3';
        $mediaType = MediaType::Audio;

        $mediaFile = MediaFile::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'demo_recording.'.$extension,
            'storage_filename' => Str::random(40).'.'.$extension,
            'storage_path' => 'media/demo/'.Str::random(40).'.'.$extension,
            'media_type' => $mediaType,
            'mime_type' => 'audio/mpeg',
            'extension' => $extension,
            'file_size_bytes' => fake()->numberBetween(5242880, 52428800),
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'audio_codec' => 'aac',
            'sample_rate' => 44100,
            'channels' => 2,
            'status' => MediaStatus::Ready,
        ]);

        $transcription = Transcription::create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
            'title' => $validated['title'],
            'language' => $validated['language'] ?? null,
            'detected_language' => $validated['language'] ?? 'en',
            'model' => 'faster-whisper-medium',
            'status' => TranscriptionStatus::Completed,
            'full_text' => 'This is a demo transcription created for prototype purposes. The actual transcription processing will be implemented in Phase 4.',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'processing_seconds' => fake()->numberBetween(10, 120),
        ]);

        TranscriptionSegment::create([
            'transcription_id' => $transcription->id,
            'segment_index' => 0,
            'start_seconds' => 0,
            'end_seconds' => 5,
            'text' => 'This is a demo transcription.',
        ]);

        ProcessingJob::create([
            'transcription_id' => $transcription->id,
            'job_uuid' => (string) Str::uuid(),
            'worker_name' => 'demo-worker',
            'stage' => ProcessingStage::Transcribe,
            'status' => ProcessingStatus::Completed,
            'progress_percentage' => 100,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'processing_seconds' => fake()->numberBetween(10, 120),
            'logs' => ['Demo transcription created', 'Processing complete'],
        ]);

        return redirect()->route('transcriptions.show', $transcription)
            ->with('success', 'Demo transcription created successfully.');
    }
}
