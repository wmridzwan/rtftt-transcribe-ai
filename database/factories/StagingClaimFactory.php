<?php

namespace Database\Factories;

use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StagingClaim>
 */
class StagingClaimFactory extends Factory
{
    protected $model = StagingClaim::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'upload_attempt_id' => (string) Str::uuid(),
            'staging_path' => 'media/.staging/1/'.Str::uuid().'/'.Str::random(40).'.mp3',
            'claimed_at' => now(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
