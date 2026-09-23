{{--
    P6-003 revision toolbar (Text Editing + Undo/Redo).

    Owned by P6-003. Uses dedicated `data-edit-*` / `data-revision-*` hooks and
    does not reuse the reserved Phase 4 selectors (`data-seek-seconds`,
    `data-segment-language`) or P6-006's `data-filter-language` /
    `data-nav-seconds`. The `transcriptEditing` Alpine component that provides
    `editMode`, `enter`, `cancel`, and `submit` wraps this partial.
--}}

<div class="flex flex-wrap items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/60" data-revision-toolbar>
    <span
        data-revision-indicator
        data-revision-state="{{ $activeRevisionId !== null ? 'revision' : 'machine' }}"
        data-revision-version="{{ $activeRevisionVersion ?? '' }}"
        class="inline-block rounded bg-white px-2 py-1 font-mono text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200"
    >{{ $activeRevisionId !== null ? 'Revision v'.$activeRevisionVersion : 'Machine transcript' }}</span>

    @if ($canEdit)
        <button
            type="button"
            data-edit-enter
            x-show="! editMode"
            x-on:click="enter()"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-40"
        >Edit text</button>

        <button
            type="submit"
            form="revision-edit-form"
            data-edit-save
            x-show="editMode"
            style="display: none"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
        >Save</button>

        <button
            type="button"
            data-edit-cancel
            x-show="editMode"
            style="display: none"
            x-on:click="cancel()"
            class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
        >Cancel</button>

        <form method="POST" action="{{ route('transcriptions.revisions.undo', $transcription) }}" class="inline" data-edit-undo-form>
            @csrf
            <input type="hidden" name="target" value="{{ $undoRevisionId }}">
            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
            <button
                type="submit"
                data-edit-undo
                @disabled($undoRevisionId === null)
                class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700"
            >Undo</button>
        </form>

        <form method="POST" action="{{ route('transcriptions.revisions.redo', $transcription) }}" class="inline" data-edit-redo-form>
            @csrf
            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
            <button
                type="submit"
                data-edit-redo
                @disabled($redoRevision === null)
                class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700"
            >Redo</button>
        </form>
    @endif

    <span
        data-edit-mode-status
        role="status"
        aria-live="polite"
        class="text-xs text-zinc-500 dark:text-zinc-400"
        x-text="editMode ? 'Editing text — changes are saved as a new revision' : ''"
    ></span>

    @if (session('revision_conflict'))
        <div data-revision-conflict role="alert" class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ session('revision_conflict') }}
            <a href="{{ route('transcriptions.show', $transcription) }}" class="font-medium underline">Reload current version</a>
        </div>
    @endif

    @if (session('revision_error'))
        <div data-revision-error role="alert" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ session('revision_error') }}
        </div>
    @endif

    @if (session('revision_notice'))
        <div data-revision-notice role="status" class="w-full rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('revision_notice') }}
        </div>
    @endif
</div>
