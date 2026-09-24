# Phase 6 — P6-004 Closure + P6-005 Contract Batch Report

Date: 2026-09-24
Tasks: P6-004 (Timing Editing + Validation) — closure; P6-005 (Split / Merge +
Translation Invalidation) — eligibility reclassification + canonical contract
Status: **P6-004 = DONE; P6-005 = CONTRACT_AUTHORED (not READY, not implemented)**
Authority: `DECISION-P6-004-CLOSURE-001`;
`DECISION-P6-004-INFO-CARRYFORWARD-001`;
`DECISION-P6-005-ELIGIBILITY-001`; ADR-025; ADR-022; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md`.

## 0. Explicit confirmations

- **P6-004 was accepted VERIFIED and closed DONE** by the HPO
  (`DECISION-P6-004-CLOSURE-001`). The independent review and the historical
  implementation/pre-review artifacts are preserved unchanged.
- **No P6-005 implementation occurred.** No migration, model, route, controller,
  view, or JavaScript was written; P6-005 was not promoted to READY.
- **No P6-008 / P6-009 / additional Phase 7 work occurred.**
- **No Phase 5 translation rows are rewritten or remapped.**
- P6-002/P6-007 record-completeness notes are retained; no artifact was
  fabricated.

## 1. P6-004 final closure

- Fresh independent review verdict: **VERIFIED**, no remaining BLOCKER/HIGH/MEDIUM.
  Independently confirmed: frozen timing semantics implemented exactly; invalid
  first (machine-source) edits write-free; active-revision timing is the
  playback/active-resolution source of truth; machine timing immutable;
  overlap/nested/equal/zero-length/out-of-time-order timing legal; stale-base/CAS
  correct; no P6-003/P6-006/P6-007 regression; no split/merge or
  translation-staleness persistence.
- Transition `VERIFIED → DONE` recorded in `DECISIONS.md` / `DECISION_QUEUE.md`
  (`DECISION-P6-004-CLOSURE-001`) and
  `tasks/P6-004-timing-editing-validation.md`.
- Non-blocking INFO debt carried forward without reopening P6-004
  (`DECISION-P6-004-INFO-CARRYFORWARD-001`):
  1. pre-existing P6-007/P4-006 V4-13 locator-scoping issue involving hidden
     comparison content (introduced by P6-007 `985d2c5`; recommend scoping the
     older spec locator to `[data-transcript-region]`);
  2. the shared Laravel validation error bag could theoretically surface unrelated
     form errors inside the timing toolbar (no observed failure; optional future
     hardening);
  3. the Phase 4 headless playback-start V4-08/V4-09 environmental flake
     (`currentTimeAfterPlay: 0`; pre-existing, unrelated to P6-004).

## 2. P6-005 contract summary

Canonical contract: `tasks/P6-005-split-merge-translation-invalidation.md`
(`CONTRACT_AUTHORED — PENDING HPO READY PROMOTION`). P6-005 is reclassified
`CONTRACT_REQUIRED / READY-ELIGIBLE AFTER CONTRACT`
(`DECISION-P6-005-ELIGIBILITY-001`) now that P6-004 is DONE.

P6-005 owns two high-risk areas: structural segment editing (split/merge) and
persisted translation invalidation. It consumes, without redefining, the frozen
P6-001..P6-004 semantics and the Phase 5 translation identity
(`translations` per `(transcription_id, target_language)`; `translation_segments`
aligned to machine `segment_index`; no revision-linked translation identity).

## 3. Exact split semantics

- Replaces one active-revision segment with two ordered segments at the original
  position, as a **new** revision derived from `expected_base`; prior revision
  never mutated.
- **Identity:** two **new** opaque `RevisionSegmentIdentity` values; original
  identity retired; never reuse a `machine:<index>` identity.
- **Positions:** children at `p` and `p+1`; subsequent positions `+1`; contiguous
  `0..n-1`.
- **Text:** first `text[0,k)`, second `text[k,length)`, Unicode code-point
  boundaries.
- **Timing:** `[start, t]` and `[t, end]`, each satisfying the frozen invariants.
- **Language:** both children carry the source segment's language verbatim.
- **Base:** `parent_revision_id = expected_base`; compare-and-set against the
  active pointer; stale base is the canonical conflict.
- **Classification:** `EditKind::Structural` → `SegmentStructureChanged`.
- **Boundary (owner decision `DECISION-P6-005-SPLIT-BOUNDARY-001`):** default
  strictly interior (`start < t < end`, `0 < k < length`) so children are
  non-degenerate; degenerate splits rejected. Alternative (allow zero-length /
  empty-text children) must be confirmed or overridden.

## 4. Exact merge semantics

- Replaces an ordered run of two or more **adjacent** revision segments with one
  segment at the earliest replaced position, as a new revision; prior revision
  never mutated.
- **Adjacency:** required (frozen P6-001 §7); non-adjacent/gapped runs rejected.
- **Identity:** one **new** opaque identity at `p`; contributing identities
  removed only from the new revision; old revisions durable.
- **Positions:** merged at `p`; subsequent positions `-(m-1)`; contiguous.
- **Text:** joined in `position` order; separator is owner decision
  `DECISION-P6-005-MERGE-JOIN-001` (default: single space).
- **Timing:** owner decision `DECISION-P6-005-MERGE-JOIN-001` (default:
  earliest-position start → latest-position end), satisfying the frozen
  invariants; zero-length/overlap contributions legal; no hidden monotonicity.
- **Language:** earliest contributing segment's language (frozen); if languages
  differ, **flagged as mixed-language provenance** — representation is owner
  decision `DECISION-P6-005-LANGUAGE-PROVENANCE-001`.
- **Classification:** `EditKind::Structural` → `SegmentStructureChanged`.

## 5. Translation invalidation lifecycle

- **Minimum marker (named by P6-001 §10, not implemented):**
  `translations.stale_at` (timestamp, nullable) and
  `translations.staleness_reason` (string, nullable).
- **Taxonomy (frozen, not redefined):** `SourceTextChanged`, `TimingChanged`,
  `SegmentStructureChanged`; precedence
  `SegmentStructureChanged > TimingChanged > SourceTextChanged`.
- **Scope:** per `translations` row (= per `(transcription_id, target_language)`).
- **Structural edits** invalidate every persisted translation for the
  transcription (segment identity/position changes globally).
- **Never:** rewrite Phase 5 `translation_segments`, silently remap translations
  to new revision identities, or pretend a structurally modified revision remains
  aligned to the machine translation.
- **Lifecycle (owner decision `DECISION-P6-005-STALENESS-LIFECYCLE-001`):** when
  staleness is set; scope; causing revision/source identity; reason stored;
  repeated-edit replace vs accumulate; retranslation clearing; historical
  viewability. Contract defaults pending confirmation: per-translation scope;
  reason replaced by highest-severity precedence and `stale_at` refreshed;
  retranslation clears staleness; stale output remains viewable but clearly marked
  stale.

## 6. Required schema additions (named only; not implemented)

- `translations.stale_at` (timestamp, nullable) — named by P6-001 §10.
- `translations.staleness_reason` (string, nullable) — named by P6-001 §10.
- optionally `translations.stale_revision_id` or equivalent causing-revision
  provenance — owner decision `DECISION-P6-005-SCHEMA-001`.
- optionally a revision-segment mixed-language provenance marker — owner decision
  `DECISION-P6-005-LANGUAGE-PROVENANCE-001`.

No final schema was invented; the Phase 5 translation identity contract was traced
and is respected.

## 7. Unresolved HPO decisions

Recorded OPEN in `DECISION_QUEUE.md` and `DECISIONS.md`:

- `DECISION-P6-005-SPLIT-BOUNDARY-001` — split boundary / degenerate splits.
- `DECISION-P6-005-MERGE-JOIN-001` — merge text join and resulting timing.
- `DECISION-P6-005-LANGUAGE-PROVENANCE-001` — mixed-language provenance flag
  representation.
- `DECISION-P6-005-STALENESS-LIFECYCLE-001` — persisted staleness lifecycle.
- `DECISION-P6-005-SCHEMA-001` — exact schema additions.

P6-005 implementation must not begin until these are resolved and an explicit HPO
READY promotion is recorded.

## 8. Concurrency / atomicity requirements

- Every structural edit states the expected active/base revision; a stale base is
  the canonical conflict; no silent merge / last-writer-wins.
- The structural revision append and the invalidation persistence must commit
  **atomically** in a single transaction.
- Failure must not leave a new active revision with a translation incorrectly
  marked current, nor mark a translation stale without the structural revision
  succeeding.
- A rejected/invalid structural edit leaves persistence unchanged.
- Implementation must extend the P6-002 transactional boundary (the repository's
  `append` is already transactional) to include the invalidation write, without
  redefining CAS/version semantics.

## 9. Browser verification requirements

Real-browser (DC-01) verification is mandatory. Scenarios include:

- **Split:** valid split; split at beginning/end (allowed or rejected explicitly);
  resulting text/timing/language; reload durability; revision-history
  preservation; translation invalidation.
- **Merge:** valid adjacent merge; non-adjacent merge rejected; resulting
  text/timing/language; reload durability; translation invalidation.
- **Concurrency/safety:** stale structural conflict; cancel/no write; ownership
  denial; machine source unchanged; failed operation leaves the translation
  lifecycle coherent.
- **Comparison/navigation:** P6-006 navigation still valid (position-based);
  P6-007 does not silently align a structurally changed revision to the machine
  translation.

## 10. P6-005 READY eligibility

P6-005 is **READY-eligible after contract** and now has a canonical contract.
It is **not** READY and **not** implemented. READY eligibility additionally
requires (a) resolution of the five owner decisions above and (b) an explicit HPO
READY promotion. P6-008 remains separately contract-gated; P6-009 remains
FINAL_GATE_ONLY.

## 11. Changed files

Governance / task records:

- `tasks/P6-004-timing-editing-validation.md` (DONE)
- `tasks/P6-005-split-merge-translation-invalidation.md` (new — canonical
  contract)
- `DECISIONS.md`, `DECISION_QUEUE.md` (`DECISION-P6-004-CLOSURE-001`,
  `DECISION-P6-004-INFO-CARRYFORWARD-001`, `DECISION-P6-005-ELIGIBILITY-001`, and
  the five OPEN P6-005 decisions)
- `CURRENT_STATE.md`, `AGENTS.md`, `plan.md`
- `PHASE6-7-ELIGIBILITY-MATRIX.md` §S
- `PHASE5-7-DEPENDENCY-GRAPH.md` (2026-09-24 Phase 6 status)
- this report

Preserved unchanged:

- `reviews/pre-review/P6-004-pre-review.md`,
  `PHASE6-P6-004-IMPLEMENTATION-BATCH-REPORT.md`,
  `verification/p6-004/P6-004-BROWSER-VERIFICATION-EVIDENCE.md`.
- No application code, test, migration, or schema changed in this batch.

## 12. Explicit non-actions

- No P6-005 implementation; P6-005 not promoted to READY.
- No P6-008/P6-009 authoring, implementation, or promotion.
- No additional Phase 7 task; Phase 7 remains not generally authorized.
- No historical finding rewritten; no frozen semantics redefined; no Phase 5
  translation rows rewritten or remapped.