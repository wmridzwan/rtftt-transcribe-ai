<x-layouts::app :title="'Processing Job'">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('jobs.index') }}" icon="arrow-left" variant="subtle" size="sm" wire:navigate>Back to Jobs</flux:button>
            <x-page-header title="Processing Job Details" :description="'Job ' . Str::limit($job->job_uuid, 12)" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="sm" class="mb-4">Job Information</flux:heading>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <x-metadata-row label="Job UUID" :value="$job->job_uuid" />
                    <div class="flex items-center justify-between py-2">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Transcription</flux:text>
                        @if ($job->transcription)
                            <a href="{{ route('transcriptions.show', $job->transcription) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ $job->transcription->title }}</a>
                        @else
                            <flux:text class="text-sm font-medium text-zinc-900 dark:text-white">—</flux:text>
                        @endif
                    </div>
                    <x-metadata-row label="Stage" :value="str_replace('_', ' ', ucfirst($job->stage->value))" />
                    <div class="flex items-center justify-between py-2">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Status</flux:text>
                        <x-status-badge :value="$job->status->value" />
                    </div>
                    <x-metadata-row label="Worker" :value="$job->worker_name ?? '—'" />
                    <x-metadata-row label="Progress" :value="$job->progress_percentage . '%'" />
                    <x-metadata-row label="Started" :value="$job->started_at?->format('M j, Y g:i A') ?? '—'" />
                    <x-metadata-row label="Completed" :value="$job->completed_at?->format('M j, Y g:i A') ?? '—'" />
                    <x-metadata-row label="Processing Time" :value="$job->formatted_processing_time ?? '—'" />
                </div>

                @if ($job->error_message)
                    <div class="mt-4 rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
                        <div class="text-sm font-medium text-red-800 dark:text-red-400">Error</div>
                        <div class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $job->error_message }}</div>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="sm" class="mb-4">Logs</flux:heading>
                @if ($job->logs)
                    <div class="max-h-96 overflow-auto rounded-lg bg-zinc-900 p-4 font-mono text-xs text-zinc-300 dark:bg-zinc-950">
                        @foreach ($job->logs as $log)
                            <div>{{ $log }}</div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No logs available for this job.</p>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
