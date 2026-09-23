# P6-001 — Advanced Transcript Editing Domain / Contract Foundation

## Status

READY — canonical contract authored 2026-09-23 under
`DECISION-PHASE6-AUTHORIZATION-001`; dependencies reconciled (Phase 5 CLOSED;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). Not implemented; not
IMPLEMENTED_PENDING_REVIEW; not VERIFIED/DONE.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019; ADR-025)

## Objective

Establish the canonical Phase 6 editing domain/contract foundation: the
authoritative model of an **immutable machine transcript source layer** plus an
explicit **editable revision layer**, with durable revision/version semantics,
defined editable fields and validation invariants, timing/split-merge rules, and
the translation-invalidation policy. This task fixes the semantics that P6-002+
implement; it does not build the editing UI.

## Scope

1. Define the domain contract (interfaces/value objects/services) for:
   - immutable source provenance from the completed `Transcription` /
     `TranscriptionSegment` rows;
   - editable revision layer entries and the current/active revision pointer;
   - revision/version identity and durable history (D6-02);
   - editable fields and per-field validation bounds (D6-01/D6-03);
   - timing invariants: valid/non-negative, `start < end`, explicit
     ordering/overlap policy (D6-03);
   - split/merge stable segment identity, boundary monotonicity, and
     per-segment language carry (D6-04);
   - translation staleness/invalidation policy: a structural or textual change
     that invalidates alignment marks affected translations explicitly stale and
     never silently remaps translation content (D6-04);
   - ownership via `TranscriptionPolicy` (owner/admin) and optimistic
     concurrency (revision token) semantics;
   - export/consumption semantics (edited view is authoritative for presentation;
     the machine original stays recoverable).
2. Define the additive schema shape (no migration in this task unless separately
   authorized; the contract names the tables/columns/keys P6-002 will add).
3. Define the machine-transcript immutability guarantee: completed machine rows
   are never overwritten (ADR-018 B3-04 continues to apply to the machine
   original).
4. Define the Phase 6 integration-gate checklist (edit → persist → reload; undo/
   redo; timing edits; split/merge alignment; comparison; exports-after-edit;
   stale-write conflict; Unicode round-trip `ms/en/zh/ta/und`; original
   recoverable; no Phase 3/4/5 regression).

## Non-Scope

- editing UI / Alpine / Blade (P6-003/P6-004/P6-006);
- persistence implementation + migrations (P6-002);
- source/translation comparison UI (P6-007);
- revision-history surface UI (P6-008);
- speaker labels/annotations/bookmarks (D6-08, deferred);
- waveform/timeline (D6-09, deferred);
- real-time collaboration/multi-user editing; tenancy redesign;
- any change to frozen Phase 3/4/5 contracts.

## Dependencies

- Phase 5 = CLOSED (`DECISION-PHASE5-CLOSURE-001`);
- D6-01..D6-09 + DC-01 adopted (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025);
- P3 persistence/immutability contracts; P4 read-model primitives.

## Acceptance Criteria

1. The contract explicitly defines the immutable-source + editable-revision
   model and forbids in-place mutation of machine rows.
2. Revision/version semantics are durable and survive reload; undo/redo is
   derived from history.
3. Editable fields, validation bounds, timing invariants, and ordering/overlap
   policy are unambiguous.
4. Split/merge identity and language-carry rules are defined; translation
   invalidation is explicit (stale marking, no silent remap).
5. Ownership and optimistic-concurrency (stale-write) behavior are defined.
6. Schema shape is additive and named; no migration is performed here.
7. The Phase 6 integration-gate checklist is recorded.
8. No frozen Phase 3/4/5 contract is changed; D6-08/D6-09 remain deferred.

## Verification Requirements

Contract/document review (no browser). Independent review of the contract before
any dependent implementation is promoted.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D6-01..D6-09 (adopted); DC-01; ADR-025.