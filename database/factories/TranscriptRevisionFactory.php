<?php

namespace Database\Factories;

use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptRevisionModel>
 */
class TranscriptRevisionFactory extends Factory
{
    protected $model = TranscriptRevisionModel::class;

    public function definition(): array
    {
        return [
            'transcription_id' => Transcription::factory(),
            'version' => 1,
            'parent_revision_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
