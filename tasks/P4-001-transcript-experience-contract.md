# P4-001 — Transcript Experience Contract

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code (independent review; per AGENTS.md agent model)

## Authorization

This contract was authored under `DECISION-PHASE4-AUTHORIZATION-001` (Phase 4
authorized for task-contract authoring, 2026-09-19), audited/finalized on
2026-09-19, then reconciled and authorized for implementation under
`DECISION-P4-001-AUTHORIZATION-001` (HPO, 2026-09-19): P4-001 transitioned
BACKLOG → READY. Only P4-001 is authorized. P4-002 through P4-006 remain
BACKLOG and require separate implementation authorization after P4-001 is DONE.

Browser-verification tooling is not required for P4-001 and does not block it;
that unresolved decision must be resolved before browser-dependent tasks
(P4-003, and ultimately P4-006).

## Authorized Phase

Phase 4 — Transcript Experience baseline (ADR-019)

## Batch

Batch 1 (proposed; to be confirmed by the implementation authorization)

## Objective

Establish the canonical Phase 4 transcript-experience contract and implement
only the shared baseline primitives that P4-002 through P4-005 consume. This task
must not implement their feature surfaces (streaming route, player binding,
search/copy UI, export changes).

## Context

- ADR-019 (ACCEPTED); `PHASE4-PLANNING.md` (D4-01..D4-07 decided)
- `DECISION_QUEUE.md` (`DECISION-PHASE4-AUTHORIZATION-001`,
  `DECISION-P4-001-AUTHORIZATION-001`)
- Phase 3 persisted model: `Transcription`, `TranscriptionSegment`, `MediaFile`
- Existing: `TranscriptionController::show`,
  `resources/views/transcriptions/show.blade.php`,
  `TranscriptionExportController`, `TranscriptionSegment::getFormattedStartAttribute`
- Persisted segment precision: `decimal(12,3)` seconds
- Supported languages: `ms`, `en`, `zh`, `ta`, `und`
- `.ai/guidelines/orchestration-policy.md`

## Implements vs Specifies

### This task implements (shared baseline only)

1. A canonical timestamp formatting helper providing, from one persisted
   `decimal(12,3)` value:
   - human-readable display string;
   - media-seek numeric seconds (exact persisted value);
   - SRT string (`HH:MM:SS,mmm`);
   - VTT string (`HH:MM:SS.mmm`).
2. A deterministic active-segment resolver as a pure function over an ordered
   segment list and a current playback time (algorithm in §Active Segment).
3. A workspace availability helper that answers, for a `Transcription`, whether
   transcript interaction, search, copy, and export are available
   (§Completed-Only Workspace Contract).
4. A no-speech/empty-transcript handling helper (§No-Speech).
5. Tests for items 1–4.

### This task specifies only (implemented by later tasks)

- P4-002: the media-delivery interface and HTTP range contract.
- P4-003: the browser binding contract (click-to-seek, highlight, auto-scroll).
- P4-004: search and copy semantics.
- P4-005: the export contract and hardening.

P4-001 must not add a streaming route, a player element, search/copy controls,
export changes, or any schema change.

## Canonical Timestamp Rules

### Persisted source of truth

- Persisted segment timestamps remain `decimal(12,3)` seconds (Phase 3 schema;
  unchanged). Persisted values therefore have at most millisecond precision.
- Test fixtures that represent persisted values must be representable as
  `decimal(12,3)` (for example `5.999`, `6.000`, `65.123`, `3600.001`).
- The four representations (display, seek, SRT, VTT) are derived from the same
  persisted value.
- Seek numeric is the exact persisted seconds value, emitted to the DOM as a
  numeric string with up to three decimals (for example `12.345`), consumed by
  the player binding. Seek must not be re-rounded after persistence.

### Canonical representations

- Display string: `H:MM:SS.mmm` when hours > 0, otherwise `MM:SS.mmm`. Exactly
  three fractional digits.
- Seek numeric: exact persisted numeric seconds (see above).
- SRT string: `HH:MM:SS,mmm`.
- VTT string: `HH:MM:SS.mmm`.

### Formatter robustness (defensive only)

- The formatter may defensively accept numeric inputs with more than three
  decimal places. Such inputs are not claimed to originate from persisted Phase
  3 segment rows; they exist only to prove formatter robustness.
- Higher-precision input is normalized by rounding to the nearest millisecond
  with proper carry into seconds.
- Example (robustness input, not a persisted fixture): `5.9996` normalizes to
  6000 ms → display `00:06.000`, SRT `00:00:06,000`, VTT `00:00:06.000`.
