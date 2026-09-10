<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string $original_filename
 * @property string $storage_filename
 * @property string $storage_path
 * @property MediaType $media_type
 * @property string $mime_type
 * @property string $extension
 * @property int $file_size_bytes
 * @property int|null $duration_seconds
 * @property string|null $audio_codec
 * @property string|null $video_codec
 * @property int|null $sample_rate
 * @property int|null $channels
 * @property MediaStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'folder_id',
        'display_name',
        'uuid',
        'original_filename',
        'storage_filename',
        'storage_path',
        'media_type',
        'mime_type',
        'extension',
        'file_size_bytes',
        'duration_seconds',
        'audio_codec',
        'video_codec',
        'sample_rate',
        'channels',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'media_type' => MediaType::class,
            'file_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'sample_rate' => 'integer',
            'channels' => 'integer',
            'status' => MediaStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MediaFile $mediaFile) {
            if (empty($mediaFile->uuid)) {
                $mediaFile->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(Transcription::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->display_name ?? $this->original_filename;
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;

        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $hours = intdiv($this->duration_seconds, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
