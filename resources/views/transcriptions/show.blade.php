<x-layouts::app :title="$transcription->title">
    @php
        $hasFile = $transcription->mediaFile
            && $transcription->mediaFile->storage_path
            && in_array($transcription->mediaFile->status->value, ['uploaded', 'ready']);
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6" x-data="{ activeTab: 'transcript', showRenameModal: false }">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('transcriptions.index') }}" icon="arrow-left" variant="subtle" size="sm" wire:navigate>All Transcriptions</flux:button>
            <x-page-header :title="$transcription->title" :description="'Transcription details and transcript'" />

            <div class="ml-auto flex items-center gap-2">
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" variant="primary" size="sm" :disabled="!$hasFile">
                        Export
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.txt', $transcription)" :disabled="!$hasFile" wire:navigate>
                            Export as TXT
                        </flux:menu.item>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.srt', $transcription)" :disabled="!$hasFile" wire:navigate>
                            Export as SRT
                        </flux:menu.item>
                        <flux:menu.item icon="play" :href="route('transcriptions.export.vtt', $transcription)" :disabled="!$hasFile" wire:navigate>
                            Export as VTT
                        </flux:menu.item>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.docx', $transcription)" :disabled="!$hasFile" wire:navigate>
                            Export as DOCX
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:button icon="pencil-square" variant="subtle" size="sm" x-on:click="showRenameModal = true">
                    Rename
                </flux:button>

                <form method="POST" action="{{ route('transcriptions.destroy', $transcription) }}" class="inline" x-on:submit.prevent="if (confirm('Are you sure you want to delete this transcription? This action cannot be undone.')) { $el.submit(); }">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" icon="trash" variant="danger" size="sm">
                        Delete
                    </flux:button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="border-b border-zinc-200 dark:border-zinc-700">
                        <nav class="flex gap-4 px-4" aria-label="Tabs">
                            <button
                                @click="activeTab = 'transcript'"
                                :class="activeTab === 'transcript' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                            >Transcript</button>
                            <button
                                @click="activeTab = 'details'"
                                :class="activeTab === 'details' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                            >Details</button>
                            <button
                                @click="activeTab = 'processing'"
                                :class="activeTab === 'processing' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                            >Processing</button>
                        </nav>
                    </div>

                    <div x-show="activeTab === 'transcript'" class="p-4">
                        @if ($transcription->segments->isEmpty())
                            <x-empty-state
                                title="No transcript segments"
                                description="This transcription does not have any segments yet."
                                icon="document-text"
                            />
                        @else
                            <div class="space-y-4">
                                @foreach ($transcription->segments as $segment)
                                    <div class="flex gap-4">
                                        <div class="flex-shrink-0 w-16 text-right">
                                            <span class="inline-block rounded bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                                {{ $segment->formatted_start }}
                                            </span>
                                        </div>
                                        <div class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ $segment->text }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div x-show="activeTab === 'details'" class="p-4">
                        <flux:heading size="sm" class="mb-4">Media Information</flux:heading>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            <x-metadata-row label="Original Filename" :value="$transcription->mediaFile->original_filename" />
                            <x-metadata-row label="Media Type" :value="$transcription->mediaFile->media_type->value" />
                            <x-metadata-row label="MIME Type" :value="$transcription->mediaFile->mime_type" />
                            <x-metadata-row label="Extension" :value="$transcription->mediaFile->extension" />
                            <x-metadata-row label="File Size" :value="$transcription->mediaFile->formatted_file_size" />
                            <x-metadata-row label="Duration" :value="$transcription->mediaFile->formatted_duration ?? '—'" />
                            <x-metadata-row label="Audio Codec" :value="$transcription->mediaFile->audio_codec ?? '—'" />
                            <x-metadata-row label="Video Codec" :value="$transcription->mediaFile->video_codec ?? '—'" />
                            <x-metadata-row label="Sample Rate" :value="$transcription->mediaFile->sample_rate ? $transcription->mediaFile->sample_rate . ' Hz' : '—'" />
                            <x-metadata-row label="Channels" :value="$transcription->mediaFile->channels ?? '—'" />
                        </div>

                        <flux:heading size="sm" class="mb-4 mt-6">Transcription Information</flux:heading>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            <x-metadata-row label="Language" :value="$transcription->language ? strtoupper($transcription->language) : '—'" />
                            <x-metadata-row label="Detected Language" :value="$transcription->detected_language ? strtoupper($transcription->detected_language) : '—'" />
                            <x-metadata-row label="Model" :value="$transcription->model ?? '—'" />
                            <x-metadata-row label="Status" :value="ucfirst($transcription->status->value)" />
                            <x-metadata-row label="Created" :value="$transcription->created_at->format('M j, Y g:i A')" />
                        </div>
                    </div>

                    <div x-show="activeTab === 'processing'" class="p-4">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">Status</span>
                                <x-status-badge :value="$transcription->status->value" />
                            </div>
                            <x-metadata-row label="Worker" :value="$transcription->processingJobs->first()?->worker_name ?? '—'" />
                            <x-metadata-row label="Model" :value="$transcription->model ?? '—'" />
                            <x-metadata-row label="Processing Time" :value="$transcription->formatted_processing_time ?? '—'" />
                            <x-metadata-row label="Real-Time Factor" :value="$transcription->real_time_factor !== null ? number_format($transcription->real_time_factor, 2) . '×' : '—'" />
                        </div>

                        @if ($transcription->processingJobs->isNotEmpty())
                            <flux:heading size="sm" class="mb-3 mt-6">Processing Jobs</flux:heading>
                            <div class="space-y-3">
                                @foreach ($transcription->processingJobs as $job)
                                    <a href="{{ route('jobs.show', $job) }}" class="block rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50" wire:navigate>
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ Str::limit($job->job_uuid, 8) }}</span>
                                            <x-status-badge :value="$job->status->value" />
                                        </div>
                                        <div class="mt-2 flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span>Stage: {{ $job->stage->value }}</span>
                                            <span>Progress: {{ $job->progress_percentage }}%</span>
                                        </div>
                                        @if ($job->progress_percentage > 0 && $job->progress_percentage < 100)
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                                <div class="h-full rounded-full bg-indigo-600" style="width: {{ $job->progress_percentage }}%"></div>
                                            </div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-6 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="sm" class="mb-4">Overview</flux:heading>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">Status</span>
                            <x-status-badge :value="$transcription->status->value" />
                        </div>
                        <x-metadata-row label="Duration" :value="$transcription->formatted_duration ?? '—'" />
                        <x-metadata-row label="Language" :value="$transcription->language ? strtoupper($transcription->language) : '—'" />
                        <x-metadata-row label="Created" :value="$transcription->created_at->diffForHumans()" />
                        <x-metadata-row label="Segments" :value="$transcription->segments->count()" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="rename-transcription" :show="$errors->isNotEmpty()" x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === 'rename-transcription') { open = true }" x-bind:show="open || showRenameModal" x-effect="showRenameModal = open" focusable class="max-w-lg">
        <form method="POST" action="{{ route('transcriptions.rename', $transcription) }}" class="space-y-6">
            @csrf
            @method('PATCH')
            <div>
                <flux:heading size="lg">Rename Transcription</flux:heading>
                <flux:text class="mt-2">Enter a new title for this transcription.</flux:text>
            </div>

            <flux:input
                name="title"
                label="Title"
                :value="$transcription->title"
                required
                maxlength="255"
            />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="ghost" x-on:click="showRenameModal = false">Cancel</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">Rename</flux:button>
            </div>
        </form>
    </flux:modal>
</x-layouts::app>