- Millisecond carry: a rounded millisecond component must carry into seconds.
  The formatter/display/export layer must never emit `.1000` or `,1000`.
- Negative, NaN, or non-finite values must not produce malformed output.

## Active Segment

The active segment is resolved deterministically:

```text
active(segment)  iff  start_seconds <= currentTime < end_seconds
```

- Gaps between segments: no active segment.
- Exact end boundary: the segment is inactive once `currentTime >= end_seconds`.
- Zero-length segment (`start_seconds == end_seconds`): never active.
- Before the first segment start and after the final segment end: no active
  segment.
- Overlapping/malformed intervals (not expected under Phase 3 invariants):
  resolve to the lowest `segment_index` whose interval contains `currentTime`.
- Ordering follows the verified Phase 3 `segment_index` ordering; the resolver
  must not depend on array position.

## Completed-Only Workspace Contract

| Status | Media player | Transcript interaction | Search | Copy | Export | Retry |
|---|---|---|---|---|---|---|
| completed | available when a physical media file exists | available when segments exist; otherwise the no-speech/empty state | available when segments exist | available when segments exist | available | n/a |
| queued | available when a physical media file exists | unavailable | unavailable | unavailable | unavailable | n/a |
| preparing | available when a physical media file exists | unavailable | unavailable | unavailable | unavailable | n/a |
| transcribing | available when a physical media file exists | unavailable | unavailable | unavailable | unavailable | n/a |
| failed | available when a physical media file exists | unavailable | unavailable | unavailable | unavailable | preserved Phase 3 retry surface |
| draft / cancelled | available when a physical media file exists | unavailable | unavailable | unavailable | unavailable | n/a |

- Export remains completed-only, matching the existing controller rule.
- Interactive transcript features must never present content that is not
  persisted completed content.
- Phase 3 retry/lifecycle semantics must not be weakened.

## Language Presentation

- Per-segment language is presented as persisted (`ms`, `en`, `zh`, `ta`,
  `und`); it is never mutated by display, search, copy, or export.
- Transcript detected/dominant language remains separate from segment language.

## Search Semantics (specified)

- Client-side over the loaded persisted segments (D4-04); no server request per
  keystroke, no database index.
- Case-insensitive for Latin text; substring matching (not whole-word).
- Unicode-safe for Chinese and Tamil; matching must not corrupt multi-byte text.
- Literal substring matching; no whitespace normalization that would shift
  offsets.
- Empty query: no active search, zero matches, no highlights.
- Result count is available; next/previous navigation wraps; clearing search
  removes all search highlights.
- Match highlighting must not use unsafe HTML injection; transcript text must
  never be interpreted as markup.
- Search highlights and the active playback highlight may coexist; search does
  not change active-segment semantics.

## Copy Semantics (specified)

- Copy full transcript: plain text only, segments joined in `segment_index`
  order by a single newline; no timestamps.
- Copy individual segment: plain text of that segment only; no timestamp.
- Unicode preserved verbatim.
- No-speech/empty transcript: copy is unavailable/no-op with accessible
  feedback.
- Clipboard failure must be handled without throwing and with accessible
  feedback.

## Export Contract (specified)

- Formats: TXT, SRT, VTT, DOCX (D4-03). Do not add or remove formats.
- Completed-only and authorization retained.
- Source of truth:
  - TXT and DOCX: title plus ordered persisted segments; when no segments exist,
    `full_text`.
  - SRT: ordered persisted segments only; no segments → empty output.
  - VTT: `WEBVTT` header plus ordered persisted segments; no segments →
    header-only output.
- Deterministic from persisted Phase 3 state; exports must never rerun
  transcription or provider inference.
- Multilingual/UTF-8 correctness for `ms`, `en`, `zh`, `ta`, `und`.
- Millisecond timestamp correctness for SRT/VTT per §Canonical Timestamp Rules.

## Player / Media-Delivery Interface (specified)

- Route identity is opaque: bind by `MediaFile` UUID, never a sequential id.
- The player consumes an authorized stream URL plus per-segment data attributes
  (start seconds, end seconds, `segment_index`, language).
- HTTP range details are owned by P4-002.
- No private filesystem path may appear in the interface.

## Read-Only Guarantee

- Phase 4 transcript content is read-only (D4-05). No editing, timestamp
  editing, merge/split, autosave, or revision history.
- No Phase 4 action may mutate `Transcription`, `TranscriptionSegment`, or
  `MediaFile` transcript content.

## Dependency Interfaces

