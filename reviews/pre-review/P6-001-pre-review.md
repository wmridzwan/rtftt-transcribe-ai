# P6-001 — Builder Pre-Review Handoff

Date: 2026-09-23
Task: P6-001 — Advanced Transcript Editing Domain / Contract Foundation
Status: `IMPLEMENTED_PENDING_REVIEW`
Authority: `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025 (D6-01..D6-09, DC-01)
Reviewer: Claude Code (fresh independent review required)

## What this task is

A **contract-foundation** task. It fixes the Phase 6 editing semantics that
P6-002+ implement. It does not build UI, does not migrate the database, and does
not touch frozen Phase 3/4/5 contracts.

## Deliverables

- `PHASE6-EDITING-DOMAIN-CONTRACT.md` — canonical specification.
- `app/Editing/` — domain contract primitives:
  `EditableField`, `EditKind`, `TranslationStalenessReason`,
  `RevisionSegmentIdentity`, `RevisionSegmentData`, `TimingInvariants`,
  `TranscriptRevision`, `TranslationInvalidationPolicy`,
  `RevisionConflictException`, `RevisionRepository`, `MachineSegmentSnapshot`,
  `RevisionFactory`.
- `tests/Unit/Editing/` — 30 tests, 122 assertions, including a reference
  in-memory `RevisionRepository` implementation (`Tests\Support\InMemoryRevisionRepository`)
  that pins the append-only + compare-and-set contract.

## Requested fresh-review focus

1. **Contract completeness vs. Acceptance Criteria.** ACs 1–7 (immutable source
   vs editable revision; durable revision/version; editing/timing invariants;
   split/merge identity + language carry + explicit translation staleness;
   ownership + optimistic concurrency; additive schema shape named with no
   migration; integration-gate checklist).
2. **Timing semantics (D6-03) are explicit and correct:** overlap legality and
   lowest-`position` resolution; zero-length legal-but-never-active; ordering by
   contiguous `position`; per-segment `start <= end`; no cross-segment
   monotonicity requirement; timing edits never mutate machine timestamps.
3. **Navigation identity (P6-006 handoff):** edited-transcript navigation uses
   the active revision's identity/position, not machine `segment_index`; the
   spec says so and `RevisionSegmentIdentity` exists for it.
4. **Translation invalidation (D6-04):** every edit kind is invalidating with an
   explicit reason; no silent remap; persisted marker deferred to P6-005.
5. **No scope creep:** no migration/model/route/view/JS; frozen contracts
   unchanged; `data-seek-seconds`/`data-segment-language` recorded as reserved
   Phase 4 hooks.
6. **P6-002 readiness:** the schema shape and `RevisionRepository` contract are
   sufficient for P6-002 to consume without re-deciding semantics.

## Deliberate design points

- The domain is pure: machine segments enter through `MachineSegmentSnapshot`
  (no Eloquent coupling); persistence is an interface only.
- `RevisionFactory` is deterministic in ordering/identity and generates opaque
  UUID revision ids; tests assert UUID shape without injecting a clock.
- `TranslationInvalidationPolicy` has no "leave current" branch by construction.
- `RevisionConflictException` encodes the stale-write rule; the in-memory
  repository demonstrates it.

## Quality / evidence

- Full PHP suite: 691 tests, 690 passed, 1 skipped, 0 failures.
- New: `tests/Unit/Editing/` 30 passed, 122 assertions.
- Pint clean; PHPStan 0 errors.
- Browser evidence: not required (no browser behavior changed).

## Not done (correctly)

- Not VERIFIED; not DONE. No self-verification.
- P6-002 not implemented and not promoted to READY (contract authored only).
- No P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 and no further P7 work started.