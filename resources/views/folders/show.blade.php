<x-layouts::app :title="$folder->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('folders.index') }}" icon="arrow-left" variant="subtle" size="sm" wire:navigate />
            <div class="flex-1">
                <x-page-header :title="$folder->name" :description="$folder->mediaFiles->count() . ' ' . Str::plural('file', $folder->mediaFiles->count())" />
            </div>
        </div>

        @if ($folder->mediaFiles->isEmpty())
            <x-empty-state
                title="No files in this folder"
                description="Move media files into this folder to organize them."
                icon="folder"
            />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($folder->mediaFiles as $mediaFile)
                    <a href="{{ route('media.show', $mediaFile) }}" class="group relative rounded-lg border border-zinc-200 bg-white p-4 transition-colors hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600" wire:navigate>
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                                    @if ($mediaFile->media_type->value === 'audio')
                                        <flux:icon.musical-note class="size-5 text-indigo-600 dark:text-indigo-400" />
                                    @else
                                        <flux:icon.play class="size-5 text-indigo-600 dark:text-indigo-400" />
                                    @endif
                                </div>
                                <div>
                                    <h3 class="font-medium text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">{{ $mediaFile->display_name }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $mediaFile->formatted_file_size }}</p>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