- P4-002 consumes: workspace read model and opaque media identity.
- P4-003 consumes: active-segment resolver and the display/seek formatter.
- P4-004 consumes: search/copy semantics and the workspace read model.
- P4-005 consumes: the export contract and timestamp rules.

## Out of Scope

Do not implement:

- streaming route or player (P4-002);
- seek/highlight binding or auto-scroll (P4-003);
- search/copy controls (P4-004);
- export changes (P4-005);
- integration verification harness (P4-006);
- transcript editing, diarization, chapters, annotations (Phase 6);
- translation (Phase 5);
- server-side full-text search or any database index;
- any schema/migration change.

## Dependencies

Requires:

- ADR-019 ACCEPTED (satisfied);
- D4-01..D4-07 DECIDED (satisfied).

## Acceptance Criteria

1. A single canonical formatter produces display, seek-numeric, SRT, and VTT
   strings from one persisted `decimal(12,3)` value.
2. For persisted `decimal(12,3)` fixtures (`5.999`, `6.000`, `65.123`,
   `3600.001`), all four representations are correct and no output contains
   `.1000` or `,1000`.
3. The formatter defensively normalizes higher-precision numeric input (not a
   persisted fixture) by rounding to the nearest millisecond with carry;
   `5.9996` yields display `00:06.000`, SRT `00:00:06,000`, VTT `00:00:06.000`.
4. Display uses `H:MM:SS.mmm` when hours > 0, otherwise `MM:SS.mmm`.
5. Seek emits the exact persisted seconds value and is not re-rounded.
6. The active-segment resolver returns exactly one active segment or none, per
   §Active Segment, and is covered by unit tests for normal, gap, boundary,
   zero-length, and overlap cases.
7. The workspace availability helper returns the exact availability matrix in
   §Completed-Only Workspace Contract for all seven statuses.
8. Export remains completed-only in the helper's contract.
9. The no-speech helper treats `completed` + `speech_detected=false` +
   `text=""` + no segments as a valid empty workspace, not a failure.
10. The contract records the specified interfaces for P4-002..P4-005.
11. No application code outside the shared baseline primitives is changed; no
    route, controller, view, JavaScript, or schema change.
12. Phase 3 queue/retry/failure/language/no-speech/persistence/media contracts
    are explicitly preserved and unchanged.
13. Relevant tests pass; Pint clean; PHPStan 0 errors.

## Test Requirements

Minimum tests:

- formatter, persisted fixtures (`decimal(12,3)`): display/seek/SRT/VTT for
  whole, fractional, and hour-bearing values (`5.999`, `6.000`, `65.123`,
  `3600.001`); no `.1000`/`,1000`;
- formatter robustness (non-persisted high-precision input): `5.9996` →
  display `00:06.000`, SRT `00:00:06,000`, VTT `00:00:06.000`; carry
  normalization;
- formatter guard: negative/NaN/non-finite input produces no malformed output;
- active-segment resolver: normal, gap, exact-start, exact-end, zero-length,
  overlap tie-break, before-first, after-last;
- workspace availability matrix across all statuses;
- no-speech/empty handling.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Timestamp rounding emits `1000` ms | Carry-normalization test on defensive high-precision input (`x.9995`–`x.9999`) plus persisted-fixture tests |
| Conflating defensive input with persisted data | Contract separates persisted `decimal(12,3)` fixtures from robustness inputs |
| Duplicate/divergent formatters | One canonical formatter reused by display/seek/export |
| Active-segment ambiguity | Pure function + deterministic tie-break + boundary tests |
| Scope creep into feature surfaces | Implements/specifies split enforced by AC11 |
| Silent lifecycle weakening | Availability matrix tested against Phase 3 statuses |

## Implementation Notes

### Files Changed

- `app/TranscriptExperience/SegmentTimestamp.php` (new) — canonical timestamp
  primitive (display/seek/SRT/VTT), persisted `decimal(12,3)` source of truth,
  defensive higher-precision normalization with millisecond carry, invalid-input
  rejection.
- `app/TranscriptExperience/ActiveSegmentResolver.php` (new) — pure deterministic
  active-segment resolver ordered by `segment_index`.
- `app/TranscriptExperience/WorkspaceState.php` (new) — readonly availability
  flags value object.
- `app/TranscriptExperience/WorkspaceAvailability.php` (new) — completed-only
  availability matrix + no-speech detection.
- `tests/Unit/TranscriptExperience/SegmentTimestampTest.php` (new).
- `tests/Unit/TranscriptExperience/ActiveSegmentResolverTest.php` (new).
- `tests/Feature/TranscriptExperience/WorkspaceAvailabilityTest.php` (new).
- `tests/Feature/TranscriptExperience/NoSpeechTest.php` (new).

