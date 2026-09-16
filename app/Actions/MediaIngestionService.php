<?php

namespace App\Actions;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaIngestionService
{
    public function __construct(private readonly DatabaseManager $database) {}

    public static function validateByteSize(int $size): void
    {
        $maximum = (int) config('media.max_upload_bytes');

        if ($size < 0 || $size > $maximum) {
            throw ValidationException::withMessages([
                'media_file' => sprintf('The media file may not be larger than %s bytes.', number_format($maximum)),
            ]);
        }
    }

    /**
     * @return array{original_filename: string, extension: string, mime_type: string, size: int, media_type: MediaType, display_name: string}
     */
    public function inspectUploadedFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'media_file' => 'The upload could not be received. Please try again.',
            ]);
        }

        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = strtolower((string) $file->getMimeType());
        $size = $file->getSize();

        if ($originalFilename === '' || mb_strlen($originalFilename) > 255) {
            throw ValidationException::withMessages([
                'media_file' => 'The original filename must be between 1 and 255 characters.',
            ]);
        }

        if ($size === false) {
            throw ValidationException::withMessages([
                'media_file' => 'The upload size could not be determined.',
            ]);
        }

        self::validateByteSize($size);

        $mediaType = $this->resolveMediaType($extension, $mimeType);

        if ($mediaType === null) {
            throw ValidationException::withMessages([
                'media_file' => 'The selected file type is not supported or does not match its detected MIME type.',
            ]);
        }

        return [
            'original_filename' => $originalFilename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
            'media_type' => $mediaType,
            'display_name' => pathinfo($originalFilename, PATHINFO_FILENAME),
        ];
    }

    public function ingest(User $owner, UploadedFile $file, string $attemptId, ?int $folderId = null): MediaFile
    {
        $existing = $this->findByAttempt($owner, $attemptId);

        if ($existing !== null) {
            return $existing;
        }

        $metadata = $this->inspectUploadedFile($file);
        $storage = MediaFile::storage();
        $stagingPath = null;
        $durablePath = null;
        $committed = false;

        try {
            $stagingPath = $this->stage($storage, $file, $owner, $attemptId, $metadata['extension']);
            $stagedSize = $storage->size($stagingPath);
            self::validateByteSize((int) $stagedSize);

            if ($stagedSize !== $metadata['size']) {
                throw ValidationException::withMessages([
                    'media_file' => 'The received file size did not match the upload metadata.',
                ]);
            }

            $checksum = $this->checksum($storage, $stagingPath);
            $mediaUuid = (string) Str::uuid();
            $storageFilename = Str::random(40).'.'.$metadata['extension'];
            $durablePath = sprintf(
                '%s/%s/%s',
                trim((string) config('media.storage_directory'), '/'),
                $mediaUuid,
                $storageFilename,
            );

            $this->copy($storage, $stagingPath, $durablePath);

            try {
                $mediaFile = $this->database->transaction(function () use ($owner, $folderId, $attemptId, $metadata, $checksum, $mediaUuid, $storageFilename, $durablePath): MediaFile {
                    $mediaFile = new MediaFile([
                        'user_id' => $owner->id,
                        'folder_id' => $folderId,
                        'uuid' => $mediaUuid,
                        'original_filename' => $metadata['original_filename'],
                        'display_name' => $metadata['display_name'],
                        'storage_filename' => $storageFilename,
                        'storage_path' => $durablePath,
                        'media_type' => $metadata['media_type'],
                        'mime_type' => $metadata['mime_type'],
                        'extension' => $metadata['extension'],
                        'file_size_bytes' => $metadata['size'],
                        'duration_seconds' => null,
                        'audio_codec' => null,
                        'video_codec' => null,
                        'sample_rate' => null,
                        'channels' => null,
                        'status' => MediaStatus::Uploaded,
                    ]);

                    $mediaFile->assignUploadAttemptId($attemptId)
                        ->assignGeneratedChecksumSha256($checksum)
                        ->save();

                    return $mediaFile;
                });
            } catch (Throwable $exception) {
                $existing = $this->findByAttempt($owner, $attemptId);

                if ($existing !== null) {
                    $this->deleteOrLog($storage, $durablePath, 'concurrent upload-attempt retry');
                    $this->deleteStaging($storage, $stagingPath);
                    $committed = true;

                    return $existing;
                }

                throw $exception;
            }

            $committed = true;
            $this->deleteStaging($storage, $stagingPath);

            return $mediaFile;
        } catch (Throwable $exception) {
            if (! $committed) {
                $this->deleteStaging($storage, $stagingPath);
                $this->deleteOrLog($storage, $durablePath, 'failed upload attempt');
            }

            throw $exception;
        }
    }

    public function findByAttempt(User $owner, string $attemptId): ?MediaFile
    {
        return MediaFile::query()
            ->where('user_id', $owner->id)
            ->where('upload_attempt_id', $attemptId)
            ->first();
    }

    public function findActiveClaim(User $owner, string $attemptId): ?StagingClaim
    {
        return StagingClaim::query()
            ->where('user_id', $owner->id)
            ->where('upload_attempt_id', $attemptId)
            ->where('held_by', 'upload')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function releaseClaim(User $owner, string $attemptId): void
    {
        StagingClaim::where('user_id', $owner->id)
            ->where('upload_attempt_id', $attemptId)
            ->delete();
    }

    private function resolveMediaType(string $extension, string $mimeType): ?MediaType
    {
        foreach (config('media.supported_media', []) as $type => $extensions) {
            foreach ($extensions as $acceptedExtension => $acceptedMimes) {
                if ($extension === $acceptedExtension && in_array($mimeType, $acceptedMimes, true)) {
                    return MediaType::from((string) $type);
                }
            }
        }

        return null;
    }

    /**
     * Stage the uploaded file and establish the upload claim atomically.
     *
     * Uses insertOrIgnore for the initial claim (atomic on SQLite via unique
     * index), then conditional UPDATE for same-attempt retry renewal. Returns
     * a controlled retryable failure when cleanup has already claimed the
     * attempt.
     *
     * @throws ValidationException when cleanup has claimed this attempt (retryable)
     */
    private function stage(FilesystemAdapter $storage, UploadedFile $file, User $owner, string $attemptId, string $extension): string
    {
        $directory = sprintf(
            '%s/%s/%s',
            trim((string) config('media.staging_directory'), '/'),
            $owner->id,
            $attemptId,
        );
        $filename = (string) Str::uuid().'.'.$extension;
        $path = $directory.'/'.$filename;

        $retentionHours = (int) config('media.temporary_retention_hours', 24);

        // Step 1: Insert claim atomically via insertOrIgnore (no read-then-write).
        $now = now();
        $inserted = DB::table('staging_claims')->insertOrIgnore([
            'user_id' => $owner->id,
            'upload_attempt_id' => $attemptId,
            'staging_path' => $path,
            'held_by' => 'upload',
            'claimed_at' => $now,
            'expires_at' => $now->copy()->addHours($retentionHours),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            // A row already exists. Check if cleanup owns it.
            $existingClaim = StagingClaim::where('user_id', $owner->id)
                ->where('upload_attempt_id', $attemptId)
                ->first();

            if ($existingClaim !== null && $existingClaim->held_by === 'cleanup') {
                // Cleanup has claimed this attempt for deletion. Do NOT write
                // into the staging directory. Return a controlled retryable
                // failure so the client can retry with a new attempt ID.
                throw ValidationException::withMessages([
                    'media_file' => 'The upload attempt is currently being cleaned up. Please retry with a new upload.',
                ]);
            }

            // held_by is 'upload' — this is a normal same-attempt retry. Renew
            // the claim via a single guarded UPDATE (no read-then-write). If
            // the affected-row count is 0, cleanup won the race in the window
            // between the SELECT above and this UPDATE — treat as lost race.
            $renewed = DB::table('staging_claims')
                ->where('user_id', $owner->id)
                ->where('upload_attempt_id', $attemptId)
                ->where('held_by', 'upload')
                ->update([
                    'expires_at' => $now->copy()->addHours($retentionHours),
                    'updated_at' => $now,
                ]);

            if ($renewed === 0) {
                throw ValidationException::withMessages([
                    'media_file' => 'The upload attempt is currently being cleaned up. Please retry with a new upload.',
                ]);
            }
        }

        try {
            if ($storage->putFileAs($directory, $file, $filename) === false) {
                throw new RuntimeException('The upload could not be written to temporary staging storage.');
            }
        } catch (Throwable $exception) {
            StagingClaim::where('user_id', $owner->id)
                ->where('upload_attempt_id', $attemptId)
                ->delete();

            throw $exception;
        }

        return $path;
    }

    private function checksum(FilesystemAdapter $storage, string $path): string
    {
        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The staged upload could not be read for checksum generation.');
        }

        try {
            $context = hash_init((string) config('media.checksum.algorithm', 'sha256'));

            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('The staged upload could not be read for checksum generation.');
                }

                hash_update($context, $chunk);
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function copy(FilesystemAdapter $storage, string $source, string $destination): void
    {
        $stream = $storage->readStream($source);

        if (! is_resource($stream)) {
            throw new RuntimeException('The staged upload could not be read for durable storage promotion.');
        }

        try {
            if (! $storage->put($destination, $stream)) {
                throw new RuntimeException('The upload could not be promoted to durable private storage.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function deleteStaging(FilesystemAdapter $storage, ?string $path): void
    {
        if ($path !== null && $storage->exists($path)) {
            $storage->delete($path);
        }

        if ($path !== null) {
            StagingClaim::where('staging_path', $path)->delete();
        }
    }

    private function deleteOrLog(FilesystemAdapter $storage, ?string $path, string $reason): void
    {
        if ($path === null || ! $storage->exists($path)) {
            return;
        }

        try {
            if (! $storage->delete($path)) {
                throw new RuntimeException('The storage adapter did not confirm deletion.');
            }
        } catch (Throwable $exception) {
            Log::error('Media ingestion left an orphan candidate for reconciliation.', [
                'path' => $path,
                'reason' => $reason,
                'exception' => $exception,
            ]);
        }
    }
}
