<?php

namespace App\Models;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Transcription\TranscriptionFailure;
use Database\Factories\ProcessingJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $transcription_id
 * @property string $job_uuid
 * @property string|null $worker_name
 * @property ProcessingStage $stage
 * @property ProcessingStatus $status
 * @property int $progress_percentage
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $processing_seconds
 * @property string|null $error_message
 * @property TranscriptionFailure|null $failure_code
 * @property list<string|null>|null $logs
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProcessingJob extends Model
{
    /** @use HasFactory<ProcessingJobFactory> */
    use HasFactory;

    protected $fillable = [
        'transcription_id',
        'job_uuid',
        'worker_name',
        'stage',
        'status',
        'progress_percentage',
        'started_at',
        'completed_at',
        'processing_seconds',
        'error_message',
        'failure_code',
        'logs',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ProcessingStage::class,
            'status' => ProcessingStatus::class,
            'progress_percentage' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'processing_seconds' => 'integer',
            'failure_code' => TranscriptionFailure::class,
            'logs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProcessingJob $job) {
            if (empty($job->job_uuid)) {
                $job->job_uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Transcription, $this> */
    public function transcription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class);
    }

    public function getFormattedProcessingTimeAttribute(): ?string
    {
        if ($this->processing_seconds === null) {
            return null;
        }

        $hours = intdiv($this->processing_seconds, 3600);
        $minutes = intdiv($this->processing_seconds % 3600, 60);
        $seconds = $this->processing_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedLogsAttribute(): ?string
    {
        if ($this->logs === null) {
            return null;
        }

        return collect($this->logs)->implode("\n");
    }
}
