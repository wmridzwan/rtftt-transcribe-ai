{{--
    P6-007 read-only source / active-revision / persisted-translation comparison.

    Owned by P6-007 (presentation-only, DECISION-P6-007-SCOPE-001). Uses dedicated
    `data-compare-*` hooks and does not reuse the reserved Phase 4 selectors or
    P6-006's hooks. Every value rendered is persisted state; no freshness or
    staleness is inferred and no write is performed.
--}}

<div data-transcript-comparison class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex overflow-hidden rounded-md border border-zinc-300 dark:border-zinc-600" role="group" aria-label="Transcript view">
            <button
                type="button"
                data-compare-view="normal"
                x-on:click="transcriptView = 'normal'"
                x-bind:aria-pressed="transcriptView === 'normal'"
                x-bind:class="transcriptView === 'normal' ? 'bg-indigo-600 text-white' : 'bg-white text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                class="px-3 py-1.5 text-xs font-medium"
            >Transcript</button>
            <button
                type="button"
                data-compare-view="compare"
                x-on:click="transcriptView = 'compare'"
                x-bind:aria-pressed="transcriptView === 'compare'"
                x-bind:class="transcriptView === 'compare' ? 'bg-indigo-600 text-white' : 'bg-white text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                class="px-3 py-1.5 text-xs font-medium"
            >Compare</button>
        </div>
        <span class="text-xs text-zinc-500 dark:text-zinc-400">Comparison is read-only.</span>
    </div>

    <div
        data-comparison-panel
        x-show="transcriptView === 'compare'"
        style="display: none"
        class="space-y-3"
    >
        <div
            data-comparison-state="{{ $comparisonView->hasActiveRevision ? 'revision' : 'machine' }}"
            class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"
        >
            <span data-comparison-source-state>{{ $comparisonView->hasActiveRevision ? 'Active revision v'.$comparisonView->activeRevisionVersion : 'Machine source is authoritative (no active revision)' }}</span>
            @if ($comparisonView->hasTranslation)
                <span data-comparison-translation-state>Translation: {{ strtoupper((string) $comparisonView->translationTargetLanguage) }}</span>
            @else
                <span data-comparison-no-translation role="status">No translation available</span>
            @endif
        </div>

        @if ($comparisonView->hasTranslation && $comparisonView->hasEditedActiveRevision())
            <p data-comparison-mismatch-note role="note" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                The persisted translation is of the original machine source. The active revision has since been edited, so the translation is not a translation of the edited text.
            </p>
        @endif

        @if ($comparisonView->isEmpty())
            <x-empty-state
                title="Nothing to compare"
                description="This transcription does not have any segments yet."
                icon="document-text"
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-sm" data-comparison-table>
                    <thead>
                        <tr class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            <th scope="col" class="w-1/3 border-b border-zinc-200 px-2 py-1.5 dark:border-zinc-700">Machine source</th>
                            <th scope="col" class="w-1/3 border-b border-zinc-200 px-2 py-1.5 dark:border-zinc-700">Active revision</th>
                            <th scope="col" class="w-1/3 border-b border-zinc-200 px-2 py-1.5 dark:border-zinc-700">Translation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparisonView->rows as $row)
                            <tr
                                data-comparison-row
                                data-comparison-machine-index="{{ $row->machineIndex ?? '' }}"
                                class="align-top"
                            >
                                <td class="border-b border-zinc-100 px-2 py-2 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300" data-comparison-machine>
                                    {{ $row->machineText ?? '—' }}
                                </td>
                                <td class="border-b border-zinc-100 px-2 py-2 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300" data-comparison-revision>
                                    @if (! $comparisonView->hasActiveRevision)
                                        <span data-comparison-revision-state="machine-authoritative" class="text-zinc-400 dark:text-zinc-500">Machine source is authoritative</span>
                                    @elseif ($row->machineIndex === null)
                                        <span data-comparison-revision-text>{{ $row->revisionText }}</span>
                                        <span data-comparison-revision-state="alignment-unavailable" class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">Alignment with the machine source and its translation is unavailable for this revision segment</span>
                                    @elseif (! $row->revisionAligned)
                                        <span data-comparison-revision-state="no-revision-segment" class="text-zinc-400 dark:text-zinc-500">No matching revision segment</span>
                                    @else
                                        <span data-comparison-revision-text>{{ $row->revisionText }}</span>
                                        @if ($row->revisionEdited())
                                            <span data-comparison-revision-edited-note class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">Edited after the translation was produced</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="border-b border-zinc-100 px-2 py-2 text-zinc-700 dark:border-zinc-800 dark:text-zinc-300" data-comparison-translation>
                                    {{ $row->hasTranslation() ? $row->translatedText : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
