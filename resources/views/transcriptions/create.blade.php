<x-layouts::app :title="__('Upload Recording')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <x-page-header title="Upload Recording" description="Create a new transcription from an audio or video file" />

        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-700/50">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Demo mode — files are not uploaded in Phase 1.</p>
        </div>

        <form method="POST" action="{{ route('transcriptions.store') }}" class="max-w-2xl">
            @csrf

            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-6 rounded-lg border-2 border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <svg class="size-6 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                        Drag and drop your file here, or click to browse
                    </p>
                </div>

                <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">Supported formats: MP3, M4A, WAV, MP4, MOV</p>

                <div class="space-y-4">
                    <div>
                        <flux:label for="title">Title <span class="text-zinc-400">(optional)</span></flux:label>
                        <flux:input
                            type="text"
                            id="title"
                            name="title"
                            placeholder="e.g., Weekly Management Meeting"
                            :value="old('title')"
                        />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">If left blank, the filename will be used.</p>
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="language">Language</flux:label>
                        <flux:select id="language" name="language">
                            <option value="">Auto Detect</option>
                            <option value="en">English</option>
                            <option value="ms">Bahasa Melayu</option>
                            <option value="zh">Chinese</option>
                            <option value="other">Other</option>
                        </flux:select>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <flux:button type="submit" icon="arrow-up-tray">
                        Create Transcription
                    </flux:button>
                    <flux:button variant="subtle" :href="route('transcriptions.index')" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</x-layouts::app>
