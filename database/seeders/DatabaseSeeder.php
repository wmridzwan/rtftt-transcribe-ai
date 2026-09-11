<?php

namespace Database\Seeders;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\Folder;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** @var array<string, Folder> */
    private array $adminFolders = [];

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@rtftt.local',
            'password' => bcrypt('password'),
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@rtftt.local',
            'password' => bcrypt('password'),
        ]);

        $this->seedFolders($admin);
        $this->seedAdminData($admin);
        $this->seedUserData($user);
    }

    private function seedFolders(User $admin): void
    {
        $meetings = Folder::create(['user_id' => $admin->id, 'name' => 'Meetings']);
        $training = Folder::create(['user_id' => $admin->id, 'name' => 'Training']);
        $interviews = Folder::create(['user_id' => $admin->id, 'name' => 'Interviews']);

        // Store folder references for use in seedAdminData
        $this->adminFolders = [
            'meetings' => $meetings,
            'training' => $training,
            'interviews' => $interviews,
        ];
    }

    private function seedAdminData(User $admin): void
    {
        $mediaFiles = [
            [
                'title' => 'Weekly Management Meeting',
                'original_filename' => 'weekly_management_meeting.mp3',
                'media_type' => MediaType::Audio,
                'extension' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 52428800,
                'duration_seconds' => 2700,
                'status' => MediaStatus::Ready,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Completed,
                'processing_seconds' => 420,
                'segments' => [
                    ['start' => 0, 'end' => 7, 'text' => 'Good morning everyone, welcome to today\'s management meeting.'],
                    ['start' => 7, 'end' => 15, 'text' => 'Let\'s start by reviewing the performance metrics from last week.'],
                    ['start' => 15, 'end' => 23, 'text' => 'The transcription team processed 142 recordings, which is a 12% increase from the previous week.'],
                    ['start' => 23, 'end' => 31, 'text' => 'Our average processing time has decreased to 0.25 RTF, which is excellent.'],
                    ['start' => 31, 'end' => 39, 'text' => 'The main bottleneck remains the initial audio extraction phase.'],
                    ['start' => 39, 'end' => 47, 'text' => 'I\'d like to discuss the new server deployment plans for next quarter.'],
                    ['start' => 47, 'end' => 55, 'text' => 'We\'re looking at expanding our GPU capacity to handle the growing workload.'],
                ],
            ],
            [
                'title' => 'SGI Training Recording',
                'original_filename' => 'sgi_training_session.mp4',
                'media_type' => MediaType::Video,
                'extension' => 'mp4',
                'mime_type' => 'video/mp4',
                'file_size_bytes' => 157286400,
                'duration_seconds' => 3600,
                'status' => MediaStatus::Ready,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Completed,
                'processing_seconds' => 540,
                'segments' => [
                    ['start' => 0, 'end' => 8, 'text' => 'Welcome to the SGI Training Program. Today we\'ll cover the fundamentals of our system.'],
                    ['start' => 8, 'end' => 16, 'text' => 'First, let me walk you through the main dashboard interface.'],
                    ['start' => 16, 'end' => 24, 'text' => 'You can see all your recent transcriptions here on the left panel.'],
                    ['start' => 24, 'end' => 32, 'text' => 'The upload process is straightforward - just drag and drop your audio or video files.'],
                    ['start' => 32, 'end' => 40, 'text' => 'Our system supports MP3, M4A, WAV for audio, and MP4, MOV for video.'],
                ],
            ],
            [
                'title' => 'Marketing Workshop',
                'original_filename' => 'marketing_workshop.mp4',
                'media_type' => MediaType::Video,
                'extension' => 'mp4',
                'mime_type' => 'video/mp4',
                'file_size_bytes' => 209715200,
                'duration_seconds' => 5400,
                'status' => MediaStatus::Processing,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Transcribing,
                'processing_seconds' => null,
                'segments' => [],
            ],
            [
                'title' => 'Mandarin Seminar',
                'original_filename' => 'mandarin_seminar.mp3',
                'media_type' => MediaType::Audio,
                'extension' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 41943040,
                'duration_seconds' => 1800,
                'status' => MediaStatus::Ready,
                'language' => 'zh',
                'transcription_status' => TranscriptionStatus::Completed,
                'processing_seconds' => 360,
                'segments' => [
                    ['start' => 0, 'end' => 6, 'text' => '各位好，欢迎参加今天的研讨会。'],
                    ['start' => 6, 'end' => 12, 'text' => '今天我们将讨论人工智能在语音识别领域的最新进展。'],
                    ['start' => 12, 'end' => 18, 'text' => 'Whisper模型在过去一年中取得了显著的改进。'],
                    ['start' => 18, 'end' => 24, 'text' => '特别是在中文识别的准确率方面，已经达到了95%以上。'],
                ],
            ],
            [
                'title' => 'Product Briefing',
                'original_filename' => 'product_briefing.mp3',
                'media_type' => MediaType::Audio,
                'extension' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 31457280,
                'duration_seconds' => 1200,
                'status' => MediaStatus::Failed,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Failed,
                'processing_seconds' => 60,
                'segments' => [],
            ],
        ];

        $this->persistAdminMedia($admin, $mediaFiles);
    }

    /** @param list<array{title: string, original_filename: string, media_type: MediaType, extension: string, mime_type: string, file_size_bytes: int, duration_seconds: int, status: MediaStatus, language: string|null, transcription_status: TranscriptionStatus, processing_seconds: int|null, folder_id?: int|null, segments: list<array{start: int, end: int, text: string}>}> $mediaFiles */
    private function persistAdminMedia(User $admin, array $mediaFiles): void
    {
        foreach ($mediaFiles as $index => $data) {
            $segments = $data['segments'];
            $title = $data['title'];
            $language = $data['language'];
            $status = $data['transcription_status'];
            $processingSeconds = $data['processing_seconds'];

            $mediaFile = MediaFile::create([
                'user_id' => $admin->id,
                'original_filename' => $data['original_filename'],
                'media_type' => $data['media_type'],
                'extension' => $data['extension'],
                'mime_type' => $data['mime_type'],
                'file_size_bytes' => $data['file_size_bytes'],
                'duration_seconds' => $data['duration_seconds'],
                'status' => $data['status'],
                'uuid' => (string) Str::uuid(),
                'storage_filename' => Str::random(40).'.'.$data['extension'],
                'storage_path' => 'media/'.Str::random(40).'.'.$data['extension'],
                'audio_codec' => match ($data['extension']) {
                    'mp3' => 'mp3',
                    'm4a' => 'aac',
                    'wav' => 'pcm',
                    default => 'aac',
                },
                'video_codec' => $data['media_type'] === MediaType::Video ? 'h264' : null,
                'sample_rate' => match ($data['extension']) {
                    'mp3' => 44100,
                    'm4a' => 44100,
                    'wav' => 44100,
                    'mp4' => 48000,
                    'mov' => 48000,
                    default => 44100,
                },
                'channels' => 2,
                'folder_id' => match ($index) {
                    0 => $this->adminFolders['meetings']->id,
                    1, 2 => $this->adminFolders['training']->id,
                    3, 4 => $this->adminFolders['interviews']->id,
                    default => null,
                },
                'display_name' => $title,
            ]);

            $transcriptionStartedAt = in_array($status, [TranscriptionStatus::Transcribing, TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? Carbon::instance(fake()->dateTimeBetween('-30 days', '-1 day'))
                : null;
            $transcriptionCompletedAt = in_array($status, [TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? Carbon::instance(fake()->dateTimeBetween($transcriptionStartedAt ?? '-30 days', 'now'))
                : null;

            $transcription = Transcription::create([
                'user_id' => $admin->id,
                'media_file_id' => $mediaFile->id,
                'title' => $title,
                'language' => $language,
                'detected_language' => $language,
                'model' => 'faster-whisper-medium',
                'status' => $status,
                'full_text' => $status === TranscriptionStatus::Completed
                    ? collect($segments)->pluck('text')->implode(' ')
                    : null,
                'started_at' => $transcriptionStartedAt,
                'completed_at' => $transcriptionCompletedAt,
                'processing_seconds' => $processingSeconds,
                'error_message' => $status === TranscriptionStatus::Failed
                    ? 'Audio format not supported for processing'
                    : null,
            ]);

            if ($status === TranscriptionStatus::Completed && $segments !== []) {
                foreach ($segments as $segmentIndex => $segment) {
                    TranscriptionSegment::create([
                        'transcription_id' => $transcription->id,
                        'segment_index' => $segmentIndex,
                        'start_seconds' => $segment['start'],
                        'end_seconds' => $segment['end'],
                        'text' => $segment['text'],
                    ]);
                }
            }

            if ($status !== TranscriptionStatus::Draft) {
                $jobStatus = match ($status) {
                    TranscriptionStatus::Completed => ProcessingStatus::Completed,
                    TranscriptionStatus::Failed => ProcessingStatus::Failed,
                    TranscriptionStatus::Transcribing => ProcessingStatus::Running,
                    default => ProcessingStatus::Queued,
                };

                $jobStartedAt = in_array($jobStatus, [ProcessingStatus::Running, ProcessingStatus::Completed, ProcessingStatus::Failed])
                    ? Carbon::instance(fake()->dateTimeBetween('-30 days', '-1 day'))
                    : null;
                $jobCompletedAt = in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                    ? Carbon::instance(fake()->dateTimeBetween($jobStartedAt ?? '-30 days', 'now'))
                    : null;

                ProcessingJob::create([
                    'transcription_id' => $transcription->id,
                    'job_uuid' => (string) Str::uuid(),
                    'worker_name' => fake()->randomElement(['worker-01', 'worker-02', 'worker-gpu-01']),
                    'stage' => ProcessingStage::Transcribe,
                    'status' => $jobStatus,
                    'progress_percentage' => $jobStatus === ProcessingStatus::Completed ? 100 : ($jobStatus === ProcessingStatus::Running ? fake()->numberBetween(20, 80) : 0),
                    'started_at' => $jobStartedAt,
                    'completed_at' => $jobCompletedAt,
                    'processing_seconds' => in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                        ? fake()->numberBetween(5, 600)
                        : null,
                    'error_message' => $jobStatus === ProcessingStatus::Failed
                        ? 'GPU memory allocation failed'
                        : null,
                    'logs' => [
                        $jobStartedAt === null ? 'Job queued; not started.' : 'Job started at '.$jobStartedAt->toDateTimeString(),
                        'Processing audio stream...',
                        'Transcription model loaded: faster-whisper-medium',
                        $jobStatus === ProcessingStatus::Completed
                            ? 'Job completed successfully.'
                            : 'Job status: '.$jobStatus->value,
                        in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                            ? 'Finished at '.$jobCompletedAt?->toDateTimeString()
                            : null,
                    ],
                ]);
            }
        }
    }

    private function seedUserData(User $user): void
    {
        $meetings = Folder::create(['user_id' => $user->id, 'name' => 'Meetings']);
        $projects = Folder::create(['user_id' => $user->id, 'name' => 'Projects']);

        $mediaFiles = [
            [
                'title' => 'Weekly Status Update',
                'original_filename' => 'weekly_status.mp3',
                'media_type' => MediaType::Audio,
                'extension' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 26214400,
                'duration_seconds' => 900,
                'status' => MediaStatus::Ready,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Completed,
                'processing_seconds' => 180,
                'folder_id' => $meetings->id,
                'segments' => [
                    ['start' => 0, 'end' => 6, 'text' => 'Let\'s start with a quick status update from each team lead.'],
                    ['start' => 6, 'end' => 14, 'text' => 'Engineering has completed two major features this sprint.'],
                    ['start' => 14, 'end' => 22, 'text' => 'We\'re on track for the release deadline next Friday.'],
                ],
            ],
            [
                'title' => 'Team Huddle',
                'original_filename' => 'team_huddle.mp4',
                'media_type' => MediaType::Video,
                'extension' => 'mp4',
                'mime_type' => 'video/mp4',
                'file_size_bytes' => 73400320,
                'duration_seconds' => 600,
                'status' => MediaStatus::Ready,
                'language' => 'en',
                'transcription_status' => TranscriptionStatus::Completed,
                'processing_seconds' => 90,
                'folder_id' => $meetings->id,
                'segments' => [
                    ['start' => 0, 'end' => 5, 'text' => 'Alright everyone, let\'s do a quick round-table update.'],
                    ['start' => 5, 'end' => 12, 'text' => 'Engineering team, where are we on the new transcription pipeline?'],
                    ['start' => 12, 'end' => 20, 'text' => 'We\'ve completed the API integration and are running beta tests now.'],
                    ['start' => 20, 'end' => 28, 'text' => 'Expected completion is end of this month.'],
                ],
            ],
            [
                'title' => 'Coaching Session',
                'original_filename' => 'coaching_session.mp3',
                'media_type' => MediaType::Audio,
                'extension' => 'mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 15728640,
                'duration_seconds' => 450,
                'status' => MediaStatus::Uploaded,
                'language' => null,
                'transcription_status' => TranscriptionStatus::Queued,
                'processing_seconds' => null,
                'folder_id' => $projects->id,
                'segments' => [],
            ],
            [
                'title' => 'Scaling System Review',
                'original_filename' => 'scaling_review.mp4',
                'media_type' => MediaType::Video,
                'extension' => 'mp4',
                'mime_type' => 'video/mp4',
                'file_size_bytes' => 104857600,
                'duration_seconds' => 2400,
                'status' => MediaStatus::Processing,
                'language' => 'ms',
                'transcription_status' => TranscriptionStatus::Transcribing,
                'processing_seconds' => null,
                'folder_id' => $projects->id,
                'segments' => [],
            ],
        ];

        $this->persistUserMedia($user, $mediaFiles);
    }

    /** @param list<array{title: string, original_filename: string, media_type: MediaType, extension: string, mime_type: string, file_size_bytes: int, duration_seconds: int, status: MediaStatus, language: string|null, transcription_status: TranscriptionStatus, processing_seconds: int|null, folder_id?: int|null, segments: list<array{start: int, end: int, text: string}>}> $mediaFiles */
    private function persistUserMedia(User $user, array $mediaFiles): void
    {
        foreach ($mediaFiles as $data) {
            $segments = $data['segments'];
            $title = $data['title'];
            $language = $data['language'];
            $status = $data['transcription_status'];
            $processingSeconds = $data['processing_seconds'];

            $mediaFile = MediaFile::create([
                'user_id' => $user->id,
                'original_filename' => $data['original_filename'],
                'media_type' => $data['media_type'],
                'extension' => $data['extension'],
                'mime_type' => $data['mime_type'],
                'file_size_bytes' => $data['file_size_bytes'],
                'duration_seconds' => $data['duration_seconds'],
                'status' => $data['status'],
                'uuid' => (string) Str::uuid(),
                'storage_filename' => Str::random(40).'.'.$data['extension'],
                'storage_path' => 'media/'.Str::random(40).'.'.$data['extension'],
                'audio_codec' => match ($data['extension']) {
                    'mp3' => 'mp3',
                    'm4a' => 'aac',
                    'wav' => 'pcm',
                    default => 'aac',
                },
                'video_codec' => $data['media_type'] === MediaType::Video ? 'h264' : null,
                'sample_rate' => match ($data['extension']) {
                    'mp3' => 44100,
                    'm4a' => 44100,
                    'wav' => 44100,
                    'mp4' => 48000,
                    'mov' => 48000,
                    default => 44100,
                },
                'channels' => 2,
                'folder_id' => $data['folder_id'] ?? null,
                'display_name' => $title,
            ]);

            $transcriptionStartedAt = in_array($status, [TranscriptionStatus::Transcribing, TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? Carbon::instance(fake()->dateTimeBetween('-30 days', '-1 day'))
                : null;
            $transcriptionCompletedAt = in_array($status, [TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? Carbon::instance(fake()->dateTimeBetween($transcriptionStartedAt ?? '-30 days', 'now'))
                : null;

            $transcription = Transcription::create([
                'user_id' => $user->id,
                'media_file_id' => $mediaFile->id,
                'title' => $title,
                'language' => $language,
                'detected_language' => $language,
                'model' => 'faster-whisper-medium',
                'status' => $status,
                'full_text' => $status === TranscriptionStatus::Completed
                    ? collect($segments)->pluck('text')->implode(' ')
                    : null,
                'started_at' => $transcriptionStartedAt,
                'completed_at' => $transcriptionCompletedAt,
                'processing_seconds' => $processingSeconds,
                'error_message' => $status === TranscriptionStatus::Failed
                    ? 'Processing interrupted'
                    : null,
            ]);

            if ($status === TranscriptionStatus::Completed && $segments !== []) {
                foreach ($segments as $segmentIndex => $segment) {
                    TranscriptionSegment::create([
                        'transcription_id' => $transcription->id,
                        'segment_index' => $segmentIndex,
                        'start_seconds' => $segment['start'],
                        'end_seconds' => $segment['end'],
                        'text' => $segment['text'],
                    ]);
                }
            }

            if ($status !== TranscriptionStatus::Draft) {
                $jobStatus = match ($status) {
                    TranscriptionStatus::Completed => ProcessingStatus::Completed,
                    TranscriptionStatus::Failed => ProcessingStatus::Failed,
                    TranscriptionStatus::Transcribing => ProcessingStatus::Running,
                    default => ProcessingStatus::Queued,
                };

                $jobStartedAt = in_array($jobStatus, [ProcessingStatus::Running, ProcessingStatus::Completed, ProcessingStatus::Failed])
                    ? Carbon::instance(fake()->dateTimeBetween('-30 days', '-1 day'))
                    : null;
                $jobCompletedAt = in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                    ? Carbon::instance(fake()->dateTimeBetween($jobStartedAt ?? '-30 days', 'now'))
                    : null;

                ProcessingJob::create([
                    'transcription_id' => $transcription->id,
                    'job_uuid' => (string) Str::uuid(),
                    'worker_name' => fake()->randomElement(['worker-01', 'worker-02']),
                    'stage' => ProcessingStage::Transcribe,
                    'status' => $jobStatus,
                    'progress_percentage' => $jobStatus === ProcessingStatus::Completed ? 100 : ($jobStatus === ProcessingStatus::Running ? fake()->numberBetween(20, 80) : 0),
                    'started_at' => $jobStartedAt,
                    'completed_at' => $jobCompletedAt,
                    'processing_seconds' => in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                        ? fake()->numberBetween(5, 300)
                        : null,
                    'error_message' => $jobStatus === ProcessingStatus::Failed
                        ? 'Processing timeout exceeded'
                        : null,
                    'logs' => [
                        $jobStartedAt === null ? 'Job queued; not started.' : 'Job started at '.$jobStartedAt->toDateTimeString(),
                        'Loading audio file...',
                        'Transcription in progress...',
                        $jobStatus === ProcessingStatus::Completed
                            ? 'Done.'
                            : 'Status: '.$jobStatus->value,
                        in_array($jobStatus, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                            ? 'Finished at '.$jobCompletedAt?->toDateTimeString()
                            : null,
                    ],
                ]);
            }
        }
    }
}
