<?php

namespace Database\Factories;

use App\Models\Translation;
use App\Models\TranslationSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationSegment>
 */
class TranslationSegmentFactory extends Factory
{
    protected $model = TranslationSegment::class;

    public function definition(): array
    {
        return [
            'translation_id' => Translation::factory(),
            'segment_index' => fake()->unique()->numberBetween(0, 500),
            'start_seconds' => 0,
            'end_seconds' => fake()->numberBetween(5, 30),
            'text' => fake()->sentence(),
            'source_language' => fake()->randomElement(['ms', 'en', 'zh', 'ta', 'und']),
        ];
    }
}
