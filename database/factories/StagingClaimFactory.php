<?php

namespace Database\Factories;

use App\Models\StagingClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
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
            'held_by' => 'upload',
            'cleanup_claimed_at' => null,
            'claimed_at' => now(),
            'expires_at' => now()->addHours(24),
        ];
    }

    public function heldByUpload(): static
    {
        return $this->state(fn () => [
            'held_by' => 'upload',
            'cleanup_claimed_at' => null,
        ]);
    }

    public function heldByCleanup(?Carbon $claimedAt = null): static
    {
        return $this->state(fn () => [
            'held_by' => 'cleanup',
            'cleanup_claimed_at' => $claimedAt ?? now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
