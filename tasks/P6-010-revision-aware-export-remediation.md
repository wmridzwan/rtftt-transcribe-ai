# P6-010 — Revision-Aware Export Remediation (F-001)

## Status

DONE — closed by the Human Product Owner on 2026-09-25
(`DECISION-P6-010-CLOSURE-001`) on the basis of the fresh independent review
VERIFIED verdict (`reviews/P6-010-INDEPENDENT-REVIEW.md`; all AC1–AC11 PASS;
no BLOCKER/MAJOR/MINOR/OPTIONAL). Prior status `DRAFT` (2026-09-25 contract
candidate) → `READY` (HPO approval HPO-P6-010-A, `DECISION-P6-010-READY-001`)
→ `IN_PROGRESS` (HPO-authorized Builder execution) →
`IMPLEMENTED_PENDING_REVIEW` (implementation + tests + Builder report
complete) → `VERIFIED` (fresh independent review,
`reviews/P6-010-INDEPENDENT-REVIEW.md`) → `DONE` (HPO closure).

Task type: bounded Phase 6 remediation (owns F-001 only).

Contract approval and READY promotion authorize the task to be opened for
implementation assignment; implementation itself requires a separate
Builder-execution authorization. P6-009 remains IN_PROGRESS with execution
verdict FAIL. Phase 6 remains OPEN.

## Ownership

Implementation Owner: OpenCode (Builder execution 2026-09-25; must not
self-review or self-close).
Reviewer: UNASSIGNED — independent review required (Claude Code per AGENTS.md
agent model; must be independent of the implementer).

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025). Remediation of
HPO-accepted gate finding F-001 under HPO-F001-A (DECIDED: REMEDIATE).

## Objective

Make the supported transcript exports (TXT/SRT/VTT/DOCX) derive content from
the canonical current active revision, in accordance with the frozen Phase 6
revision/export contract, while preserving approved machine-source
fallback/recoverability semantics and all existing authorization and gating.

