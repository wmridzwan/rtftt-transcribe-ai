# P4-001 — Independent Review: Transcript Experience Contract

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-19
Scope: `tasks/P4-001-transcript-experience-contract.md` only. Does not
implement fixes, does not modify implementation code, does not mark P4-001
DONE, does not authorize P4-002..P4-006.

## 1. Executive Summary

P4-001 implements exactly the four shared baseline primitives the contract
specifies (`SegmentTimestamp`, `ActiveSegmentResolver`, `WorkspaceState`,
`WorkspaceAvailability`) under `app/TranscriptExperience/`, with matching
unit/feature tests. All primitives are pure/read-only, are not wired into any
route, controller, view, or JavaScript, and do not touch the database schema.
Independent re-execution reproduces every reported quality-gate result
exactly.

**Verdict: P4-001 = VERIFIED**, eligible to return to the Human Product Owner
for closure consideration.

## 2. Authorization / Scope

- `tasks/P4-001-transcript-experience-contract.md` confirms Status `REVIEW`,
  authorized under `DECISION-P4-001-AUTHORIZATION-001` (BACKLOG → READY).
- Grepped the entire `app/`, `resources/`, and `routes/` trees for
  `TranscriptExperience`: matches only inside the four new
  `app/TranscriptExperience/*.php` files themselves. No controller, view,
  route, or other application file references the new namespace — confirms
  the primitives are inert (specified-but-not-yet-consumed), satisfying AC11
  ("no route, controller, view, JavaScript, or schema change").
- No new migration file is associated with this task; the migrations present
  in the working tree (`2026_09_18_*`, `2026_09_19_000001/2`) are Phase 3
  Batch 2/3 artifacts (segment language/index, `failure_code`, active-attempt
  unique index) already independently reviewed under P3-005/P3-006/P3-007,
  not part of this diff's scope.
- `git status` shows the task's own footprint is exactly the eight files
  listed in the task's "Files Changed" section (4 new `app/TranscriptExperience`
  classes + 4 new test files) plus the task file itself — no other
  application file is newly modified by this work.

**No unauthorized scope expansion found.**

## 3. Canonical Timestamp Primitive (`SegmentTimestamp`)

Read `app/TranscriptExperience/SegmentTimestamp.php` in full.

- `fromSeconds()` accepts `int|float|string`, rejects non-numeric strings,
  `NaN`, infinite, and negative values via `InvalidArgumentException` —
  matches the task's own recorded decision ("no product-visible fallback
  text is produced").
- `totalMilliseconds` is computed once via `round($value * 1000)` and reused
  by `display()`, `srt()`, and `vtt()` through a shared `parts()` decomposition
  — a single canonical derivation, satisfying AC1 ("one canonical formatter").
- Millisecond carry: verified by direct calculation that `5.9996 * 1000 =
  5999.6 → round → 6000ms`, which decomposes to `0h 0m 6s 0ms` — matches
  AC3 exactly (`5.9996` → `00:06.000` / `00:00:06,000` / `00:00:06.000`).
  Independently re-ran `php -r` arithmetic to confirm no `1000`ms overflow
  case is possible: `round()` on any `x.9995`–`x.9999` input always carries
  into whole seconds before `parts()` ever divides by 1000, so `.1000`/`,1000`
  cannot be emitted structurally, not just by the four tested samples.
