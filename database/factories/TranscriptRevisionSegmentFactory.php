<?php

namespace Database\Factories;

use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptRevisionSegment>
 */
class TranscriptRevisionSegmentFactory extends Factory
{
    protected $model = TranscriptRevisionSegment::class;

    public function definition(): array
    {
        return [
            'revision_id' => TranscriptRevisionModel::factory(),
            'segment_key' => 'seg-'.fake()->unique()->numberBetween(0, 1000000),
            'position' => fake()->unique()->numberBetween(0, 1000000),
            'start_seconds' => 0,
            'end_seconds' => fake()->numberBetween(1, 30),
            'text' => fake()->sentence(),
            'language' => fake()->randomElement(['ms', 'en', 'zh', 'ta', 'und']),
        ];
    }
}
