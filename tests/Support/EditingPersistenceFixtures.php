<?php

namespace Tests\Support;

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\User;

/**
 * Shared P6-002 revision-persistence fixtures.
 */
final class EditingPersistenceFixtures
{
    /**
     * A completed transcription (owner defaults to a fresh user) with machine
     * segments copied from a simple two-segment source.
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public static function completedTranscription(?User $user = null, array $segments = []): Transcription
    {
        $user ??= User::factory()->create();

        $transcription = Transcription::factory()->completed()->for($user)->create();

        $segments = $segments === [] ? self::machineSegments() : $segments;

        foreach ($segments as $segment) {
            TranscriptionSegment::factory()->for($transcription)->create($segment);
        }

        return $transcription->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function machineSegments(): array
    {
        return [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.5, 'text' => 'Hai semua', 'language' => 'ms'],
            ['segment_index' => 1, 'start_seconds' => 4.5, 'end_seconds' => 9.25, 'text' => 'Welcome 欢迎', 'language' => 'en'],
        ];
    }
}
