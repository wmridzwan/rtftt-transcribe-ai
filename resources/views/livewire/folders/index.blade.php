<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <x-page-header title="Folders" description="Organize your media files into folders" />
        <flux:button wire:click="$set('showCreateModal', true)" icon="plus" variant="primary">
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
                                <flux:menu.item icon="pencil" wire:click="openRenameModal({{ $folder->id }}, '{{ addslashes($folder->name) }}')">Rename</flux:menu.item>
                                <flux:separator />
                                <flux:menu.item icon="trash" wire:click="openDeleteModal({{ $folder->id }}, '{{ addslashes($folder->name) }}', {{ $folder->media_files_count }})">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create Folder Modal --}}
    <flux:modal wire:model="showCreateModal" name="folders.create-modal">
        <form wire:submit="store">
            <flux:heading size="lg">Create Folder</flux:heading>
            <flux:text class="mt-2">Enter a name for the new folder.</flux:text>

            <flux:field class="mt-4">
                <flux:label>Folder Name</flux:label>
                <flux:input wire:model="newFolderName" placeholder="Enter folder name" />
                <flux:error name="newFolderName" />
            </flux:field>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Rename Folder Modal --}}
    <flux:modal wire:model="showRenameModal" name="folders.rename-modal">
        <form wire:submit="update">
            <flux:heading size="lg">Rename Folder</flux:heading>
            <flux:text class="mt-2">Enter a new name for this folder.</flux:text>

            <flux:field class="mt-4">
                <flux:label>Folder Name</flux:label>
                <flux:input wire:model="renameFolderName" placeholder="Enter folder name" />
                <flux:error name="renameFolderName" />
            </flux:field>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showRenameModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Rename</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Folder Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" name="folders.delete-modal">
        <form wire:submit="destroy">
            <flux:heading size="lg">Delete Folder</flux:heading>

            @if ($deleteMediaCount > 0)
                <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <div class="flex items-center gap-2 text-sm font-medium text-yellow-800 dark:text-yellow-300">
                        <flux:icon.exclamation-triangle class="size-5" />
                        Folder Contains Files
                    </div>
                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">
                        This folder contains {{ $deleteMediaCount }} {{ Str::plural('file', $deleteMediaCount) }}.
                        Deleting the folder will move all files to the root level. This action cannot be undone.
                    </p>
                </div>
            @else
                <flux:text class="mt-2">Are you sure you want to delete this folder? This action cannot be undone.</flux:text>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Delete Folder</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
