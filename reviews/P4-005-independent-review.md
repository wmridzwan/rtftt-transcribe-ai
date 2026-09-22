# P4-005 — Independent Review: Export Hardening

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-20
Scope: `tasks/P4-005-export-hardening.md` only. This review does not implement
fixes, does not modify implementation code or tests, does not mark P4-005
DONE, and does not authorize P4-003 or P4-006. It is independent of the
P4-002 and P4-004 reviews conducted alongside it in the same session; each
task's verdict stands on its own contract.

## 1. Contract Checked

`tasks/P4-005-export-hardening.md` (Status at review start: `REVIEW`;
authorized under `DECISION-P4-WAVE1-AUTHORIZATION-001`; dependency P4-001 =
DONE, satisfied).

## 2. Implementation Inspected

- `app/Http/Controllers/TranscriptionExportController.php` (full file read;
  all four export methods).
- `app/TranscriptExperience/SegmentTimestamp.php` (full file read, shared with
  P4-001's independently-VERIFIED review).
- `tests/Feature/TranscriptExportHardeningTest.php` (full file read, 11
  tests).
- `tests/Feature/TranscriptExportTest.php` (pre-existing coverage, run
  alongside for regression).

## 3. Single Timestamp Source of Truth (Critical Surface)

Grepped `app/` for `formatSrtTime`, `formatVttTime`, and any hand-rolled
`sprintf`-based SRT/VTT timestamp formatting outside `SegmentTimestamp` —
**zero matches**. `exportSrt()` and `exportVtt()` both call
`SegmentTimestamp::fromSeconds($segment->start_seconds)->srt()` /`->vtt()`
(and the same for `end_seconds`) — this is the same canonical primitive
already independently reviewed and VERIFIED under P4-001
(`reviews/P4-001-independent-review.md` §3). **Confirmed: Phase 4 now has
exactly one canonical timestamp primitive for timed export; the previously
duplicate `formatSrtTime`/`formatVttTime` methods were removed, not merely
superseded.** The unrelated display accessor
(`TranscriptionSegment::getFormattedStartAttribute`) is unchanged, and the
task explicitly scopes that migration out (P4-001's own "Known Limitations"
already disclosed this deferral) — correctly not required here.

## 4. Export Source of Truth

Read all four export methods in `TranscriptionExportController.php`:

- **TXT**: `$transcription->title` + (`$segments->pluck('text')->implode("\n\n")`
  if segments exist, else `$transcription->full_text ?? ''`). Matches the
  contract (title + ordered persisted segments, `full_text` fallback). The
  contract does not mandate a specific inter-segment separator for TXT/DOCX
  (unlike the single-newline rule specified for *copy* in P4-004); the
  double-newline paragraph separator used here is a reasonable, contract-
  consistent choice and not the same requirement as copy's single-newline
  rule — not a defect.
- **DOCX**: mirrors TXT's source-of-truth logic (`addTitle` + per-segment
  `addText`, or `full_text` fallback when no segments). Matches.
- **SRT**: iterates `$segments` only; `$segments->isEmpty()` naturally
  produces an empty string (the loop body never executes) — no `full_text`
  fallback, matching the contract's explicit "SRT: ordered persisted segments
  only; no segments → empty output."
- **VTT**: `"WEBVTT\n\n"` header is unconditional, then the same
  segments-only loop — no-segments case naturally yields header-only output.
  Matches.

All four methods call `$transcription->segments()->orderBy('segment_index')->get()`
**explicitly, at each call site** — this is deterministic ordering by
`segment_index` proven directly at the query level, not incidentally reliant
on the relationship's default ordering (see also P4-004's review §8, which
independently confirms the relationship itself is also ordered). Confirmed by
the passing `orders exported segments deterministically by segment_index`
test, which inserts segments out of index order (`2, 0, 1`) and asserts the
SRT output preserves index order — a genuine, non-trivial regression check.

## 5. No Provider Re-Invocation

Traced all four controller methods: each only calls `$this->authorize(...)`,
reads `$transcription->status`, `$transcription->segments()`,
`$transcription->title`, and `$transcription->full_text` — no
`TranscriptionProvider`, queue `dispatch()`, or worker/job class is
referenced anywhere in this controller. The
`never invokes the transcription provider during export` test mocks
`TranscriptionProvider::class` with `shouldNotReceive('transcribe')` and
exercises all four formats — independently reproduced passing (§8).
**Confirmed: exports are deterministic from persisted state and cannot
trigger re-transcription.**

## 6. Unicode

`preserves multilingual unicode across all formats` asserts `ms`/`en`/`zh`/`ta`
segment text is present verbatim in TXT, SRT, and VTT output, and — for
DOCX — opens the returned bytes as a real `ZipArchive`, extracts
`word/document.xml`, and asserts the same Unicode needles are present inside
the actual OOXML document body (not merely that the outer byte stream is
non-empty). This is a meaningful, structurally-valid assertion: a corrupted
or mis-encoded DOCX would either fail `ZipArchive::open()` or fail to contain
the literal Unicode substrings inside `document.xml`. `und` (the fifth
supported language code) is exercised as a literal segment text value in the
same test (`addExportSegment(..., 'und', 'und')`) — the language *code*
itself is not separately asserted to survive export headers, but per the
Export Contract, exported SRT/VTT/TXT/DOCX bodies do not carry
per-segment language metadata at all (only text and timestamps), so this is
consistent with the contract, not a gap.

## 7. No-Speech

For a `completed` transcription with `speech_detected=false`,
`full_text=''`, no segments:

