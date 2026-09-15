<x-layouts::app :title="__('Media Library')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-start justify-between gap-4">
            <x-page-header title="Media Library" description="Browse your uploaded audio and video files" />
            <flux:button :href="route('media.upload')" wire:navigate icon="arrow-up-tray">Upload Media</flux:button>
        </div>

        <form method="GET" action="{{ route('media.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input name="search" placeholder="Search files..." :value="request('search')" />
            </div>
            <div class="w-40">
                <flux:select name="type" placeholder="All Types">
                    <option value="">All Types</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>Audio</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>Video</option>
                </flux:select>
            </div>
            <div class="w-48">
                <flux:select name="folder" placeholder="All Folders">
                    <option value="">All Folders</option>
                    @foreach ($folders as $folder)
                        <option value="{{ $folder->id }}" {{ request('folder') == $folder->id ? 'selected' : '' }}>{{ $folder->name }} ({{ $folder->media_files_count }})</option>
                    @endforeach
                    <option value="none" {{ request('folder') === 'none' ? 'selected' : '' }}>No Folder</option>
                </flux:select>
            </div>
            <flux:button type="submit" variant="subtle">Apply Filters</flux:button>
        </form>

        @if ($mediaFiles->isEmpty())
            <x-empty-state
                title="No media files found"
                description="Upload a recording to add files to your media library."
                icon="folder"
                :action-text="'Upload Recording'"
                :action-href="route('media.upload')"
            />
        @else
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Duration</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Folder</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Uploaded</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($mediaFiles as $mediaFile)
                            <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('media.show', $mediaFile) }}" class="font-medium text-zinc-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400" wire:navigate>
                                        {{ $mediaFile->display_name }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ ucfirst($mediaFile->media_type->value) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $mediaFile->formatted_duration ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $mediaFile->formatted_file_size }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($mediaFile->folder)
                                        <a href="{{ route('folders.show', $mediaFile->folder) }}" class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600" wire:navigate>
                                            <flux:icon.folder class="size-3" />
                                            {{ $mediaFile->folder->name }}
                                        </a>
                                    @else
                                        <span class="text-sm text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-status-badge :value="$mediaFile->status->value" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $mediaFile->created_at->diffForHumans() }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <flux:dropdown>
                                        <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" />
                                        <flux:menu>
                                            <flux:menu.item icon="eye" tag="a" href="{{ route('media.show', $mediaFile) }}" wire:navigate>View</flux:menu.item>
                                            @if ($mediaFile->hasPhysicalFile())
                                                <flux:menu.item icon="arrow-down-tray" tag="a" href="{{ route('media.download', $mediaFile) }}">Download</flux:menu.item>
                                            @else
                                                <flux:menu.item icon="arrow-down-tray" disabled>Download</flux:menu.item>
                                            @endif
                                            <flux:separator />
                                            <flux:menu.item icon="pencil" :href="route('media.show', ['mediaFile' => $mediaFile, 'action' => 'rename'])" wire:navigate>Rename</flux:menu.item>
                                            <flux:menu.item icon="folder-arrow-down" :href="route('media.show', ['mediaFile' => $mediaFile, 'action' => 'move'])" wire:navigate>Move to Folder</flux:menu.item>
                                            <flux:separator />
                                            <flux:menu.item icon="trash" :href="route('media.show', ['mediaFile' => $mediaFile, 'action' => 'delete'])" wire:navigate>Delete</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $mediaFiles->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
