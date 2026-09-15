@php
    $acceptedExtensions = collect(config('media.supported_media', []))
        ->flatMap(fn (array $extensions): array => array_keys($extensions))
        ->map(fn (string $extension): string => '.'.$extension)
        ->implode(',');
@endphp

<x-layouts::app :title="__('Upload Media')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-start justify-between gap-4">
            <x-page-header title="Upload Media" description="Add one audio or video file to your private media library" />
            <flux:button :href="route('media.index')" wire:navigate variant="subtle">Cancel</flux:button>
        </div>

        <form
            id="media-upload-form"
            method="POST"
            action="{{ route('media.upload.store') }}"
            enctype="multipart/form-data"
            class="max-w-2xl"
        >
            @csrf
            <input type="hidden" name="upload_attempt_id" id="upload_attempt_id" value="{{ old('upload_attempt_id', $uploadAttemptId) }}">

            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div>
                    <flux:label for="media_file">Media file</flux:label>
                    <input
                        id="media_file"
                        name="media_file"
                        type="file"
                        accept="{{ $acceptedExtensions }}"
                        required
                        class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:file:bg-zinc-700"
                    >
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        Supported: MP3, WAV, M4A, AAC, FLAC, OGG, MP4, MOV, and WEBM. Maximum size: 500 MiB.
                    </p>
                    @error('media_file')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5">
                    <flux:label for="folder_id">Folder <span class="text-zinc-400">(optional)</span></flux:label>
                    <select id="folder_id" name="folder_id" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">No folder</option>
                        @foreach ($folders as $folder)
                            <option value="{{ $folder->id }}" @selected((string) old('folder_id') === (string) $folder->id)>{{ $folder->name }}</option>
                        @endforeach
                    </select>
                    @error('folder_id')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div id="media-upload-status" class="mt-5 hidden rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-700/50 dark:text-zinc-300" role="status" aria-live="polite"></div>
                <div id="media-upload-progress-wrap" class="mt-3 hidden">
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span id="media-upload-progress-label">Uploading…</span>
                        <span id="media-upload-progress-value">0%</span>
                    </div>
                    <progress id="media-upload-progress" class="mt-2 h-2 w-full accent-indigo-600" max="100" value="0"></progress>
                </div>
                <div id="media-upload-errors" class="mt-3 hidden rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert"></div>

                <div class="mt-6 flex items-center gap-3">
                    <flux:button id="media-upload-submit" type="submit" icon="arrow-up-tray">
                        Upload Media
                    </flux:button>
                    <flux:button variant="subtle" :href="route('media.index')" wire:navigate>Cancel</flux:button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('media-upload-form');
            const attemptInput = document.getElementById('upload_attempt_id');
            const fileInput = document.getElementById('media_file');
            const submitButton = document.getElementById('media-upload-submit');
            const status = document.getElementById('media-upload-status');
            const progressWrap = document.getElementById('media-upload-progress-wrap');
            const progress = document.getElementById('media-upload-progress');
            const progressLabel = document.getElementById('media-upload-progress-label');
            const progressValue = document.getElementById('media-upload-progress-value');
            const errors = document.getElementById('media-upload-errors');

            if (!form || !attemptInput || !fileInput || !submitButton) {
                return;
            }

            if (!attemptInput.value) {
                attemptInput.value = window.crypto?.randomUUID?.() ?? '{{ $uploadAttemptId }}';
            }

            const showStatus = (message) => {
                status.textContent = message;
                status.classList.remove('hidden');
            };

            const showErrors = (messages) => {
                errors.textContent = messages.join(' ');
                errors.classList.remove('hidden');
            };

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                errors.classList.add('hidden');

                if (!fileInput.files || fileInput.files.length !== 1) {
                    showErrors(['Select exactly one media file.']);
                    return;
                }

                const xhr = new XMLHttpRequest();
                const formData = new FormData(form);

                submitButton.disabled = true;
                progressWrap.classList.remove('hidden');
                progress.value = 0;
                progressValue.textContent = '0%';
                progressLabel.textContent = 'Uploading…';
                showStatus('Uploading file…');

                xhr.open('POST', form.action, true);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', (progressEvent) => {
                    if (! progressEvent.lengthComputable) {
                        return;
                    }

                    const percentage = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                    progress.value = percentage;
                    progressValue.textContent = `${percentage}%`;

                    if (percentage >= 100) {
                        progressLabel.textContent = 'Finalizing…';
                        showStatus('Upload transferred. Finalizing validation and storage…');
                    }
                });

                xhr.addEventListener('load', () => {
                    let payload = null;

                    try {
                        payload = JSON.parse(xhr.responseText);
                    } catch (error) {
                        // The response is reported below when it is not JSON.
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && payload?.redirect) {
                        progress.value = 100;
                        progressValue.textContent = '100%';
                        progressLabel.textContent = 'Complete';
                        showStatus('Upload committed. Opening Media Detail…');
                        window.location.assign(payload.redirect);
                        return;
                    }

                    const validationMessages = payload?.errors
                        ? Object.values(payload.errors).flat()
                        : [payload?.message ?? 'The upload could not be completed. Please try again.'];

                    submitButton.disabled = false;
                    showErrors(validationMessages);
                    showStatus('Upload failed. The same upload attempt can be retried.');
                });

                xhr.addEventListener('error', () => {
                    submitButton.disabled = false;
                    showErrors(['The connection was interrupted. Retry with the same selected file.']);
                    showStatus('Upload interrupted before server confirmation.');
                });

                xhr.send(formData);
            });
        });
    </script>
</x-layouts::app>
