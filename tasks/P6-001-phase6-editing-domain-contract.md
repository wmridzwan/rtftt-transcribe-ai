# P6-001 — Advanced Transcript Editing Domain / Contract Foundation

## Status

DONE — independently VERIFIED and closed by the Human Product Owner
(`DECISION-P6-001-CLOSURE-001`, 2026-09-23). Canonical contract authored
2026-09-23 under `DECISION-PHASE6-AUTHORIZATION-001` (dependencies reconciled:
Phase 5 CLOSED; `DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025); domain contract
implemented and unit-tested 2026-09-23; corrective cycle applied for the
independent review BLOCKER/HIGH/MEDIUM findings. The fresh independent
corrective re-review (`reviews/P6-001-corrective-independent-re-review.md`)
returned VERIFIED with no remaining BLOCKER/HIGH/MEDIUM. The canonical semantics
are frozen inputs for downstream Phase 6 work.

- Canonical specification: `PHASE6-EDITING-DOMAIN-CONTRACT.md` (FROZEN).
- Domain contract: `app/Editing/` (13 classes/enums/interfaces).
- Unit tests: `tests/Unit/Editing/` (48 tests, 186 assertions).
- Quality: full PHP suite 709/708 (1 skipped, 0 failures); Pint clean; PHPStan 0.

## Review

Review verdict: **VERIFIED** (`reviews/P6-001-independent-review.md` returned
CHANGES_REQUESTED; corrective handoff:
`reviews/pre-review/P6-001-corrective-pre-review.md`; fresh corrective
independent re-review: `reviews/P6-001-corrective-independent-re-review.md`
returned VERIFIED). All historical findings and corrective provenance are
preserved. No browser evidence required (this task changes no browser behavior).

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

## Implementation (2026-09-23)

Canonical specification: `PHASE6-EDITING-DOMAIN-CONTRACT.md` (defines the
immutable-source + editable-revision model; revision identity/version/durable
history; active revision + optimistic concurrency; navigation identity over the
active revision; timing invariants; split/merge identity and language carry;
translation-invalidation policy; additive schema shape; integration-gate
checklist; reserved Phase 4 hooks).

Domain contract (`app/Editing/`):

- `EditableField` — editable field vocabulary.
- `EditKind` — textual/timing/structural edit classification.
- `TranslationStalenessReason` — explicit staleness reasons.
- `RevisionSegmentIdentity` — stable navigation/selection identity.
- `RevisionSegmentData` — validated ordered revision segment.
- `TimingInvariants` — explicit timing/overlap/zero-length/ordering policy.
- `TranscriptRevision` — immutable revision value object.
- `TranslationInvalidationPolicy` — every edit kind invalidates.
- `RevisionConflictException` — stale-write conflict / non-monotonic version.
- `RevisionVersionAllocator` — transcription-scoped monotonic version contract.
- `RevisionRepository` — persistence contract (implemented by P6-002).
- `MachineSegmentSnapshot` — pure machine-source snapshot.
- `RevisionFactory` — materialize/derive revisions.

Tests (`tests/Unit/Editing/`): `TimingInvariantsTest`,
`RevisionSegmentIdentityTest`, `RevisionSegmentDataTest`,
`TranscriptRevisionTest`, `TranslationInvalidationPolicyTest`,
`RevisionFactoryTest`, `RevisionRepositoryContractTest`,
`RevisionVersioningTest`, `RedoSemanticsTest` (use the reference in-memory
`Tests\Support\InMemoryRevisionRepository`). 48 tests, 186 assertions.

### Editing/revision/timing invariants established

- Machine source is immutable in place; edits live in append-only revisions with
  durable history; exactly one active revision (null = machine source).
- Optimistic concurrency: base revision id must equal the active revision id;
  otherwise a stale-write conflict is thrown (no silent merge / last-writer-wins).
- Revision segments have stable, unique `RevisionSegmentIdentity`; ordering is
  by unique contiguous `position` (`0..n-1`), never by timestamp.
- Per segment: finite, non-negative, `start <= end` (ms precision). Zero-length
  is legal and never active. Overlaps are legal and resolve to the lowest
  `position`. Cross-segment timestamp monotonicity is not required. Revision
  timing never mutates machine timestamps.
- Navigation identity over an edited transcript follows the active revision's
  identity/position, not machine `segment_index`.
- Every edit kind (textual/timing/structural) marks affected translations
  explicitly stale with an explicit reason; translation content is never
  silently remapped.
- `data-seek-seconds` / `data-segment-language` remain reserved Phase 4 hooks.

### Corrective cycle (2026-09-23) — review CHANGES_REQUESTED

1. **Version semantics (BLOCKER).** `version` is now a monotonic sequence
   scoped to the transcription and independent of ancestry. `RevisionFactory::
   derive()` no longer computes `$base->version + 1`; it obtains the next
   version from the new `RevisionVersionAllocator` contract (implemented by the
   repository), and `RevisionRepository::append()` re-validates monotonicity,
   rejecting a non-monotonic version with `RevisionConflictException`. This
   prevents duplicate `(transcription_id, version)` after
   `undo → edit from older active revision`. Regression coverage:
   `RevisionVersioningTest` (linear, single undo, multiple undos, multiple
   branches, uniqueness, cross-transcription reuse, non-monotonic rejection).
2. **Redo semantics (HIGH).** The contract now distinguishes the durable
   revision graph/history from the user undo/redo navigation path. Redo target
   is the active revision's unique child (`RevisionRepository::redoTargetFor()`);
   a new edit from a non-tip active revision invalidates the prior redo path
   (branch point → redo unavailable, never guessed), while abandoned branches
   stay durable. `childrenOf()` exposes branch ancestry. Coverage:
   `RedoSemanticsTest`.
3. **Mixed-category staleness precedence (MEDIUM).** Added fixed precedence
   `SegmentStructureChanged` > `TimingChanged` > `SourceTextChanged`
   (`EditKind::precedence()`, `TranslationInvalidationPolicy::reasonForKinds()`).
   Every applicable edit kind remains translation-invalidating. Coverage:
   `TranslationInvalidationPolicyTest`.
4. **Low test-support PHPStan findings addressed.** Removed the redundant
   `array_values()` and added the missing `list<RevisionSegmentData>` PHPDoc.

P6-002's authored contract was reconciled against these semantics but remains
**not READY** and not implemented.

No migration, model, route, view, JavaScript, or frozen Phase 3/4/5 contract was
changed.