### Important Decisions

- Timestamp: one canonical primitive derives display/seek/SRT/VTT from the same
  input. Persisted values are the source of truth; higher-precision input is a
  defensive robustness path normalized by rounding to the nearest millisecond
  with carry. `seek()` returns the exact numeric seconds without re-rounding.
- Invalid input (negative, NaN, infinite, non-numeric string) throws
  `InvalidArgumentException`; no product-visible fallback text is produced. This
  is the minimum deterministic behavior chosen per the contract.
- Active segment: pure function, `start_seconds <= currentTime < end_seconds`,
  ordered by `segment_index` (not array position); lowest index wins on overlap.
- Workspace: `mediaPlayer` from `MediaFile::hasPhysicalFile()`;
  `transcriptInteraction`/`search`/`copy` require completed + segments; `export`
  requires completed; `retry` reflects the failed-state retry surface (actual
  eligibility remains owned by Phase 3 `TranscriptionRetry::isEligible`).
- No-speech: completed + `speech_detected === false` + empty `full_text` + no
  segments.

### Known Limitations

- The existing `TranscriptionExportController` and
  `TranscriptionSegment::getFormattedStartAttribute` still use their own
  timestamp formatting; they were intentionally not rewired to the new primitive
  to avoid P4-005 export behavior changes. P4-003/P4-005 may consume the new
  primitive when authorized.
- `retry` is a surface flag only; it does not evaluate Phase 3 retry eligibility.

## Verification

Implementation owner must record the commands executed and results.

Commands executed on 2026-09-19 (PHP 8.4, Pest 5.1):

- `vendor/bin/pest tests/Unit/TranscriptExperience tests/Feature/TranscriptExperience`
  → 26 passed, 113 assertions.
- `php artisan test --compact` (full suite) → 392 tests, 391 passed, 1 skipped
  (pre-existing two-factor 2FA skip), 1265 assertions, 2 warnings (pre-existing
  baseline; a transient 3rd warning from a redundant global import was removed).
- `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress` → 0 errors.
- `vendor/bin/pint app/TranscriptExperience tests/Unit/TranscriptExperience tests/Feature/TranscriptExperience --format agent`
  and `vendor/bin/pint --dirty --format agent` → passed.

Result:

PASSED

## Review

Review File: `reviews/P4-001-independent-review.md`

Review Status: VERIFIED (independent review, 2026-09-19). No BLOCKER/HIGH/
MEDIUM findings. Non-blocking: LOW-1 (redundant `segments()->exists()` query
when `for()` and `isNoSpeech()` are both called on the same instance), LOW-2
(no direct unit test for `ActiveSegmentResolver`'s missing-key exception
branch). All reported quality gates (focused suite 26/113, full suite
392/391/1/1265/2, PHPStan 0 errors, Pint clean) were independently
reproduced with an exact match. Confirmed no route, controller, view,
JavaScript, or schema change; the new `app/TranscriptExperience/*` primitives
are not referenced anywhere outside their own files and tests. Eligible for
Human Product Owner closure to DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-19
(DECISION-P4-001-CLOSURE-001), based on the completed independent review
(`reviews/P4-001-independent-review.md`: P4-001 = VERIFIED; no BLOCKER/HIGH/
MEDIUM; LOW-1 and LOW-2 non-blocking and retained as historical observations).

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

History preserved, not rewritten:

```text
BACKLOG
→ HPO authorization (DECISION-P4-001-AUTHORIZATION-001)
→ READY
→ implementation
→ REVIEW
→ independent review: VERIFIED (LOW-1, LOW-2)
→ HPO closure
→ DONE
```

Non-blocking findings remain historical and are not promoted into scope:

- LOW-1: `WorkspaceAvailability::for()` and `::isNoSpeech()` each query
  `segments()->exists()` when both are called on the same instance (redundant
  query; no correctness impact).
- LOW-2: no direct unit test for `ActiveSegmentResolver::value()`'s missing-key
  exception branch (not required by the contract; malformed/overlap inputs are
  not expected under Phase 3 invariants).

Closure is governance/state reconciliation only; no implementation, test, or
migration change is authorized by this closure. It does not authorize P4-002,
P4-004, or P4-005; those become eligible for separate implementation
authorization only. P4-003 remains gated on P4-002 DONE; P4-006 remains gated on
all feature tasks DONE.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-002, P4-004, and
P4-005 depend on this task being VERIFIED and closed DONE.
