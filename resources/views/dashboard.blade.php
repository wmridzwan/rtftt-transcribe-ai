<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <x-page-header title="Dashboard" description="Overview of your transcription platform" />
            <flux:button :href="route('media.upload')" wire:navigate icon="arrow-up-tray">
                Upload Recording
            </flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card title="Total Recordings" :value="$totalRecordings" icon="document-text" />
            <x-kpi-card title="Completed" :value="$completed" icon="check-circle" />
            <x-kpi-card title="Processing" :value="$processing" icon="arrow-path" />
            <x-kpi-card title="Failed" :value="$failed" icon="x-circle" />
        </div>

        @if ($processingJobs !== null)
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="sm">Processing Queue</flux:heading>
                    @if (auth()->user()->isAdmin())
                        <flux:button variant="subtle" size="sm" :href="route('jobs.index')" wire:navigate>View Jobs</flux:button>
                    @endif
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="flex items-center gap-3">
                        <div class="size-2.5 rounded-full bg-yellow-400"></div>
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Queued</div>
                            <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $processingJobs['queued'] }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="size-2.5 rounded-full bg-blue-400"></div>
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Running</div>
                            <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $processingJobs['running'] }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="size-2.5 rounded-full bg-red-400"></div>
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Failed</div>
                            <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $processingJobs['failed'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="sm">Recent Transcriptions</flux:heading>
                <flux:button variant="subtle" size="sm" :href="route('transcriptions.index')" wire:navigate>View All</flux:button>
            </div>

            @if ($recentTranscriptions->isEmpty())
                <x-empty-state
                    title="No transcriptions yet"
                    description="Upload a recording to create your first transcription."
                    icon="document-text"
                    :action-text="'Upload Recording'"
                    :action-href="route('media.upload')"
                />
            @else
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($recentTranscriptions as $transcription)
                        <a href="{{ route('transcriptions.show', $transcription) }}" class="flex items-center justify-between px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset" wire:navigate>
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                                    @if ($transcription->mediaFile?->media_type === 'video')
                                        <flux:icon.play class="size-5 text-zinc-500" />
                                    @else
                                        <flux:icon.musical-note class="size-5 text-zinc-500" />
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $transcription->title }}</div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transcription->mediaFile?->formatted_duration ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transcription->language ? strtoupper($transcription->language) : '—' }}</span>
                                <x-status-badge :value="$transcription->status->value" />
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transcription->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
