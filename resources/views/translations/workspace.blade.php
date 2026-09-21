@use('App\Translation\TranslationStatus')

@php
    $status = $translation?->status;

    // One state per selected target; every branch below is driven by persisted state.
    $state = match (true) {
        $translation === null => 'none',
        $translation->isAwaitingDispatch() => 'awaiting-dispatch',
        $status === TranslationStatus::Translating => 'translating',
        $status === TranslationStatus::Completed => 'completed',
        $status === TranslationStatus::Failed => $canRetry ? 'failed-retryable' : 'failed-final',
        default => 'queued',
    };

    $stateLabel = function ($candidate): string {
        if ($candidate === null) {
            return 'Not started';
        }

        return match (true) {
            $candidate->isAwaitingDispatch() => 'Not queued',
            $candidate->status === TranslationStatus::Completed => 'Completed',
            $candidate->status === TranslationStatus::Translating => 'Translating',
            $candidate->status === TranslationStatus::Failed => 'Failed',
            default => 'Queued',
        };
    };

    $badgeColor = fn ($candidate): string => match (true) {
        $candidate === null => 'zinc',
        $candidate->status === TranslationStatus::Completed => 'green',
        $candidate->status === TranslationStatus::Failed => 'red',
        default => 'yellow',
    };

    $fullTranslatedText = $translatedSegments->pluck('text')->implode("\n");
@endphp

