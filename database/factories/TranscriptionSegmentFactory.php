<?php

namespace Database\Factories;

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptionSegment>
 */
class TranscriptionSegmentFactory extends Factory
{
    protected $model = TranscriptionSegment::class;

    public function definition(): array
    {
        return [
            'transcription_id' => Transcription::factory(),
            'segment_index' => fake()->unique()->numberBetween(0, 500),
            'start_seconds' => 0,
            'end_seconds' => fake()->numberBetween(5, 30),
            'text' => fake()->sentence(),
            'language' => fake()->randomElement(['ms', 'en', 'zh', 'ta', 'und']),
        ];
    }
}
