{{--
    P6-004 timing toolbar (Timing Editing + Validation).

    Owned by P6-004. Uses dedicated `data-timing-*` hooks and does not reuse the
    reserved Phase 4 selectors (`data-seek-seconds`, `data-segment-language`),
    P6-003's `data-edit-*` / `data-revision-*`, P6-006's `data-filter-language` /
    `data-nav-seconds`, or P6-007's `data-comparison-*` hooks. The
    `transcriptTiming` Alpine component that provides `timingEditMode`, `enter`,
    `cancelTiming`, and `forceExit` wraps this partial.

    The formulation is external (`#timing-edit-form`) and the per-row timing
    inputs associate with it through the `form` attribute, because the row region
    is already wrapped by P6-003's `#revision-edit-form` (forms must not nest).
--}}

<div class="flex flex-wrap items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/60" data-timing-toolbar>
    <span
        data-timing-revision-indicator
        data-timing-revision-state="{{ $activeRevisionId !== null ? 'revision' : 'machine' }}"
        data-timing-revision-version="{{ $activeRevisionVersion ?? '' }}"
        class="inline-block rounded bg-white px-2 py-1 font-mono text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200"
    >{{ $activeRevisionId !== null ? 'Revision v'.$activeRevisionVersion : 'Machine timing' }}</span>

    @if ($canEdit)
        <form id="timing-edit-form" method="POST" action="{{ route('transcriptions.revisions.timing', $transcription) }}" data-timing-form novalidate>
            @csrf
            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
        </form>

        <button
            type="button"
            data-timing-enter
            x-show="! timingEditMode"
            x-on:click="enter()"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-40"
        >Edit timing</button>

        <button
            type="submit"
            form="timing-edit-form"
            data-timing-save
            x-show="timingEditMode"
            style="display: none"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
        >Save timing</button>

        <button
            type="button"
            data-timing-cancel
            x-show="timingEditMode"
            style="display: none"
            x-on:click="cancelTiming()"
            class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
        >Cancel</button>

        <span
            data-timing-mode-status
            role="status"
            aria-live="polite"
            class="text-xs text-zinc-500 dark:text-zinc-400"
            x-text="timingEditMode ? 'Editing timing — changes are saved as a new revision' : ''"
        ></span>
    @endif

    @if ($errors->any())
        <div data-timing-validation-errors role="alert" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('timing_conflict'))
        <div data-timing-conflict role="alert" class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ session('timing_conflict') }}
            <a href="{{ route('transcriptions.show', $transcription) }}" class="font-medium underline">Reload current version</a>
        </div>
    @endif

    @if (session('timing_error'))
        <div data-timing-error role="alert" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ session('timing_error') }}
        </div>
    @endif

    @if (session('timing_notice'))
        <div data-timing-notice role="status" class="w-full rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('timing_notice') }}
        </div>
    @endif
</div>