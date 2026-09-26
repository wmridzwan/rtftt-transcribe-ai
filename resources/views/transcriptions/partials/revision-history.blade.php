{{--
    P6-008 revision history / audit surface.

    Owned by P6-008. Read-only presentation over the durable P6-002 revision
    graph in version order. Every displayed field has a persistence source:
    version, author (`created_by`), created time, parent linkage, segment
    count, and the active marker. Staleness markers surface only the persisted
    `stale_caused_by_revision_id` links (P6-005); no freshness is inferred.

    Uses dedicated `data-history-*` hooks; does not reuse P6-003's
    `data-edit-*` / `data-revision-*`, Phase 4 selectors, or P6-006's
    `data-filter-language` / `data-nav-seconds` hooks.
--}}

@php
    $historyVersionById = collect($revisionHistory ?? [])->mapWithKeys(fn ($revision) => [$revision->revisionId => $revision->version]);
@endphp

<div class="mt-3 rounded-md border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/40" data-revision-history>
    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Revision history</h3>

    @if (($revisionHistory ?? []) === [])
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-history-machine-source>
            No revisions yet — the machine transcript is authoritative.
        </p>
    @else
        @if (($activeRevisionId ?? null) === null)
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-history-machine-source>
                The machine transcript is currently authoritative (no active revision).
            </p>
        @endif

        <ol class="mt-2 space-y-1" data-history-list>
            @foreach ($revisionHistory as $historyRevision)
                @php
                    $isHistoryActive = ($activeRevisionId ?? null) === $historyRevision->revisionId;
                    $historyParentVersion = $historyRevision->parentRevisionId !== null
                        ? ($historyVersionById[$historyRevision->parentRevisionId] ?? null)
                        : null;
                    $historyStaleLangs = ($historyStalenessCauses ?? [])[$historyRevision->revisionId] ?? [];
                @endphp
                <li
                    data-history-entry
                    data-history-version="{{ $historyRevision->version }}"
                    data-history-revision="{{ $historyRevision->revisionId }}"
                    class="flex flex-wrap items-center gap-2 rounded border border-zinc-100 bg-zinc-50 px-2 py-1.5 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300"
                >
                    <span class="rounded bg-white px-2 py-0.5 font-mono dark:bg-zinc-700">v{{ $historyRevision->version }}</span>

                    @if ($isHistoryActive)
                        <span data-history-active class="rounded bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Active</span>
                    @elseif ($canEdit ?? false)
                        <form method="POST" action="{{ route('transcriptions.revisions.activate', $transcription) }}" class="inline" data-history-activate-form>
                            @csrf
                            <input type="hidden" name="target" value="{{ $historyRevision->revisionId }}">
                            <input type="hidden" name="expected_base" value="{{ $activeRevisionId ?? '' }}">
                            <button
                                type="submit"
                                data-history-activate
                                class="rounded-md px-2 py-1 font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950"
                            >Activate</button>
                        </form>
                    @endif

                    <span data-history-author title="Author user id {{ $historyRevision->createdBy }}">
                        {{ ($historyAuthors ?? [])[$historyRevision->createdBy] ?? 'user #'.$historyRevision->createdBy }}
                    </span>
                    <span data-history-created title="Persisted creation time">{{ $historyRevision->createdAt->format('Y-m-d H:i:s') }}</span>
                    <span data-history-parent>
                        @if ($historyRevision->parentRevisionId === null)
                            from machine source
                        @else
                            from v{{ $historyParentVersion ?? '?' }}
                        @endif
                    </span>
                    <span data-history-segments>{{ $historyRevision->segmentCount() }} {{ $historyRevision->segmentCount() === 1 ? 'segment' : 'segments' }}</span>

                    @if ($historyStaleLangs !== [])
                        <span
                            data-history-stale-cause="{{ implode(',', $historyStaleLangs) }}"
                            title="Persisted P6-005 staleness cause for these translations"
                            class="rounded bg-amber-100 px-2 py-0.5 font-mono uppercase text-amber-800 dark:bg-amber-900 dark:text-amber-200"
                        >stale: {{ implode(',', $historyStaleLangs) }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if (session('history_conflict'))
        <div data-history-conflict role="alert" class="mt-2 w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ session('history_conflict') }}
            <a href="{{ route('transcriptions.show', $transcription) }}" class="font-medium underline">Reload history</a>
        </div>
    @endif

    @if (session('history_error'))
        <div data-history-error role="alert" class="mt-2 w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ session('history_error') }}
        </div>
    @endif

    @if (session('history_notice'))
        <div data-history-notice role="status" class="mt-2 w-full rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('history_notice') }}
        </div>
    @endif
</div>
