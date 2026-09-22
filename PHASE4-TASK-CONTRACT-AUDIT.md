# Phase 4 — Task Contract Audit

Date: 2026-09-19
Status: AUDIT COMPLETE — contracts finalized; P4-001..P4-006 DONE; Phase 4 CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); Phase 5 NOT AUTHORIZED
Authority: ADR-019 (ACCEPTED); `DECISION-PHASE4-AUTHORIZATION-001`;
`PHASE4-PLANNING.md`; `.ai/guidelines/orchestration-policy.md`

This audit finalizes the implementation-ready contracts for P4-001 through
P4-006. It does not authorize implementation, does not promote any task to
READY, and does not change application code, tests, migrations, configuration,
routes, controllers, components, views, or JavaScript.

## A. State Confirmed

Verified against the repository:

- ADR-019 = ACCEPTED (D4-01..D4-07 decided).
- `DECISION-PHASE4-AUTHORIZATION-001` = DECIDED; Phase 4 authorized for
  task-contract authoring only.
- P4-001..P4-006 = BACKLOG.
- Phase 4 implementation = NOT AUTHORIZED; no P4 task READY.
- Test tooling: Pest only (no Dusk/Playwright); no `<audio>`/`<video>` element
  exists yet.
- Export already reads persisted segments and is Completed-gated
  (`TranscriptionExportController`); `tests/Feature/TranscriptExportTest.php`
  exists.
- Media is private via `MediaFile::storage()` and `MediaFilePolicy`
  (owner-or-admin); no streaming route exists (download only).
- Segment timestamps persisted at `decimal(12,3)`.
- No P4 contract proposes a schema change.

## B. Contract Audit Summary

| Task | Finalized | Dependency | Notes |
|---|---|---|---|
| P4-001 | yes | — | Implements shared baseline primitives; specifies later surfaces |
| P4-002 | yes | P4-001 DONE | Full HTTP range/security contract |
| P4-003 | yes | P4-002 DONE | Browser binding + active-segment + auto-scroll |
| P4-004 | yes | P4-001 DONE | Search/copy semantics + injection safety |
| P4-005 | yes | P4-001 DONE | Hardening existing exports; source-of-truth defined |
| P4-006 | yes | P4-002/003/004/005 DONE | V4-01..V4-25 matrix; browser strategy |

All six remain BACKLOG.

## C. Resolved Task-Level Details

These are task-level UX/technical details resolved consistently with ADR-019
(not new product decisions):

- Canonical timestamp: display `H:MM:SS.mmm` (or `MM:SS.mmm`), seek numeric =
  exact persisted seconds, SRT `HH:MM:SS,mmm`, VTT `HH:MM:SS.mmm`, with
  millisecond carry (no `1000`).
- Active segment: `start_seconds <= currentTime < end_seconds`; gaps/before/
  after → none; zero-length → never; overlap → lowest `segment_index`.
- Auto-scroll: scroll only when the active segment is not already visible;
  suspend on manual transcript scroll until the next playback interaction.
- Search: client-side, case-insensitive Latin, substring, Unicode-safe,
  wrapping navigation, safe text-node highlighting.
- Copy: plain text; full transcript = newline-joined ordered segments, no
  timestamps; segment copy = text only.
- Export source-of-truth: TXT/DOCX = title + segments (fallback `full_text`);
  SRT = segments only; VTT = header + segments.
- Media delivery: opaque `MediaFile` UUID route; single-range support; 206/416
  semantics; malformed/multi-range → `200` (ignored); chunked streaming.

## D. Dependency Model

```text
P4-001 (DONE)
  ├─→ P4-002 (DONE) → P4-003 (DONE)
  ├─→ P4-004 (DONE)
  └─→ P4-005 (DONE)

P4-002 + P4-003 + P4-004 + P4-005 (all DONE)
  └─→ P4-006 execution and VERIFIED
```

