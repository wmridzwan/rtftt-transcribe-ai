{{--
    P6-005 structural toolbar (Split / Merge + Translation Invalidation).

    Owned by P6-005. Uses dedicated `data-struct-*` hooks and does not reuse the
    reserved Phase 4 selectors (`data-seek-seconds`, `data-segment-language`),
    P6-003's `data-edit-*` / `data-revision-*`, P6-004's `data-timing-*`, P6-006's
    `data-filter-language` / `data-nav-seconds`, or P6-007's `data-comparison-*`
    hooks. The `transcriptStructural` Alpine component provides `structMode`,
    `enter`, `cancel`, and `chooseSplit` and wraps this partial plus the row
    region.

    The split/merge formulations are external (`#structural-split-form`,
    `#structural-merge-form`); per-row checkboxes associate with the merge form
    through the `form` attribute because the row region is already wrapped by
    P6-003's `#revision-edit-form` (forms must not nest).
--}}

<div class="flex flex-wrap items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/60" data-struct-toolbar>
    <span
        data-struct-revision-indicator
        data-struct-revision-state="{{ $activeRevisionId !== null ? 'revision' : 'machine' }}"
        data-struct-revision-version="{{ $activeRevisionVersion ?? '' }}"
        class="inline-block rounded bg-white px-2 py-1 font-mono text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200"
    >{{ $activeRevisionId !== null ? 'Revision v'.$activeRevisionVersion : 'Machine segments' }}</span>

    @if ($canEdit)
        <form id="structural-split-form" method="POST" action="{{ route('transcriptions.revisions.split', $transcription) }}" data-struct-split-form novalidate>
            @csrf
            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
            <input type="hidden" name="segment" x-model="splitKey">
        </form>

        <form id="structural-merge-form" method="POST" action="{{ route('transcriptions.revisions.merge', $transcription) }}" data-struct-merge-form novalidate>
            @csrf
            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
        </form>

        <button
            type="button"
            data-struct-enter
            x-show="! structMode"
            x-on:click="enter()"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-40"
        >Split / Merge</button>

        <button
            type="button"
            data-struct-cancel
            x-show="structMode"
            style="display: none"
            x-on:click="cancel()"
            class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
        >Cancel</button>

        <span
            data-struct-mode-status
            role="status"
            aria-live="polite"
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-text="structMode ? 'Select one segment to split, or select adjacent segments and merge — saved as a new revision' : ''"
        ></span>

        <div
            data-struct-split-panel
            x-show="structMode"
            style="display: none"
            class="flex flex-wrap items-center gap-2"
        >
            <span data-struct-split-target class="text-xs text-zinc-500 dark:text-zinc-400" x-text="splitKey ? ('Splitting ' + splitKey) : 'Choose a segment to split'"></span>
            <label for="struct-boundary" class="text-xs text-zinc-500 dark:text-zinc-400">Boundary (s)</label>
            <input
                id="struct-boundary"
                type="number"
                step="0.001"
                form="structural-split-form"
                name="boundary"
                data-struct-boundary-input
                aria-label="Split boundary time in seconds"
                class="w-28 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
            >
            <label for="struct-offset" class="text-xs text-zinc-500 dark:text-zinc-400">Text offset</label>
            <input
                id="struct-offset"
                type="number"
                step="1"
                min="1"
                form="structural-split-form"
                name="text_offset"
                data-struct-offset-input
                aria-label="Split text offset in code points"
                class="w-24 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
            >
            <button
                type="submit"
                form="structural-split-form"
                data-struct-split-submit
                x-bind:disabled="! splitKey"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-40"
            >Split</button>

            <button
                type="submit"
                form="structural-merge-form"
                data-struct-merge-submit
                class="rounded-md bg-zinc-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-600"
            >Merge selected</button>
        </div>
    @endif

    @if (session('structural_conflict'))
        <div data-struct-conflict role="alert" class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ session('structural_conflict') }}
            <a href="{{ route('transcriptions.show', $transcription) }}" class="font-medium underline">Reload current version</a>
        </div>
    @endif

    @if (session('structural_error'))
        <div data-struct-error role="alert" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ session('structural_error') }}
        </div>
    @endif

    @if (session('structural_notice'))
        <div data-struct-notice role="status" class="w-full rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('structural_notice') }}
        </div>
    @endif

    @if ($staleTranslations->isNotEmpty())
        <div data-translation-staleness role="status" class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            <span class="font-medium">Invalidated translations (historical, not current for the active revision):</span>
            @foreach ($staleTranslations as $translation)
                <span
                    data-translation-stale="{{ $translation->target_language->value }}"
                    data-translation-stale-reason="{{ $translation->staleness_reason?->value }}"
                    data-translation-stale-at="{{ $translation->stale_at?->toIso8601String() }}"
                    class="ml-2 inline-block rounded bg-white px-2 py-0.5 font-mono uppercase dark:bg-zinc-800"
                >{{ $translation->target_language->value }} · {{ $translation->staleness_reason?->value }}</span>
            @endforeach
        </div>
    @endif
</div>