<x-layouts::app :title="'Translate: '.$transcription->title">
    <div class="flex h-full w-full flex-1 flex-col gap-6" data-translation-workspace data-translation-state="{{ $state }}">
        <div class="flex flex-wrap items-center gap-4">
            <flux:button href="{{ route('transcriptions.show', $transcription) }}" icon="arrow-left" variant="subtle" size="sm">Back to transcript</flux:button>
            <x-page-header :title="'Translate: '.$transcription->title" description="Translate this transcript into another language. The original transcript is never changed." />
        </div>

        @if (session('translation_notice'))
            <div data-translation-notice role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('translation_notice') }}
            </div>
        @endif

        @if (session('translation_error'))
            <div data-translation-error role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                {{ session('translation_error') }}
            </div>
        @endif

        @if (! $isTranscriptionCompleted)
            <div data-translation-unavailable class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                <x-empty-state
                    title="Translation is not available yet"
                    description="You can translate this transcript once transcription has completed."
                    icon="document-text"
                />
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                <nav class="flex flex-wrap gap-4 border-b border-zinc-200 px-4 dark:border-zinc-700" aria-label="Target languages">
                    @foreach ($targets as $target)
                        @php
                            $candidate = $latest[$target->value] ?? null;
                            $isSelected = $selected === $target;
                        @endphp
                        <a
                            href="{{ route('transcriptions.translations.show', [$transcription, 'target' => $target->value]) }}"
                            data-target-tab="{{ $target->value }}"
                            data-target-state="{{ $stateLabel($candidate) }}"
                            @if ($isSelected) aria-current="page" @endif
                            class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium {{ $isSelected ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                        >
                            {{ $target->label() }}
                            <flux:badge :color="$badgeColor($candidate)" size="sm">{{ $stateLabel($candidate) }}</flux:badge>
                        </a>
                    @endforeach
                </nav>

                <div class="p-4">
                    @if ($state === 'none' || $state === 'awaiting-dispatch')
                        <form
                            method="POST"
                            action="{{ route('transcriptions.translations.store', $transcription) }}"
                            data-translation-start-form
                            x-data="{ busy: false }"
                            x-on:submit="if (busy) { $event.preventDefault() } else { busy = true }"
                            class="space-y-4"
                        >
                            @csrf

                            @if ($state === 'awaiting-dispatch')
                                <div data-translation-awaiting-dispatch role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                    This translation was created but could not be queued. Try again to start it.
                                </div>
                                <input type="hidden" name="target_language" value="{{ $selected?->value }}">
                            @else
                                <flux:heading size="lg">Choose a language</flux:heading>
                                <flux:text>Pick the language to translate this transcript into. Timestamps are kept exactly as in the original.</flux:text>

                                <div>
                                    <label for="target_language" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Target language</label>
                                    <select
                                        id="target_language"
                                        name="target_language"
                                        data-target-select
                                        class="w-full max-w-xs rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                                    >
                                        {{-- Targets that already have a translation live in the tabs above. --}}
                                        @foreach ($targets as $target)
                                            @continue(isset($latest[$target->value]))
                                            <option value="{{ $target->value }}" @selected($selected === $target)>{{ $target->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('target_language')
                                        <p data-translation-field-error role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <flux:button type="submit" variant="primary" icon="language" data-translation-start x-bind:disabled="busy">
                                {{ $state === 'awaiting-dispatch' ? 'Try again' : 'Start translation' }}
                            </flux:button>
                        </form>
                    @elseif ($state === 'queued' || $state === 'translating')
                        <div
                            data-translation-progress
                            data-status-url="{{ route('translations.status', $translation) }}"
                            role="status"
                            aria-live="polite"
                            class="flex items-center gap-3 py-8"
                            x-data="{
                                status: @js($status->value),
                                url: null,
                                timer: null,
                                init() {
                                    this.url = this.$el.dataset.statusUrl;
                                    this.timer = setInterval(() => this.check(), 2000);
                                },
                                async check() {
                                    try {
                                        const response = await fetch(this.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                                        if (! response.ok) { return; }
                                        const data = await response.json();
                                        if (data.status !== this.status || data.awaiting_dispatch) {
                                            clearInterval(this.timer);
                                            window.location.reload();
                                        }
                                    } catch (error) {}
                                },
                            }"
                        >
                            <svg class="size-5 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <div>
                                <flux:heading size="lg">{{ $state === 'queued' ? 'Queued' : 'Translating' }}</flux:heading>
                                <flux:text>
                                    {{ $state === 'queued' ? 'Your translation is waiting to start.' : 'Your translation is in progress.' }}
                                    This page updates automatically.
                                </flux:text>
                            </div>
                        </div>
                    @elseif ($state === 'failed-retryable' || $state === 'failed-final')
                        <div
                            data-translation-failure
                            data-failure-code="{{ $translation->failure_code?->value }}"
                            data-retryable="{{ $state === 'failed-retryable' ? 'true' : 'false' }}"
                            role="alert"
                            class="space-y-4 py-4"
                        >
                            <flux:heading size="lg">Translation failed</flux:heading>
                            <flux:text data-failure-message>{{ $translation->failure_code?->userMessage() ?? 'The translation could not be completed.' }}</flux:text>

                            @if ($state === 'failed-retryable')
                                <form
                                    method="POST"
                                    action="{{ route('translations.retry', $translation) }}"
                                    data-translation-retry-form
                                    x-data="{ busy: false }"
                                    x-on:submit="if (busy) { $event.preventDefault() } else { busy = true }"
                                >
                                    @csrf
                                    <flux:button type="submit" variant="primary" icon="arrow-path" data-translation-retry x-bind:disabled="busy">Retry translation</flux:button>
                                </form>
                            @else
                                <flux:text data-translation-not-retryable class="text-sm">This translation cannot be retried from here. If it keeps happening, contact your administrator and quote the reference below.</flux:text>
                            @endif

                            <flux:text class="font-mono text-xs text-zinc-500">Reference: {{ $translation->failure_code?->value ?? 'unknown' }}</flux:text>
                        </div>
                    @elseif ($state === 'completed')
                        <div
                            data-translation-completed
                            class="space-y-4"
                            x-data="{
                                view: 'translation',
                                copyStatus: '',
                                async copy(text, label) {
                                    try {
                                        await navigator.clipboard.writeText(text);
                                        this.copyStatus = label + ' copied';
                                    } catch (error) {
                                        this.copyStatus = 'Copy failed';
                                    }
                                },
                            }"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex overflow-hidden rounded-md border border-zinc-300 dark:border-zinc-600" role="group" aria-label="Show">
                                    <button type="button" data-view-toggle="translation" x-on:click="view = 'translation'" x-bind:aria-pressed="view === 'translation'" x-bind:class="view === 'translation' ? 'bg-indigo-600 text-white' : 'bg-white text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'" class="px-3 py-1.5 text-sm font-medium">Translation</button>
                                    <button type="button" data-view-toggle="source" x-on:click="view = 'source'" x-bind:aria-pressed="view === 'source'" x-bind:class="view === 'source' ? 'bg-indigo-600 text-white' : 'bg-white text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'" class="px-3 py-1.5 text-sm font-medium">Original</button>
                                </div>

                                <button type="button" data-copy-full x-on:click="copy(@js($fullTranslatedText), 'Translation')" class="rounded-md bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200">Copy translation</button>
                                <span data-copy-status class="text-xs text-zinc-500 dark:text-zinc-400" x-text="copyStatus" aria-live="polite"></span>

                                <div class="ml-auto flex flex-wrap items-center gap-2" role="group" aria-label="Export translation">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Export</span>
                                    <flux:button size="sm" variant="subtle" icon="arrow-down-tray" href="{{ route('translations.export.txt', $translation) }}" data-export="txt">TXT</flux:button>
                                    <flux:button size="sm" variant="subtle" icon="arrow-down-tray" href="{{ route('translations.export.srt', $translation) }}" data-export="srt">SRT</flux:button>
                                    <flux:button size="sm" variant="subtle" icon="arrow-down-tray" href="{{ route('translations.export.vtt', $translation) }}" data-export="vtt">VTT</flux:button>
                                    <flux:button size="sm" variant="subtle" icon="arrow-down-tray" href="{{ route('translations.export.docx', $translation) }}" data-export="docx">DOCX</flux:button>
                                </div>
                            </div>

                            @if ($translatedSegments->isEmpty())
                                <x-empty-state title="No translated segments" description="This translation does not contain any segments." icon="document-text" />
                            @else
                                <div data-translation-region class="max-h-[70vh] space-y-3 overflow-y-auto pr-1">
                                    @foreach ($translatedSegments as $segment)
                                        @php $source = $sourceSegments->get($segment->segment_index); @endphp
                                        <div data-segment-row data-segment-index="{{ $segment->segment_index }}" class="flex gap-4 rounded-md p-1">
                                            <div class="flex-shrink-0">
                                                <span data-segment-time class="inline-block rounded bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">{{ \App\TranscriptExperience\SegmentTimestamp::fromSeconds($segment->start_seconds)->display() }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p data-translated-text x-show="view === 'translation'" class="text-zinc-900 dark:text-zinc-100">{{ $segment->text }}</p>
                                                <p data-source-text x-show="view === 'source'" style="display: none" class="text-zinc-900 dark:text-zinc-100">{{ $source?->text }}</p>
                                            </div>
                                            <button type="button" data-copy-segment="{{ $segment->segment_index }}" x-on:click="copy(@js($segment->text), 'Segment {{ $segment->segment_index + 1 }}')" class="flex-shrink-0 self-start rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" aria-label="Copy translated segment {{ $segment->segment_index + 1 }}">Copy</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