- "DONE" means HPO closure after independent VERIFIED, per the orchestration
  policy. Implementation-complete but still in REVIEW does not satisfy a
  dependency.
- No Phase-3-style batch-review exception (D4-07).

## E. Browser Verification Strategy

- Resolver, gating, search state, copy content, and exports are covered by
  unit/feature/Livewire tests.
- Real `HTMLMediaElement` seek/playback/highlight and clipboard behavior cannot
  be proven by backend tests. The repository has no browser automation today.
- P4-006 requires a reproducible, environment-dependent browser verification
  with retained evidence, or a lightweight browser-test tool only if explicitly
  justified and authorized before implementation.
- Backend-only evidence must never be represented as proving browser behavior.

## F. Security / Privacy Requirements

- Unauthenticated and cross-user media access denied; authorization before any
  storage read.
- Opaque UUID route identity; no filesystem path in URL/headers/body; no
  path traversal.
- Range headers cannot bypass authorization; malformed ranges cannot cause
  unbounded memory use.
- No whole-file PHP memory load; bounded chunk streaming.
- No private-storage contract change.

## G. Language / No-Speech Requirements

- Per-segment language (`ms`, `en`, `zh`, `ta`, `und`) preserved across display,
  search, copy, and export; never mutated; no translation.
- No-speech (`completed`, `speech_detected=false`, `text=""`, no segments) is a
  valid workspace: explicit empty state; search/copy unavailable; exports valid
  (TXT/DOCX fall back to empty `full_text`; SRT empty; VTT header-only).

## H. Risks / Mitigations

Captured per task. Key: path leakage, range math errors, whole-file memory
reads, unauthorized streaming (P4-002); boundary jitter, inaccurate seek,
per-frame DOM loops, highlight collision (P4-003); Unicode behavior, DOM
injection, clipboard failure (P4-004); Unicode corruption, timestamp rounding,
DOCX regression (P4-005); false browser claims, missing video, incomplete
regression (P4-006).

## I. Outstanding Owner Decisions

1. Browser verification tooling: authorize a lightweight browser-test tool for
   P4-003/P4-004/P4-006, or accept environment-dependent browser evidence
   recorded per run. This does NOT block P4-001 (which needs only shared
   baseline primitives and non-browser tests). It must be resolved before
   implementation/review of browser-dependent Phase 4 work, particularly P4-003
   and ultimately P4-006.

No other unresolved owner decision remains for the contracts. No schema change
is requested by any P4 task.

## I-1. P4-001 Reconciliation (2026-09-19)

P4-001 was reconciled and authorized under `DECISION-P4-001-AUTHORIZATION-001`.
The timestamp contract now separates persisted `decimal(12,3)` fixtures (source
of truth, at most millisecond precision) from defensive formatter robustness
input (higher precision, normalized by rounding with carry). P4-001 = READY;
P4-002..P4-006 remain BACKLOG.

## J. Files Updated

- `tasks/P4-001-transcript-experience-contract.md`
- `tasks/P4-002-authorized-private-media-playback.md`
- `tasks/P4-003-segment-navigation-synchronized-highlighting.md`
- `tasks/P4-004-transcript-search-copy.md`
- `tasks/P4-005-export-hardening.md`
- `tasks/P4-006-phase4-integration-verification.md`
- `PHASE4-TASK-CONTRACT-AUDIT.md` (this file)

No application code, test, migration, configuration, route, controller,
component, view, or JavaScript was changed. No task was promoted to READY.

## K. Recommended Next Authorization

EXECUTED (2026-09-19): P4-001 implementation was authorized under
`DECISION-P4-001-AUTHORIZATION-001` and P4-001 was promoted BACKLOG → READY.

Do not authorize P4-002+ implementation ahead of dependency closure (P4-001
DONE).

## L. Explicit Non-Actions

- No Phase 4 implementation.
- No task promoted to READY (all P4 tasks remain BACKLOG).
- No translation.
- No Phase 5/6/7 implementation.
- No reviewer artifact modified.
- No commit.
