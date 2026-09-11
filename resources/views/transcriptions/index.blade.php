<x-layouts::app :title="__('Transcriptions')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <x-page-header title="Transcriptions" description="Manage your audio and video transcriptions" />
            <flux:button :href="route('transcriptions.create')" wire:navigate icon="arrow-up-tray">
                Upload Recording
            </flux:button>
        </div>

        <form method="GET" action="{{ route('transcriptions.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input name="search" placeholder="Search transcriptions..." :value="request('search')" />
            </div>
            <div class="w-40">
                <flux:select name="status" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\TranscriptionStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-40">
                <flux:select name="language" placeholder="All Languages">
                    <option value="">All Languages</option>
                    <option value="en" {{ request('language') === 'en' ? 'selected' : '' }}>English</option>
                    <option value="ms" {{ request('language') === 'ms' ? 'selected' : '' }}>Bahasa Melayu</option>
                    <option value="zh" {{ request('language') === 'zh' ? 'selected' : '' }}>Chinese</option>
                </flux:select>
            </div>
            <flux:button type="submit" variant="subtle">Apply Filters</flux:button>
        </form>

        @if ($transcriptions->isEmpty())
            <x-empty-state
                title="No transcriptions found"
                description="Upload a recording to create your first transcription."
                icon="document-text"
                :action-text="'Upload Recording'"
                :action-href="route('transcriptions.create')"
            />
        @else
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Duration</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Language</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Model</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Created</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($transcriptions as $transcription)
                            @php
                                $canExport = $transcription->status === \App\Enums\TranscriptionStatus::Completed;
                            @endphp
                            <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('transcriptions.show', $transcription) }}" class="font-medium text-zinc-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400" wire:navigate>
                                        {{ $transcription->title }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $transcription->formatted_duration ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $transcription->language ? strtoupper($transcription->language) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $transcription->model ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-status-badge :value="$transcription->status->value" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $transcription->created_at->diffForHumans() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <form id="delete-form-{{ $transcription->id }}" method="POST" action="{{ route('transcriptions.destroy', $transcription) }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="subtle" square icon="ellipsis-vertical" size="sm" />
                                        <flux:menu>
                                            <flux:menu.item icon="eye" :href="route('transcriptions.show', $transcription)" wire:navigate>
                                                View
                                            </flux:menu.item>
                                            <flux:menu.submenu heading="Export" :disabled="!$canExport">
                                                <flux:menu.item icon="document-text" :href="route('transcriptions.export.txt', $transcription)" :disabled="!$canExport">
                                                    TXT
                                                </flux:menu.item>
                                                <flux:menu.item icon="document-text" :href="route('transcriptions.export.srt', $transcription)" :disabled="!$canExport">
                                                    SRT
                                                </flux:menu.item>
                                                <flux:menu.item icon="play" :href="route('transcriptions.export.vtt', $transcription)" :disabled="!$canExport">
                                                    VTT
                                                </flux:menu.item>
                                                <flux:menu.item icon="document-text" :href="route('transcriptions.export.docx', $transcription)" :disabled="!$canExport">
                                                    DOCX
                                                </flux:menu.item>
                                            </flux:menu.submenu>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="pencil-square" :href="route('transcriptions.show', ['transcription' => $transcription, 'rename' => 1])" wire:navigate keep-open>
                                                Rename
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item variant="danger" icon="trash" x-on:click="if (confirm('Are you sure you want to delete \'{{ addslashes($transcription->title) }}\'? This action cannot be undone.')) { document.getElementById('delete-form-{{ $transcription->id }}').submit(); }">
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $transcriptions->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
