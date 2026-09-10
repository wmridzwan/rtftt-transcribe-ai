<x-layouts::app :title="__('Folders')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <x-page-header title="Folders" description="Organize your media files into folders" />
            <flux:button tag="a" href="{{ route('folders.store') }}" icon="plus" variant="primary">
                Create Folder
            </flux:button>
        </div>

        @if ($folders->isEmpty())
            <x-empty-state
                title="No folders yet"
                description="Create a folder to organize your media files."
                icon="folder"
            />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($folders as $folder)
                    <div class="group relative rounded-lg border border-zinc-200 bg-white p-4 transition-colors hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                        <a href="{{ route('folders.show', $folder) }}" class="block" wire:navigate>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                                        <flux:icon.folder class="size-5 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">{{ $folder->name }}</h3>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $folder->media_files_count }} {{ Str::plural('file', $folder->media_files_count) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                                Created {{ $folder->created_at->diffForHumans() }}
                            </div>
                        </a>
                        <div class="absolute right-3 top-3 opacity-0 transition-opacity group-hover:opacity-100">
                            <flux:dropdown>
                                <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" tag="a" href="{{ route('folders.show', $folder) }}" wire:navigate>Open</flux:menu.item>
                                    <flux:menu.item icon="pencil" tag="a" href="{{ route('folders.update', $folder) }}">Rename</flux:menu.item>
                                    <flux:separator />
                                    <flux:menu.item icon="trash" tag="a" href="{{ route('folders.destroy', $folder) }}">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
