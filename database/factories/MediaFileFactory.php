<?php

namespace Database\Factories;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    public function definition(): array
    {
        $type = fake()->randomElement(MediaType::cases());
        $extension = $type === MediaType::Audio
            ? fake()->randomElement(['mp3', 'm4a', 'wav'])
            : fake()->randomElement(['mp4', 'mov']);

        $filename = fake()->words(2, true).'.'.$extension;

        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'original_filename' => $filename,
            'storage_filename' => Str::random(40).'.'.$extension,
            'storage_path' => 'media/'.Str::random(40).'.'.$extension,
            'media_type' => $type,
            'mime_type' => $type === MediaType::Audio
                ? 'audio/'.$extension
                : 'video/'.$extension,
            'extension' => $extension,
            'file_size_bytes' => fake()->numberBetween(1048576, 104857600),
            'duration_seconds' => fake()->numberBetween(30, 7200),
            'audio_codec' => match ($extension) {
                'mp3' => 'mp3',
                'm4a' => 'aac',
                'wav' => 'pcm',
                default => 'aac',
            },
            'video_codec' => $type === MediaType::Video ? 'h264' : null,
            'sample_rate' => match ($extension) {
                'mp3' => 44100,
                'm4a' => 44100,
                'wav' => 44100,
                'mp4' => 48000,
                'mov' => 48000,
                default => 44100,
            },
            'channels' => 2,
            'status' => MediaStatus::Ready,
        ];
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::Audio,
            'extension' => fake()->randomElement(['mp3', 'm4a', 'wav']),
            'mime_type' => 'audio/'.fake()->randomElement(['mpeg', 'mp4', 'wav']),
            'video_codec' => null,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::Video,
            'extension' => fake()->randomElement(['mp4', 'mov']),
            'mime_type' => 'video/'.fake()->randomElement(['mp4', 'quicktime']),
            'video_codec' => 'h264',
        ]);
    }

    public function uploaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Uploaded,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Processing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::Failed,
        ]);
    }
}
