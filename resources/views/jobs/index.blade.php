<x-layouts::app :title="__('Processing Jobs')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <x-page-header title="Processing Jobs" description="Monitor transcription processing pipeline" />

        <form method="GET" action="{{ route('jobs.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <flux:select name="status" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\ProcessingStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-40">
                <flux:select name="stage" placeholder="All Stages">
                    <option value="">All Stages</option>
                    @foreach (\App\Enums\ProcessingStage::cases() as $stage)
                        <option value="{{ $stage->value }}" {{ request('stage') === $stage->value ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($stage->value)) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit" variant="subtle">Apply Filters</flux:button>
        </form>

        @if ($jobs->isEmpty())
            <x-empty-state
                title="No processing jobs found"
                description="Processing jobs will appear here when transcriptions are being processed."
                icon="cog-6-tooth"
            />
        @else
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Transcription</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Stage</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Worker</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Progress</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Processing Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Job ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($jobs as $job)
                            <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset" onclick="window.location='{{ route('jobs.show', $job) }}'" tabindex="0" onkeydown="if(event.key==='Enter')window.location='{{ route('jobs.show', $job) }}'">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $job->transcription->title ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ str_replace('_', ' ', ucfirst($job->stage->value)) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $job->worker_name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-full rounded-full bg-indigo-600" style="width: {{ $job->progress_percentage }}%"></div>
                                        </div>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $job->progress_percentage }}%</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-status-badge :value="$job->status->value" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $job->formatted_processing_time ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="font-mono text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ Str::limit($job->job_uuid, 8) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $jobs->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
