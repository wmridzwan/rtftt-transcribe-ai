<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MediaMetadataProbeService
{
    private string $ffprobePath;

    public function __construct()
    {
        $this->ffprobePath = config('media.ffprobe_path', 'ffprobe');
    }

    /**
     * Probe a media file and extract technical metadata.
     *
     * @return array{duration_seconds: ?int, audio_codec: ?string, video_codec: ?string, sample_rate: ?int, channels: ?int}
     */
    public function probe(MediaFile $mediaFile): array
    {
        $defaultResult = [
            'duration_seconds' => null,
            'audio_codec' => null,
            'video_codec' => null,
            'sample_rate' => null,
            'channels' => null,
        ];

        if (! $mediaFile->hasPhysicalFile()) {
            Log::warning('Media metadata probe skipped: file does not exist on disk.', [
                'media_file_id' => $mediaFile->id,
                'storage_path' => $mediaFile->storage_path,
            ]);

            return $defaultResult;
        }

        $absolutePath = $this->getAbsolutePath($mediaFile->storage_path);

        if ($absolutePath === null) {
            Log::warning('Media metadata probe skipped: unable to resolve absolute path.', [
                'media_file_id' => $mediaFile->id,
                'storage_path' => $mediaFile->storage_path,
            ]);

            return $defaultResult;
        }

        try {
            $probeData = $this->runFfprobe($absolutePath);

            return $this->parseProbeOutput($probeData);
        } catch (\Throwable $e) {
            Log::error('Media metadata probe failed.', [
                'media_file_id' => $mediaFile->id,
                'storage_path' => $mediaFile->storage_path,
                'exception' => $e->getMessage(),
            ]);

            return $defaultResult;
        }
    }

    /**
     * Update a MediaFile with probed metadata.
     */
    public function probeAndUpdate(MediaFile $mediaFile): MediaFile
    {
        $metadata = $this->probe($mediaFile);

        $mediaFile->update([
            'duration_seconds' => $metadata['duration_seconds'],
            'audio_codec' => $metadata['audio_codec'],
            'video_codec' => $metadata['video_codec'],
            'sample_rate' => $metadata['sample_rate'],
            'channels' => $metadata['channels'],
        ]);

        return $mediaFile->fresh();
    }

    /**
     * Check if FFprobe is available on the system.
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->ffprobePath, '-version']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function getAbsolutePath(string $storagePath): ?string
    {
        $disk = MediaFile::storage();

        try {
            $path = $disk->path($storagePath);

            return $path !== '' ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Run ffprobe and return parsed JSON output.
     *
     * @return array{format: array{duration: ?string}, streams: list<array{codec_type: string, codec_name: ?string, sample_rate: ?string, channels: ?int}>}
     */
    private function runFfprobe(string $filePath): array
    {
        $process = new Process([
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $filePath,
        ]);

        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \RuntimeException('FFprobe returned invalid JSON output.');
        }

        /** @var array{format: array{duration: ?string}, streams: list<array{codec_type: string, codec_name: ?string, sample_rate: ?string, channels: ?int}>} $decoded */
        return $decoded;
    }

    /**
     * Parse ffprobe JSON output into normalized metadata.
     *
     * @param  array{format: array{duration: ?string}, streams: list<array{codec_type: string, codec_name: ?string, sample_rate: ?string, channels: ?int}>}  $data
     * @return array{duration_seconds: ?int, audio_codec: ?string, video_codec: ?string, sample_rate: ?int, channels: ?int}
     */
    private function parseProbeOutput(array $data): array
    {
        $durationSeconds = null;
        $audioCodec = null;
        $videoCodec = null;
        $sampleRate = null;
        $channels = null;

        // Parse duration from format
        $formatDuration = $data['format']['duration'] ?? null;
        if (is_string($formatDuration) && is_numeric($formatDuration)) {
            $durationSeconds = (int) round((float) $formatDuration);
        }

        // Parse streams
        $streams = $data['streams'];
        foreach ($streams as $stream) {
            $codecType = $stream['codec_type'];
            $codecName = $stream['codec_name'] ?? null;

            if ($codecType === 'audio' && $audioCodec === null) {
                $audioCodec = $codecName;
                $sampleRate = isset($stream['sample_rate']) && is_numeric($stream['sample_rate'])
                    ? (int) $stream['sample_rate']
                    : null;
                $channels = $stream['channels'] ?? null;
            } elseif ($codecType === 'video' && $videoCodec === null) {
                $videoCodec = $codecName;
            }
        }

        return [
            'duration_seconds' => $durationSeconds,
            'audio_codec' => $audioCodec,
            'video_codec' => $videoCodec,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
        ];
    }
}