F-001 root cause (evidence:
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` §4):
`TranscriptionExportController::{exportTxt,exportSrt,exportVtt,exportDocx}`
read `$transcription->segments()->orderBy('segment_index')` — the immutable
machine source only — and never consult the active revision. Exports therefore
return machine-original text/timing even when an edited active revision is
authoritative, contradicting P6-001 §9. All four formats were proven affected
by a throwaway probe (4/4 FAIL; probe deleted after capture).

## Authority Matrix

| Requirement | Authority | Classification |
|---|---|---|
| When an active revision exists it is authoritative for presentation, copy, and export (order, text, language, timing); when no active revision exists the machine source remains authoritative; machine original always recoverable | `PHASE6-EDITING-DOMAIN-CONTRACT.md` §9 (frozen P6-001 inputs) | EXPLICIT |
| F-001 is a genuine Phase 6 gap; remediate via a dedicated bounded task; do not narrow P6-009 AC6; P6-009 stays failed/incomplete until remediation DONE + gate rerun | HPO-F001-A (DECIDED: REMEDIATE); P6-009 evidence §4/§13 | EXPLICIT |
| Revision segment identity/ordering (`position` contiguous `0..n-1`; order by `position`, never timestamps); timing invariants (§5); navigation over active revision identity | Frozen domain contract §§4–5, §8 | EXPLICIT |
| Existing export surface: `TranscriptionExportController` TXT/SRT/VTT/DOCX; `view` authorization; Completed-only gating; deterministic ordered segments; no provider inference; routes `transcriptions.export.{txt,srt,vtt,docx}` | P4-005 contract; `TranscriptionExportController.php` | EXPLICIT |
| Active-revision projection precedent (revision `orderedSegments()` → display rows with position/text/timing/language; machine fallback when null) | `TranscriptionController::displaySegments()` | EXPLICIT |
| No translation/revision export changes beyond reading the active revision | P6-002 contract (out-of-scope line) | EXPLICIT |
| Canonical export set is exactly TXT/SRT/VTT/DOCX; no new formats | ADR-019 (D4-03); P4-005 | EXPLICIT |
| Translation exports (`TranslationExportController`, `translation_segments`) are a separate surface reading persisted translations | P5-007 contract | EXCLUDED (not F-001) |
| Lifecycle READY → IN_PROGRESS → REVIEW → VERIFIED → DONE; no self-verify/close; max 3 CHANGES_REQUESTED cycles | `.ai/guidelines/orchestration-policy.md` | EXPLICIT |
| New revision/translation semantics, D6-08/D6-09, Phase 7 hardening inside this task | No authority; contradicts bounded remediation | EXCLUDED |

## Dependencies

Requires (all satisfied except HPO promotion):

- P6-001 DONE (frozen §9 export semantics; consumed, not redefined).
- P6-002 DONE (`RevisionService::active()` resolution, `orderedSegments()`).
- P4-005 DONE (existing export surface, gating, authorization, routes).
- HPO-F001-A DECIDED (remediation path, recorded below).
- Explicit HPO READY promotion (not granted by this DRAFT).

## Scope

Implement only:

- Active-revision resolution for export (via the existing
  `RevisionService::active()` read path; no new revision semantics).
- A revision-aware segment projection for export (ordered by revision
  `position`; revision text/timing/language; millisecond `decimal(12,3)`
  timing through the existing `SegmentTimestamp` formatter).
- TXT export from the active revision (text + order).
- SRT export from the active revision (text + position order + active
  timing; valid `HH:MM:SS,mmm` structure, no `,1000`).
- VTT export from the active revision (text + position order + active
  timing; `WEBVTT` header, valid `HH:MM:SS.mmm`, no `.1000`).
- DOCX export from the active revision (same document structure as today —
  title + ordered paragraph texts — sourced from the revision).
- Machine-source fallback exactly per §Fallback below.
- Export-specific tests (all four formats × revision provenances in
  §Structural Cases) + full regression evidence (suite, Pint, PHPStan;
  browser download check for the P6-009 rerun where the rerun requires it).

## Out of Scope

Do not implement:

- revision history/persistence redesign; split/merge redesign; undo/redo or
  historical-activation changes;
- translation-staleness changes; any Phase 5 translation/translation-segment
  write (including `TranslationExportController`);
- P6-003/P6-004 retro-wire (HPO-008-C preserved);
- metadata probe wiring; upload telemetry; staging cleanup;
- D6-08/D6-09; Phase 7 (topology, deployment, supervision, security,
  monitoring); unrelated technical debt;
- general export redesign; new export formats; filename/route changes;
  gating/authorization changes.

Future ideas are not authorization.

## Canonical Data Authority

- **Active revision** (when `transcriptions.active_revision_id` resolves to a
  valid persisted revision): the sole source of truth for export content,
  order, timing, and language.
- **Machine/original transcript** (`transcription_segments` + `full_text`):
  authoritative only under the fallback condition; never mutated by this task.
- **Historical inactive revisions**: never exported directly; they affect
  export only by becoming the active revision through the existing
  (unchanged) activation path.
- The exporter must never silently use the machine source when a valid
  active revision exists.

## Structural Revision Cases

The export path must reflect whichever valid revision is currently active,
including revisions created through text edit, timing edit, split, merge,
branch-after-undo, and historical activation. Structural cases are decisive:
exporting original machine segmentation for a split/merged active revision
would produce materially incorrect output (wrong segment count, boundaries,
and alignment). Ordering is by revision `position`; machine `segment_index`
is provenance only once a revision exists.

## Historical Activation

Activating an older eligible revision changes the active revision and therefore
changes subsequent export output to that newly active revision's content —
without rewriting machine-source data and without creating a new revision.
(Activation itself is unchanged P6-008 behavior; this task only makes export
observe its result.)

## Fallback Semantics (fully determined — no HPO decision required)

Fallback is allowed if and only if no valid active revision exists
(`active_revision_id` is null or resolves to no persisted revision). In that
case the exporter uses the existing machine-source path unchanged
(`segments()->orderBy('segment_index')`, including the existing no-speech
`full_text` behavior). Defensive rule: should a zero-segment active revision
ever be encountered (not constructible via any approved operation, which
preserve ≥1 segment), the exporter must take the machine-source path rather
than emit an empty payload. Fallback must never mask a valid active revision.

## Authorization / Security

Unchanged and re-verified: `view` authorization (owner-or-admin per
`TranscriptionPolicy`), Completed-only gating (non-completed → 403),
cross-user denial on all four formats. This task must not weaken any control
and must add no new authorization surface.

## Acceptance Criteria

- [ ] AC1 — TXT export reflects the current active revision: ordered active
  text, machine title header unchanged, machine fallback only when no valid
  active revision exists.
- [ ] AC2 — SRT export reflects active revision text, position order, and
  active timing with valid `HH:MM:SS,mmm` structure (no `,1000`).
- [ ] AC3 — VTT export reflects active revision text, position order, and
  active timing with `WEBVTT` header and valid `HH:MM:SS.mmm` (no `.1000`).
- [ ] AC4 — DOCX export reflects the current active revision in the existing
  document structure (title + ordered paragraphs) and opens as a valid
  document.
- [ ] AC5 — Active revisions created by text edit, timing edit, split, merge,
  and branch-after-undo each export correctly (including changed segment
  counts/boundaries for structural cases).
- [ ] AC6 — Activating an older eligible revision changes subsequent export
  output to that newly active revision without touching machine-source data.
- [ ] AC7 — Machine-source fallback occurs only when no valid active revision
  exists; a valid active revision is never masked by fallback.
- [ ] AC8 — Machine/original rows (`transcription_segments`, `full_text`)
  are byte-identical before and after revision-aware exports; the original
  remains recoverable.
- [ ] AC9 — Authorization, cross-user isolation, and Completed-only gating
  are unchanged on all four formats (owner/admin pass; non-owner denied;
  non-completed denied).
- [ ] AC10 — Unicode/language content (`ms`, `en`, `zh`, `ta`, `und`)
  survives revision-aware export in all four formats.
- [ ] AC11 — No unrelated revision, translation, comparison, navigation, or
  Phase 7 semantics are modified (verified by diff audit + full suite).

## Verification Requirements

- New/extended export tests covering AC1–AC10 (all four formats × text,
  timing, split, merge, branch, historical-activation, fallback,
  authorization negatives, unicode).
- Unmodified-behavior evidence for AC8/AC9/AC11 (machine snapshot comparison,
  existing export hardening suites green).
- `php artisan test --compact` (full suite green), `vendor/bin/pint --dirty
  --format agent` (scoped to touched files), PHPStan level 7 with 0 errors.
- Browser/product-path export-download check commensurate with the P6-009
  rerun needs (entry points + 200 downloads; content-level proof via the
  automated tests above).
- Independent review (Claude Code) before VERIFIED; HPO closure to DONE.
- The P6-009 gate's committed F-001 skip is removed/replaced only by the
  P6-009 rerun after this remediation is DONE — not by this task.

## Failure / Rollback Expectations

- Export failures (e.g. unresolvable active revision) fail closed as errors;
  no partial file is served and no revision/translation state is mutated.
- There is nothing to roll back beyond normal error surfacing; revision and
  machine state are read-only inputs to this task.

## Review

Review Files: `reviews/P6-010-INDEPENDENT-REVIEW.md` (fresh independent
review, VERIFIED; all AC1–AC11 PASS; no BLOCKER/MAJOR/MINOR/OPTIONAL).
Builder report: `reviews/P6-010-BUILDER-REPORT.md`.
Review Status: VERIFIED → DONE (HPO closure `DECISION-P6-010-CLOSURE-001`,
2026-09-25).

## Completion

P6-010 cannot move directly to DONE. Required flow (orchestration policy):

DRAFT (contract authored 2026-09-25) → HPO review + HPO-P6-010-A approval
(APPROVED — `DECISION-P6-010-READY-001`, 2026-09-25) → READY →
IN_PROGRESS (Builder execution 2026-09-25) → IMPLEMENTED_PENDING_REVIEW
(implementation + tests + Builder report) → VERIFIED (fresh independent
review, `reviews/P6-010-INDEPENDENT-REVIEW.md`) → DONE (HPO closure
`DECISION-P6-010-CLOSURE-001`, 2026-09-25).

The implementer must not mark P6-010 VERIFIED or DONE.

## Relationship to P6-009

- P6-009 remains IN_PROGRESS with execution verdict FAIL (F-001 blocks the
  Phase 6 closure recommendation).
- Completing P6-010 does not itself make P6-009 PASS.
- After P6-010 is DONE, P6-009 must be rerun (AC6 receives fresh
  verification; other ACs revalidated per the gate contract rerun policy).
- P6-009 AC6 must not be narrowed by this task or its implementation.

## Scope Decisions (HPO)

- HPO-F001-A — DECIDED: REMEDIATE (see below). F-001 is resolved through
  this dedicated bounded task. No superseding of P6-009 AC6.
- HPO-P6-010-A — DECIDED: APPROVED (`DECISION-P6-010-READY-001`, 2026-09-25).
  P6-010 accepted as the canonical F-001 remediation; fallback semantics
  accepted as frozen-contract-derived; P6-010 promoted `DRAFT` → READY.
  Implementation not started by this decision.
- No further HPO scope/fallback decisions are open: fallback semantics are
  fully determined by frozen P6-001 §9 (null/unresolvable active → machine
  source); structural/historical cases follow from "whichever valid revision
  is active".

## Definition of Done

P6-010 is DONE only when: all AC1–AC11 are verified by independent review
(VERIFIED, no BLOCKER/MAJOR), evidence artifacts are retained, scope
integrity holds (no revision/translation redesign, no D6-08/D6-09, no Phase 7
work), and the HPO closes VERIFIED → DONE. P6-010 DONE enables the P6-009
rerun; it does not itself pass P6-009 or close Phase 6.
