@php
    $hasPhysicalFile = $mediaFile->hasPhysicalFile();
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('media.index') }}" icon="arrow-left" variant="subtle" size="sm" wire:navigate />
        <div class="flex-1">
            <x-page-header :title="$mediaFile->display_name" :description="'Media file details'" />
        </div>
        <div class="flex items-center gap-2">
            @if ($hasPhysicalFile)
                <flux:button tag="a" href="{{ route('media.download', $mediaFile) }}" icon="arrow-down-tray" variant="subtle" size="sm">Download</flux:button>
            @else
                <flux:button icon="arrow-down-tray" variant="subtle" size="sm" disabled>Download</flux:button>
            @endif
            <flux:button wire:click="openRenameModal" icon="pencil" variant="subtle" size="sm">Rename</flux:button>
            <flux:button wire:click="openMoveModal" icon="folder-arrow-down" variant="subtle" size="sm">Move to Folder</flux:button>
            <flux:button wire:click="openDeleteModal" icon="trash" variant="danger" size="sm">Delete</flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="sm" class="mb-4">File Information</flux:heading>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <x-metadata-row label="Display Name" :value="$mediaFile->display_name" />
                <x-metadata-row label="Original Filename" :value="$mediaFile->original_filename" />
                <div class="flex items-center justify-between py-2">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Storage Filename</flux:text>
                    <span class="font-mono text-xs text-right break-all max-w-[200px] text-zinc-900 dark:text-white">{{ $mediaFile->storage_filename }}</span>
                </div>
                <x-metadata-row label="Media Type" :value="ucfirst($mediaFile->media_type->value)" />
                <x-metadata-row label="MIME Type" :value="$mediaFile->mime_type" />
                <x-metadata-row label="Extension" :value="strtoupper($mediaFile->extension)" />
                <x-metadata-row label="File Size" :value="$mediaFile->formatted_file_size" />
                <x-metadata-row label="Duration" :value="$mediaFile->formatted_duration ?? '—'" />
                @if ($mediaFile->folder)
                    <div class="flex items-center justify-between py-2">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Folder</flux:text>
                        <a href="{{ route('folders.show', $mediaFile->folder) }}" class="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" wire:navigate>
                            <flux:icon.folder class="size-4" />
                            {{ $mediaFile->folder->name }}
                        </a>
                    </div>
                @endif
                <div class="flex items-center justify-between py-2">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">UUID</flux:text>
                    <span class="font-mono text-xs text-right break-all max-w-[200px] text-zinc-900 dark:text-white">{{ $mediaFile->uuid }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Status</flux:text>
                    <x-status-badge :value="$mediaFile->status->value" />
                </div>
                <x-metadata-row label="Uploaded" :value="$mediaFile->created_at->format('M j, Y g:i A')" />
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="sm" class="mb-4">Technical Details</flux:heading>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <x-metadata-row label="Audio Codec" :value="strtoupper($mediaFile->audio_codec ?? '—')" />
                <x-metadata-row label="Video Codec" :value="strtoupper($mediaFile->video_codec ?? '—')" />
                <x-metadata-row label="Sample Rate" :value="$mediaFile->sample_rate ? number_format($mediaFile->sample_rate) . ' Hz' : '—'" />
                <x-metadata-row label="Channels" :value="$mediaFile->channels ? ($mediaFile->channels === 1 ? 'Mono (1)' : 'Stereo (' . $mediaFile->channels . ')') : '—'" />
            </div>

            <flux:heading size="sm" class="mb-4 mt-6">Related Transcriptions</flux:heading>
            @if ($mediaFile->transcriptions->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No transcriptions found for this media file.</p>
            @else
                <div class="space-y-2">
                    @foreach ($mediaFile->transcriptions as $transcription)
                        <a href="{{ route('transcriptions.show', $transcription) }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50" wire:navigate>
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $transcription->title }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $transcription->created_at->diffForHumans() }}</div>
                            </div>
                            <x-status-badge :value="$transcription->status->value" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Rename Modal --}}
    <flux:modal wire:model="showRenameModal" name="media.rename-modal">
        <form wire:submit="rename">
            <flux:heading size="lg">Rename Media File</flux:heading>
            <flux:text class="mt-2">Enter a new display name for this media file.</flux:text>

            <flux:field class="mt-4">
                <flux:label>Display Name</flux:label>
                <flux:input wire:model="renameName" placeholder="Enter display name" />
                <flux:error name="renameName" />
            </flux:field>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showRenameModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Rename</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Move to Folder Modal --}}
    <flux:modal wire:model="showMoveModal" name="media.move-modal">
        <form wire:submit="moveToFolder">
            <flux:heading size="lg">Move to Folder</flux:heading>
            <flux:text class="mt-2">Select a folder to move this media file to.</flux:text>

            <flux:field class="mt-4">
                <flux:label>Folder</flux:label>
                <flux:select wire:model="moveFolderId" placeholder="No folder (root)">
                    <option value="">No folder (root)</option>
                    @foreach ($folders as $folder)
                        <option value="{{ $folder['id'] }}">{{ $folder['name'] }} ({{ $folder['media_files_count'] }} files)</option>
                    @endforeach
                </flux:select>
                <flux:error name="moveFolderId" />
            </flux:field>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showMoveModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Move</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" name="media.delete-modal">
        <form wire:submit="destroy">
            <flux:heading size="lg">Delete Media File</flux:heading>

            @if ($mediaFile->transcriptions->count() > 0)
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <div class="flex items-center gap-2 text-sm font-medium text-red-800 dark:text-red-300">
                        <flux:icon.exclamation-triangle class="size-5" />
                        Warning: Associated Transcriptions
                    </div>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                        This media file has {{ $mediaFile->transcriptions->count() }} {{ Str::plural('transcription', $mediaFile->transcriptions->count()) }}.
                        Deleting it will also delete all associated transcriptions. This action cannot be undone.
                    </p>
                    <flux:checkbox wire:model="confirmCascade" class="mt-4" label="I understand that associated transcriptions will also be deleted." />
                </div>
            @else
                <flux:text class="mt-2">Are you sure you want to delete this media file? This action cannot be undone.</flux:text>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="subtle" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Delete</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
