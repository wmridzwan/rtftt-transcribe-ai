<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $upload_attempt_id
 * @property string $uuid
 * @property string $original_filename
 * @property string $storage_filename
 * @property string $storage_path
 * @property string|null $checksum_sha256
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
    /** @use HasFactory<MediaFileFactory> */
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

    public static function storage(): FilesystemAdapter
    {
        self::assertPrivateStorageDisk();

        return Storage::disk((string) config('media.storage_disk'));
    }

    public static function assertPrivateStorageDisk(): void
    {
        $diskName = config('media.storage_disk');
        $diskConfig = is_string($diskName)
            ? config("filesystems.disks.{$diskName}")
            : null;
        $publicDiskConfig = config('filesystems.disks.public');

        if (! is_string($diskName) || $diskName === '' || ! is_array($diskConfig)) {
            throw new LogicException('The configured media storage disk must exist and be private.');
        }

        $usesPublicVisibility = ($diskConfig['visibility'] ?? null) === 'public';
        $usesPublicRoot = is_array($publicDiskConfig)
            && isset($diskConfig['root'], $publicDiskConfig['root'])
            && $diskConfig['root'] === $publicDiskConfig['root'];

        if ($usesPublicVisibility || $usesPublicRoot) {
            throw new LogicException('The configured media storage disk must be private.');
        }
    }

    /**
     * Assign the server-owned idempotency identity for a real upload attempt.
     *
     * @throws InvalidArgumentException
     */
    public function assignUploadAttemptId(string $attemptId): static
    {
        if (! Str::isUuid($attemptId)) {
            throw new InvalidArgumentException('The upload attempt identifier must be a UUID.');
        }

        $this->attributes['upload_attempt_id'] = $attemptId;

        return $this;
    }

    public function hasPhysicalFile(): bool
    {
        return ! empty($this->storage_path) && self::storage()->exists($this->storage_path);
    }

    /**
     * Assign a checksum computed by the server from the accepted media bytes.
     *
     * @throws InvalidArgumentException
     */
    public function assignGeneratedChecksumSha256(string $checksum): static
    {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $checksum) !== 1) {
            throw new InvalidArgumentException('The server-generated checksum must be exactly 64 lowercase hexadecimal characters.');
        }

        $this->attributes['checksum_sha256'] = $checksum;

        return $this;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Folder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /** @return HasMany<Transcription, $this> */
    public function transcriptions(): HasMany
    {
        return $this->hasMany(Transcription::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->attributes['original_filename'];
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
