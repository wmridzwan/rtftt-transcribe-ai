<?php

namespace Database\Factories;

use App\Models\Transcription;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'transcription_id' => Transcription::factory(),
            'target_language' => fake()->randomElement(['ms', 'en', 'zh', 'ta']),
            'status' => 'pending',
            'source_language' => fake()->randomElement(['ms', 'en', 'zh', 'ta', 'und']),
            'provider' => 'self-hosted',
            'model' => 'translation-test',
            'full_text' => fake()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'failure_code' => 'PROVIDER_FAILED',
            'completed_at' => now(),
        ]);
    }
}
