<x-layouts::app :title="$transcription->title">
    @php
        $canExport = $transcription->status === \App\Enums\TranscriptionStatus::Completed;
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6" x-data="{ activeTab: 'transcript', showRenameModal: {{ request()->boolean('rename') ? 'true' : 'false' }} }">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('transcriptions.index') }}" icon="arrow-left" variant="subtle" size="sm" wire:navigate>All Transcriptions</flux:button>
            <x-page-header :title="$transcription->title" :description="'Transcription details and transcript'" />

            <div class="ml-auto flex items-center gap-2">
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" variant="primary" size="sm" :disabled="!$canExport">
                        Export
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.txt', $transcription)" :disabled="!$canExport">
                            Export as TXT
                        </flux:menu.item>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.srt', $transcription)" :disabled="!$canExport">
                            Export as SRT
                        </flux:menu.item>
                        <flux:menu.item icon="play" :href="route('transcriptions.export.vtt', $transcription)" :disabled="!$canExport">
                            Export as VTT
                        </flux:menu.item>
                        <flux:menu.item icon="document-text" :href="route('transcriptions.export.docx', $transcription)" :disabled="!$canExport">
                            Export as DOCX
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                @if ($canExport)
                    <flux:button icon="language" variant="subtle" size="sm" href="{{ route('transcriptions.translations.show', $transcription) }}" data-translate-link>
                        Translate
                    </flux:button>
                @endif

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
                        <div
                            class="space-y-4"
                            x-data="transcriptPlayback({
                                segments: @js($playbackSegments),
                                hasPlayer: @js($streamUrl !== null),
                            })"
                            x-on:p4-seek="seekTo($event.detail.seconds)"
                        >
                            @if ($streamUrl !== null)
                                <div>
                                    @if ($mediaElement === 'video')
                                        <video
                                            data-media-player
                                            controls
                                            preload="metadata"
                                            src="{{ $streamUrl }}"
                                            class="max-h-96 w-full rounded-lg bg-black"
                                        ></video>
                                    @else
                                        <audio
                                            data-media-player
                                            controls
                                            preload="metadata"
                                            src="{{ $streamUrl }}"
                                            class="w-full"
                                        ></audio>
                                    @endif
                                </div>
                            @endif

                            @if ($transcription->segments->isEmpty())
                                <x-empty-state
                                    title="No transcript segments"
                                    description="This transcription does not have any segments yet."
                                    icon="document-text"
                                />
                            @else
                                <div
                                    class="space-y-4"
                                    x-data="transcriptSearch({ fullText: @js($fullTranscriptText) })"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input
                                            type="search"
                                            x-model="query"
                                            placeholder="Search transcript"
                                            aria-label="Search transcript"
                                            class="w-full max-w-xs rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                                        />
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="countLabel" aria-live="polite"></span>
                                        <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click="previous()" x-bind:disabled="!hasMatches" aria-label="Previous match">Previous</button>
                                        <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click="next()" x-bind:disabled="!hasMatches" aria-label="Next match">Next</button>
                                        <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click="clear()" x-bind:disabled="query === ''">Clear</button>
                                        <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200" x-on:click="copyFull()">Copy transcript</button>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="copyStatus" aria-live="polite"></span>
                                        <label for="transcript-language-filter" class="sr-only">Filter segments by language</label>
                                        <select
                                            id="transcript-language-filter"
                                            data-transcript-language-filter
                                            x-model="languageFilter"
                                            aria-label="Filter segments by language"
                                            class="rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-xs text-zinc-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                                        >
                                            <option value="">All languages</option>
                                            @foreach ($segmentLanguages as $language)
                                                <option value="{{ $language }}">{{ strtoupper($language) }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="filterLabel" aria-live="polite"></span>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500" data-transcript-nav-hint>Use Arrow Up/Down (or J/K) to move, Home/End to jump</span>
                                    </div>

                                    <div
                                        data-transcript-region
                                        tabindex="0"
                                        role="region"
                                        aria-label="Transcript segments"
                                        x-on:keydown.arrow-down.prevent="navigateNext()"
                                        x-on:keydown.arrow-up.prevent="navigatePrevious()"
                                        x-on:keydown.j.prevent="navigateNext()"
                                        x-on:keydown.k.prevent="navigatePrevious()"
                                        x-on:keydown.home.prevent="jumpToFirst()"
                                        x-on:keydown.end.prevent="jumpToLast()"
                                        class="max-h-[70vh] space-y-3 overflow-y-auto pr-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    >
                                        @foreach ($transcription->segments as $segment)
                                            <div
                                                data-segment-row
                                                data-segment-index="{{ $segment->segment_index }}"
                                                data-segment-language="{{ $segment->language?->value ?? 'und' }}"
                                                data-seek-seconds="{{ $segment->seek_seconds }}"
                                                class="flex gap-4 rounded-md p-1 transition-colors"
                                            >
                                                <div class="flex-shrink-0">
                                                    <button
                                                        type="button"
                                                        data-seek-seconds="{{ $segment->seek_seconds }}"
                                                        x-on:click="$dispatch('p4-seek', { seconds: Number($el.dataset.seekSeconds) })"
                                                        aria-label="Seek to {{ $segment->formatted_start }}"
                                                        class="inline-block rounded bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-600 hover:bg-zinc-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:bg-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-600"
                                                    >{{ $segment->formatted_start }}</button>
                                                </div>
                                                <div
                                                    class="flex-1 text-sm text-zinc-700 dark:text-zinc-300"
                                                    data-segment-text
                                                    data-segment-index="{{ $segment->segment_index }}"
                                                >{{ $segment->text }}</div>
                                                <div class="flex flex-shrink-0 items-start gap-2">
                                                    <span
                                                        data-segment-language="{{ $segment->language?->value ?? 'und' }}"
                                                        class="inline-block rounded bg-zinc-100 px-2 py-1 font-mono text-xs uppercase text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400"
                                                    >{{ $segment->language?->value ?? 'und' }}</span>
                                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click="copySegment({{ $segment->segment_index }})" aria-label="Copy segment">Copy</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
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
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('jobs.show', $job) }}" wire:navigate class="block rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                                    @else
                                        <div class="block rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    @endif
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
                                    @if (auth()->user()->isAdmin())
                                        </a>
                                    @else
                                        </div>
                                    @endif
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

                    @if ($transcription->status === \App\Enums\TranscriptionStatus::Failed)
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
                            <p class="text-sm text-red-700 dark:text-red-300">
                                {{ $transcription->error_message ?? 'The transcription failed.' }}
                            </p>

                            @if ($retryEligible && auth()->user()?->can('update', $transcription))
                                <form method="POST" action="{{ route('transcriptions.retry', $transcription) }}" class="mt-3">
                                    @csrf
                                    <flux:button type="submit" size="sm" icon="arrow-path">Retry transcription</flux:button>
                                </form>
                            @elseif (! $retryEligible)
                                <p class="mt-2 text-xs text-red-600 dark:text-red-400">This failure is not retryable.</p>
                            @endif
                        </div>
                    @endif
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

    <script>
        window.p4ResolveActive = window.p4ResolveActive || function (segments, time) {
            let match = null;
            (segments || []).forEach((segment) => {
                if (segment.start <= time && time < segment.end) {
                    if (match === null || segment.index < match.index) {
                        match = segment;
                    }
                }
            });
            return match === null ? null : match.index;
        };

        window.transcriptPlayback = window.transcriptPlayback || function (config) {
            return {
                segments: config.segments ?? [],
                activeIndex: null,
                autoScroll: true,
                init() {
                    const player = this.player();
                    if (player) {
                        const update = () => this.updateActive();
                        player.addEventListener('timeupdate', update);
                        player.addEventListener('seeked', update);
                        player.addEventListener('loadedmetadata', update);
                        player.addEventListener('play', () => this.onPlaybackInteraction());
                        player.addEventListener('pause', () => this.onPlaybackInteraction());
                        player.addEventListener('seeking', () => this.onPlaybackInteraction());
                    }
                    const region = this.region();
                    if (region) {
                        ['wheel', 'touchmove'].forEach((event) => {
                            region.addEventListener(event, () => { this.autoScroll = false; }, { passive: true });
                        });
                        region.addEventListener('keydown', () => { this.autoScroll = false; });
                    }
                },
                player() {
                    return this.$el.querySelector('[data-media-player]');
                },
                region() {
                    return this.$el.querySelector('[data-transcript-region]');
                },
                rows() {
                    return Array.from(this.$el.querySelectorAll('[data-segment-row]'));
                },
                onPlaybackInteraction() {
                    this.autoScroll = true;
                    this.updateActive();
                },
                updateActive() {
                    const player = this.player();
                    if (!player) {
                        return;
                    }
                    const index = window.p4ResolveActive(this.segments, player.currentTime);
                    if (index === this.activeIndex) {
                        return;
                    }
                    this.setActive(index);
                },
                setActive(index) {
                    this.activeIndex = index;
                    let activeRow = null;
                    this.rows().forEach((row) => {
                        const isActive = index !== null && Number(row.dataset.segmentIndex) === index;
                        row.classList.toggle('bg-indigo-50', isActive);
                        row.classList.toggle('dark:bg-indigo-950/40', isActive);
                        row.classList.toggle('ring-2', isActive);
                        row.classList.toggle('ring-indigo-500', isActive);
                        if (isActive) {
                            row.setAttribute('aria-current', 'true');
                            activeRow = row;
                        } else {
                            row.removeAttribute('aria-current');
                        }
                    });
                    if (activeRow && this.autoScroll) {
                        activeRow.scrollIntoView({ block: 'nearest' });
                    }
                },
                seekTo(seconds) {
                    const player = this.player();
                    const target = Number(seconds);
                    if (!player || !Number.isFinite(target)) {
                        return;
                    }
                    player.currentTime = target;
                    this.autoScroll = true;
                    this.updateActive();
                },
            };
        };

        window.transcriptSearch = window.transcriptSearch || function (config) {
            return {
                query: '',
                languageFilter: '',
                matchCount: 0,
                currentIndex: 0,
                navIndex: null,
                copyStatus: '',
                fullText: config.fullText ?? '',
                init() {
                    this.rootEl = this.$el ?? this.$root;
                    this.segmentEls().forEach((el) => {
                        if (el.dataset.rawText === undefined) {
                            el.dataset.rawText = el.textContent;
                        }
                    });
                    this.applyFilter();
                    this.$watch('query', () => this.refresh());
                    this.$watch('languageFilter', () => this.applyFilter());
                },
                rowEls() {
                    return Array.from(this.rootEl.querySelectorAll('[data-segment-row]'));
                },
                visibleRows() {
                    return this.rowEls().filter((row) => ! row.hidden);
                },
                segmentEls() {
                    const els = [];
                    this.visibleRows().forEach((row) => {
                        row.querySelectorAll('[data-segment-text]').forEach((el) => els.push(el));
                    });
                    return els;
                },
                applyFilter() {
                    this.rowEls().forEach((row) => {
                        const matches = this.languageFilter === '' || (row.dataset.segmentLanguage || 'und') === this.languageFilter;
                        row.hidden = ! matches;
                        if (! matches) {
                            row.querySelectorAll('[data-segment-text]').forEach((el) => {
                                el.textContent = this.rawText(el);
                            });
                        }
                    });
                    this.navIndex = null;
                    this.refresh();
                },
                rawText(el) {
                    return el.dataset.rawText ?? el.textContent;
                },
                get hasMatches() {
                    return this.matchCount > 0;
                },
                get countLabel() {
                    if (this.query.trim() === '') {
                        return '';
                    }
                    if (this.matchCount === 0) {
                        return 'No matches';
                    }
                    return (this.currentIndex + 1) + ' of ' + this.matchCount;
                },
                get filterLabel() {
                    if (this.languageFilter === '') {
                        return '';
                    }
                    const count = this.visibleRows().length;
                    return count + ' segment' + (count === 1 ? '' : 's');
                },
                resolveNavIndex(rows) {
                    const active = rows.findIndex((row) => row.getAttribute('aria-current') === 'true');
                    if (active !== -1) {
                        return active;
                    }
                    if (this.navIndex !== null) {
                        const tracked = rows.findIndex((row) => Number(row.dataset.segmentIndex) === this.navIndex);
                        if (tracked !== -1) {
                            return tracked;
                        }
                    }
                    return -1;
                },
                activateRow(row) {
                    if (! row) {
                        return;
                    }
                    this.navIndex = Number(row.dataset.segmentIndex);
                    const seconds = row.dataset.seekSeconds;
                    if (seconds !== undefined) {
                        this.$dispatch('p4-seek', { seconds: Number(seconds) });
                    }
                    row.scrollIntoView({ block: 'nearest' });
                },
                navigateNext() {
                    const rows = this.visibleRows();
                    if (rows.length === 0) {
                        return;
                    }
                    const current = this.resolveNavIndex(rows);
                    const target = current < 0 ? 0 : (current + 1) % rows.length;
                    this.activateRow(rows[target]);
                },
                navigatePrevious() {
                    const rows = this.visibleRows();
                    if (rows.length === 0) {
                        return;
                    }
                    const current = this.resolveNavIndex(rows);
                    const target = current <= 0 ? rows.length - 1 : current - 1;
                    this.activateRow(rows[target]);
                },
                jumpToFirst() {
                    const rows = this.visibleRows();
                    if (rows.length > 0) {
                        this.activateRow(rows[0]);
                    }
                },
                jumpToLast() {
                    const rows = this.visibleRows();
                    if (rows.length > 0) {
                        this.activateRow(rows[rows.length - 1]);
                    }
                },
                refresh() {
                    const query = this.query.toLocaleLowerCase();
                    let total = 0;
                    const previous = this.currentIndex;
                    this.segmentEls().forEach((el) => {
                        const text = this.rawText(el);
                        const positions = [];
                        if (query !== '') {
                            const lower = text.toLocaleLowerCase();
                            let from = 0;
                            while (from <= lower.length - query.length) {
                                const at = lower.indexOf(query, from);
                                if (at === -1) {
                                    break;
                                }
                                positions.push({ start: at, length: query.length });
                                from = at + query.length;
                            }
                        }
                        this.render(el, text, positions, total);
                        total += positions.length;
                    });
                    this.matchCount = total;
                    this.currentIndex = total === 0 ? 0 : Math.min(previous, total - 1);
                    this.applyCurrent();
                },
                render(el, text, positions, offset) {
                    el.textContent = '';
                    if (positions.length === 0) {
                        el.appendChild(document.createTextNode(text));
                        return;
                    }
                    let cursor = 0;
                    positions.forEach((position, i) => {
                        if (position.start > cursor) {
                            el.appendChild(document.createTextNode(text.slice(cursor, position.start)));
                        }
                        const mark = document.createElement('mark');
                        mark.textContent = text.slice(position.start, position.start + position.length);
                        mark.dataset.matchIndex = String(offset + i);
                        mark.className = 'rounded bg-yellow-200 text-inherit dark:bg-yellow-700/60';
                        el.appendChild(mark);
                        cursor = position.start + position.length;
                    });
                    if (cursor < text.length) {
                        el.appendChild(document.createTextNode(text.slice(cursor)));
                    }
                },
                applyCurrent() {
                    const marks = Array.from(this.rootEl.querySelectorAll('mark[data-match-index]'));
                    marks.forEach((mark) => {
                        mark.classList.remove('bg-orange-400', 'dark:bg-orange-500');
                    });
                    const active = marks[this.currentIndex];
                    if (active) {
                        active.classList.add('bg-orange-400', 'dark:bg-orange-500');
                        active.scrollIntoView({ block: 'nearest' });
                    }
                },
                next() {
                    if (this.matchCount === 0) {
                        return;
                    }
                    this.currentIndex = (this.currentIndex + 1) % this.matchCount;
                    this.applyCurrent();
                },
                previous() {
                    if (this.matchCount === 0) {
                        return;
                    }
                    this.currentIndex = (this.currentIndex - 1 + this.matchCount) % this.matchCount;
                    this.applyCurrent();
                },
                clear() {
                    this.query = '';
                },
                copyFull() {
                    this.copyText(this.fullText);
                },
                copySegment(index) {
                    const el = this.rootEl.querySelector('[data-segment-text][data-segment-index="' + index + '"]');
                    this.copyText(el ? this.rawText(el) : '');
                },
                copyText(text) {
                    if (! text) {
                        this.copyStatus = 'Nothing to copy';
                        return;
                    }
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(
                                () => { this.copyStatus = 'Copied'; },
                                () => { this.copyStatus = 'Copy failed'; }
                            );
                        } else {
                            const helper = document.createElement('textarea');
                            helper.value = text;
                            helper.setAttribute('readonly', '');
                            helper.style.position = 'fixed';
                            helper.style.opacity = '0';
                            document.body.appendChild(helper);
                            helper.select();
                            document.execCommand('copy');
                            document.body.removeChild(helper);
                            this.copyStatus = 'Copied';
                        }
                    } catch (error) {
                        this.copyStatus = 'Copy failed';
                    }
                },
            };
        };
    </script>
</x-layouts::app>
