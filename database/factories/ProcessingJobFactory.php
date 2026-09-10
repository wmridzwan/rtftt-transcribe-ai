<?php

namespace Database\Factories;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcessingJob>
 */
class ProcessingJobFactory extends Factory
{
    protected $model = ProcessingJob::class;

    public function definition(): array
    {
        $status = fake()->randomElement(ProcessingStatus::cases());
        $startedAt = in_array($status, [ProcessingStatus::Running, ProcessingStatus::Completed, ProcessingStatus::Failed])
            ? fake()->dateTimeBetween('-30 days', '-1 day')
            : null;
        $completedAt = in_array($status, [ProcessingStatus::Completed, ProcessingStatus::Failed])
            ? fake()->dateTimeBetween($startedAt ?? '-30 days', 'now')
            : null;

        return [
            'transcription_id' => Transcription::factory(),
            'job_uuid' => (string) Str::uuid(),
            'worker_name' => fake()->randomElement(['worker-01', 'worker-02', 'worker-gpu-01', null]),
            'stage' => fake()->randomElement(ProcessingStage::cases()),
            'status' => $status,
            'progress_percentage' => $status === ProcessingStatus::Completed ? 100 : fake()->numberBetween(0, 99),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'processing_seconds' => in_array($status, [ProcessingStatus::Completed, ProcessingStatus::Failed])
                ? fake()->numberBetween(5, 1800)
                : null,
            'error_message' => $status === ProcessingStatus::Failed
                ? fake()->sentence()
                : null,
            'logs' => fake()->optional()->passthrough([
                'Job started at '.($startedAt instanceof Carbon ? $startedAt->toDateTimeString() : now()->toDateTimeString()),
                fake()->sentence(),
                fake()->sentence(),
                $status === ProcessingStatus::Completed
                    ? 'Job completed successfully.'
                    : 'Job status: '.$status->value,
            ]),
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = fake()->dateTimeBetween('-30 days', '-1 day');
            $completedAt = fake()->dateTimeBetween($startedAt, 'now');

            return [
                'status' => ProcessingStatus::Completed,
                'progress_percentage' => 100,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'processing_seconds' => fake()->numberBetween(5, 1800),
            ];
        });
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessingStatus::Running,
            'progress_percentage' => fake()->numberBetween(1, 99),
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = fake()->dateTimeBetween('-30 days', '-1 day');
            $completedAt = fake()->dateTimeBetween($startedAt, 'now');

            return [
                'status' => ProcessingStatus::Failed,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'error_message' => fake()->sentence(),
            ];
        });
    }
}
