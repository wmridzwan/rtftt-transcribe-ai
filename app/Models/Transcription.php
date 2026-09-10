<?php

namespace App\Models;

use App\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $media_file_id
 * @property string $title
 * @property string|null $language
 * @property string|null $detected_language
 * @property string|null $model
 * @property TranscriptionStatus $status
 * @property string|null $full_text
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $processing_seconds
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Transcription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_file_id',
        'title',
        'language',
        'detected_language',
        'model',
        'status',
        'full_text',
        'started_at',
        'completed_at',
        'processing_seconds',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => TranscriptionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'processing_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptionSegment::class)->orderBy('segment_index');
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function getFormattedDurationAttribute(): ?string
    {
        return $this->mediaFile?->formatted_duration;
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

    public function getRealTimeFactorAttribute(): ?float
    {
        if ($this->processing_seconds === null || $this->mediaFile?->duration_seconds === null || $this->mediaFile->duration_seconds === 0) {
            return null;
        }

        return round($this->processing_seconds / $this->mediaFile->duration_seconds, 4);
    }
}
