<?php

namespace Database\Factories;

use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcription>
 */
class TranscriptionFactory extends Factory
{
    protected $model = Transcription::class;

    public function definition(): array
    {
        $status = fake()->randomElement(TranscriptionStatus::cases());

        return [
            'user_id' => User::factory(),
            'media_file_id' => MediaFile::factory(),
            'title' => fake()->sentence(3),
            'language' => fake()->randomElement(['en', 'ms', 'zh', null]),
            'detected_language' => fake()->randomElement(['en', 'ms', 'zh', null]),
            'speech_detected' => $status === TranscriptionStatus::Completed ? true : null,
            'model' => fake()->randomElement(['faster-whisper-medium', 'faster-whisper-large-v3', null]),
            'status' => $status,
            'full_text' => $status === TranscriptionStatus::Completed ? fake()->paragraphs(3, true) : null,
            'started_at' => in_array($status, [TranscriptionStatus::Transcribing, TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? fake()->dateTimeBetween('-30 days', 'now')
                : null,
            'completed_at' => in_array($status, [TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? fake()->dateTimeBetween('-30 days', 'now')
                : null,
            'processing_seconds' => in_array($status, [TranscriptionStatus::Completed, TranscriptionStatus::Failed])
                ? fake()->numberBetween(10, 3600)
                : null,
            'error_message' => $status === TranscriptionStatus::Failed
                ? fake()->sentence()
                : null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Completed,
            'full_text' => fake()->paragraphs(3, true),
            'speech_detected' => true,
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'completed_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'processing_seconds' => fake()->numberBetween(10, 3600),
        ]);
    }

    public function transcribing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Transcribing,
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Queued,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Failed,
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'completed_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'error_message' => fake()->sentence(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Draft,
        ]);
    }
}