- TXT: title + `\n\n` + `''` → title-only, no error. Test asserts `toContain('No Speech')` (the title).
- SRT: empty loop body → `''`. Test asserts `toBe('')` — a strong,
  unambiguous assertion.
- VTT: header-only. Test asserts `toStartWith('WEBVTT')` — reasonably strong
  given the code path (header string is emitted once, unconditionally,
  before the empty segment loop; there is no way for this code to produce
  content starting with `WEBVTT` and then trailing extra content in the
  no-segments case).
- DOCX: title added, then `addText('')` for the empty `full_text`. Test only
  asserts `expect(strlen($docx))->toBeGreaterThan(0)`.

**Gap found:** the DOCX no-speech assertion is too weak to actually verify
"title only, no error" — a valid non-empty `.docx` byte stream is produced by
`PhpWord`/`PhpOffice` regardless of whether the title or body content is
correct (the OOXML zip skeleton itself is never zero bytes), so this
assertion would not catch a regression where the title was dropped, wrong, or
where unexpected body content leaked in. Source-code inspection (§4)
confirms the actual behavior is correct; the test itself just does not prove
it as rigorously as the SRT/VTT/TXT assertions in the same test function do.
Recorded as LOW-1.

## 8. Authorization / Lifecycle

- Non-completed export denied: `denies export for non-completed
  transcriptions across all formats` creates a `transcribing()` transcription
  and asserts all four routes return 403. Independently reproduced passing.
- Cross-user denied: `denies cross-user export` asserts all four routes
  return 403 for a non-owning authenticated user. Independently reproduced
  passing.
- Existing routes/content-types/filenames preserved: `keeps export filenames
  and content types correct` asserts the exact pre-existing `Content-Type`
  values and a slugified filename in `Content-Disposition` for all four
  formats — unchanged from the pre-existing `TranscriptExportTest.php`
  baseline, confirming no route/format contract regression.

## 9. Timestamp Boundaries

Independently re-verified via direct calculation (consistent with the
already-VERIFIED P4-001 review of the same primitive):

- `5.999` → `5999`ms → `00:00:05,999` / `00:00:05.999` — no carry needed.
- `6.000` → `6000`ms → `00:00:06,000` — exact whole second, zero-padded
  correctly.
- `65.123` → `65123`ms → `1`min `5`sec `123`ms → `00:01:05,123`.
- `3600.001` → `3,600,001`ms → `1`hr `0`min `0`sec `1`ms → `01:00:00,001`.

`formats millisecond timestamps for SRT and VTT without overflow` in
`TranscriptExportHardeningTest.php` exercises `5.999`→`6.999` (a genuine
near-carry boundary pair) and explicitly asserts the absence of `,1000`/`.1000`
in both outputs. This defensive-formatter guarantee was already proven
structurally (not just by these four samples) in the P4-001 review — `round()`
always carries into whole seconds before `parts()` divides by 1000, so
`.1000`/`,1000` cannot be emitted by *any* input to this shared primitive, and
P4-005 inherits that guarantee for free by reusing it rather than
reimplementing it (§3).

## 10. Test Reproduction

Independently re-executed (not read from the report):

```
vendor/bin/pest tests/Feature/TranscriptExportHardeningTest.php tests/Feature/TranscriptExportTest.php
→ 21 passed, 116 assertions
```

Exact match to the implementer's reported figures. Ordering, Unicode,
no-speech, completed-gating, cross-user denial, filenames, and content types
are all backed by meaningful assertions per the analysis above; the one
exception is the DOCX no-speech assertion (LOW-1, §7), which is present but
weak.

## 11. Cross-Task Scope Audit

- No new export format added or removed; the four existing routes
  (`transcriptions.export.{txt,srt,vtt,docx}`) and their controller methods
  are unchanged in signature and are not rewritten — only the SRT/VTT
  timestamp-formatting internals were touched, exactly as scoped.
- No schema/migration change (confirmed via `git status`; no migration is
  associated with this task's diff).
- No transcript/segment editing, no translation, no unrelated export
  architecture redesign found anywhere in the diff.
- P4-005's footprint is exactly `TranscriptionExportController.php` (SRT/VTT
  methods only) and the new `TranscriptExportHardeningTest.php` — matching
  its own "Files Changed" section precisely.

## 12. Shared Regression

`tests/Feature/TranscriptExportTest.php` (pre-existing Phase-4-baseline
coverage, not authored by this task) was run in the same invocation as the
new hardening suite and passed with no failures, confirming no regression to
the pre-existing export contract. See the Wave 1 summary report (§D) for the
full-suite reproduction.

## 13. Findings Summary

| ID | Severity | Finding | Blocking? |
|----|----------|---------|-----------|
| LOW-1 | LOW | The no-speech DOCX regression test only asserts `strlen($docx) > 0`, which any valid (even incorrect-content) `.docx` archive would satisfy; it does not verify the archive actually contains title-only content as the contract requires. Source inspection independently confirms the actual implementation is correct. | No |

No BLOCKER, HIGH, or MEDIUM finding was identified.

## 14. Acceptance Criteria Assessment

All 15 acceptance criteria were checked against the traced control flow and
the independently reproduced tests (§3–§10). All are satisfied. AC9
(no-speech) is satisfied by source-code proof for all four formats even
though the DOCX-specific test assertion is weaker than the others (LOW-1).

## 15. Final Verdict

```text
P4-005 = VERIFIED
```

No BLOCKER, HIGH, or MEDIUM finding. LOW-1 is non-blocking and recorded as a
historical observation / candidate follow-up test hardening. P4-005 is
eligible to return to the Human Product Owner for closure consideration. This
review does not itself authorize P4-003 or P4-006.