- `seek()` is populated once at construction and is never rederived from
  `totalMilliseconds` — for `string`/`int` input it returns the original
  value verbatim (trimmed), and for non-integer `float` input it uses
  `json_encode()` (PHP's shortest-round-trip serializer, locale-independent)
  rather than `sprintf`, which avoids both locale decimal-separator bugs and
  re-rounding. This correctly implements AC5 ("seek is not re-rounded") even
  though `display()`/`srt()`/`vtt()` on the *same instance* would show the
  carried/rounded value for defensive high-precision input — confirmed this
  is the documented, intentional split between the exact-seek and
  carry-normalized-display paths (Important Decisions in the task file), not
  an inconsistency.
- Independently verified with `php -r` that `json_encode()` round-trips every
  `decimal(12,3)`-scale test value used in the suite (`0.001`, `0.815`,
  `1.005`, `65.123`, `3600.001`, `5.999`, `3599.999`) exactly, and that
  trailing-zero forms (`12.340`, `100.100`, `0.100`) correctly collapse to
  their numeric equivalent (`12.34`, `100.1`, `0.1`) — consistent with the
  spec's "up to three decimals" seek contract, not a defect.
- `TranscriptionSegment::start_seconds`/`end_seconds` are cast to PHP `float`
  by the Eloquent model (confirmed by reading `app/Models/TranscriptionSegment.php`),
  so the production consumption path for real segment rows goes through the
  `float` branch of `seekString()`, not the `string` branch exercised by most
  unit tests; the float-branch behavior was separately verified via direct
  `json_encode` round-tripping above, so this does not undermine the AC2/AC5
  claims.

**No correctness defect found.**

## 4. Active-Segment Resolver

Read `app/TranscriptExperience/ActiveSegmentResolver.php` in full and traced
each branch against §Active Segment of the contract:

- `start <= currentTime < end` is implemented literally, with `usort` by
  `segment_index` (never array position) before the linear scan, and the
  first match in index order returned — correctly implements the "lowest
  `segment_index` wins on overlap" tie-break (AC6) independent of caller
  ordering.
- Zero-length segments (`start == end`) can never satisfy `start <= t < end`
  for any `t` (the only candidate `t = start` fails `t < end` since
  `end == start`) — structurally, not just empirically, unable to be active.
- Gaps, before-first, after-last: the scan simply finds no matching row and
  returns `null`; no default/fallback segment is returned.
- Accepts both array and Eloquent-model-shaped (`object`) segments via a
  `value()` helper that throws on a genuinely missing key rather than
  silently defaulting — appropriate for a pure function with no defined
  behavior for malformed input, and consistent with the contract's "not
  expected under Phase 3 invariants" framing for overlap/malformed cases.

Test file (`ActiveSegmentResolverTest.php`) directly covers: normal,
exact-start (active), exact-end (inactive), gap, zero-length, overlap
tie-break, before-first, after-last, array-position-independent ordering, and
Eloquent-object-shaped input — matches every case enumerated in the Test
Requirements section.

**No correctness defect found.**

## 5. Workspace Availability

Read `app/TranscriptExperience/WorkspaceAvailability.php` and
`WorkspaceState.php` in full, and cross-checked
`app/Enums/TranscriptionStatus.php` (7 cases: `draft`, `queued`, `preparing`,
`transcribing`, `completed`, `failed`, `cancelled` — exactly the 7 rows of
the contract's matrix).

- `mediaPlayer` is derived from `MediaFile::hasPhysicalFile()` unconditionally
  of transcription status — correctly matches every matrix row's "available
  when a physical media file exists" (the matrix applies this uniformly
  across all seven statuses, and the implementation does not gate it on
  status at all).
- `transcriptInteraction`/`search`/`copy` require `completed && hasSegments`;
  `export` requires only `completed`; `retry` requires only `status ===
  Failed`. This is an exact, direct translation of every cell in the
  Completed-Only Workspace Contract table, including the completed-with-
  segments vs. completed-without-segments split and export's "always
  available when completed" independence from segment presence.
- `WorkspaceAvailabilityTest.php` exercises all 7 statuses: the five
  non-completed non-failed statuses (`draft`, `queued`, `preparing`,
  `transcribing`, `cancelled`) grouped in one parametrized test, `failed`
  separately (retry surface), and `completed` in two variants (with/without
  segments) plus a dedicated media-player test. This is a complete,
  1:1 mapping onto AC7's "all seven statuses" requirement — confirmed no row
  of the matrix is untested.
- No-speech: `detectNoSpeech()` requires `completed && speech_detected ===
  false && (full_text null or empty) && !hasSegments` — matches AC9 exactly
  (the implementation is slightly more permissive than the literal spec text
  by also treating `null` `full_text` as empty, which is a reasonable,
  non-weakening generalization of "empty transcript," not a scope violation).
- Every helper method takes a `Transcription` and returns state only; none
  mutates the model, confirming the Read-Only Guarantee (§Read-Only
  Guarantee) is respected by this task's own code.

One non-blocking observation: `WorkspaceAvailability::for()` and
`::isNoSpeech()` each independently call `$transcription->segments()->exists()`
when used together on the same instance (as `NoSpeechTest.php` does) —
duplicate query, not a correctness issue for a stateless helper at this
scale. See Findings.

**No correctness defect found.**

## 6. Reproduced Quality Gates

All commands independently re-executed in this review session using the
project's PHP 8.4 binary (`C:/Users/Admin/.config/herd/bin/php84/php.exe`),
not re-read from the implementer's report:

| Gate | Command | Reproduced Result | Matches Reported? |
|---|---|---|---|
| Focused TranscriptExperience suite | `vendor/bin/pest tests/Unit/TranscriptExperience tests/Feature/TranscriptExperience` | 26 passed, 113 assertions | Yes, exact |
| Full PHP suite | `artisan test --compact` | 392 total / 391 passed / 1 skipped / 1265 assertions / 2 warnings | Yes, exact |
| PHPStan | `phpstan analyse --memory-limit=1G` | 0 errors | Yes, exact |
| Pint | `pint app/TranscriptExperience tests/Unit/TranscriptExperience tests/Feature/TranscriptExperience --test --format agent` | passed | Yes |

The 1 pre-existing skip (two-factor authentication, intentionally disabled)
and 2 warnings are the same pre-existing Batch-1 baseline referenced
throughout `CURRENT_STATE.md`, not new to this task.

## 7. Regression / Preservation Check (AC12)

- No file under `app/Http/Controllers/`, `app/Transcription/`, `app/Jobs/`,
  `app/Actions/`, `resources/views/transcriptions/`, or `routes/web.php` was
  found to reference or depend on the new `TranscriptExperience` namespace
  (§2). Phase 3 queue/retry/failure/language/no-speech/persistence/media
  contracts are therefore structurally unreachable from this diff.
- Full-suite reproduction (392/391/1/1265, §6) includes the entire Phase 3
  Batch 1/2/3 regression surface and shows no new failure.
- The task's own "Known Limitations" section discloses, and this review
  confirms by inspection, that `TranscriptionExportController` and
  `TranscriptionSegment::getFormattedStartAttribute` still use their own
  independent timestamp formatting rather than the new `SegmentTimestamp`
  primitive. This is explicitly in scope as deferred to P4-005 per the
  Implements-vs-Specifies split, not an omission of this task.

## 8. Findings Summary

| ID | Severity | Finding | Required Action | Blocking? |
|----|----------|---------|------------------|-----------|
| LOW-1 | LOW | `WorkspaceAvailability::for()` and `::isNoSpeech()` each independently query `segments()->exists()` when both are called on the same transcription instance (as in `NoSpeechTest.php`), causing a redundant query. No correctness impact at current scale. | Optional future refactor (e.g., accept a pre-fetched segment-existence flag) if this helper is called in a hot path by a later Phase 4 task. | No |
| LOW-2 | LOW | `ActiveSegmentResolver::value()`'s missing-key `InvalidArgumentException` branch has no direct unit test. Not required by the contract's Test Requirements (malformed/overlap cases are explicitly "not expected under Phase 3 invariants"), and the DB now enforces a unique `segment_index` per transcription (P3-005 migration), further reducing real-world relevance. | Optional. | No |

No BLOCKER, HIGH, or MEDIUM finding was identified.

## 9. Final Verdict

```text
P4-001 = VERIFIED
```

P4-001 is eligible to return to the Human Product Owner for closure
consideration. P4-002, P4-004, and P4-005 remain gated on P4-001 being
closed DONE by the Human Product Owner; this review does not itself
authorize their implementation.
