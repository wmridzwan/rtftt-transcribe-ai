# P6-008 — Revision History / Audit Surface

## Status

DONE — closed by the Human Product Owner on 2026-09-25
(`DECISION-P6-008-CLOSURE-001`) on the basis of the fresh independent review
VERIFIED verdict (`reviews/P6-008-INDEPENDENT-REVIEW.md`; all AC1–AC10 PASS;
no BLOCKER/MAJOR/MINOR; OPTIONAL-1 non-blocking, preserved in the review
artifact). Prior status `DRAFT` (2026-09-25 contract candidate) → `READY`
(HPO scope decisions HPO-008-A/B/C/D recorded DECIDED and reconciled,
`DECISION-P6-008-READY-001`) → `IN_PROGRESS` (HPO-authorized Builder
execution) → `IMPLEMENTED_PENDING_REVIEW` (implementation + tests + browser
evidence complete) → `VERIFIED` (fresh independent review,
`reviews/P6-008-INDEPENDENT-REVIEW.md`) → `DONE` (HPO closure).

Builder report: `reviews/P6-008-BUILDER-REPORT.md`. P6-008 DONE does not
close Phase 6; P6-009 remains FINAL_GATE_ONLY.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code (independent review; per AGENTS.md agent model, when implemented)

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-02/D6-05).

## Objective

Give users a visible, truthful revision-history and audit surface over the
durable P6-002 revision graph, and provide an authorization-fenced,
CAS-fenced explicit historical-revision activation operation — without
redefining any frozen P6-001..P6-007 semantics and without touching Phase 5
persistence.

Why P6-008 exists: the revision graph is durable and queryable (P6-002 DONE)
and undo/redo navigates it (P6-003 DONE), but no surface lets a user see that
revisions exist, identify the active/current revision (D6-05), or explicitly
select a historical revision (frozen domain contract §2:
"explicit historical revision selection may remain possible later (P6-008)").
P6-009 gates on P6-008 DONE.

## Authority Matrix

| Requirement | Authority | Classification |
|---|---|---|
| User-visible revision/history surface; lightweight OK; must show revisions exist + identify active/current revision | D6-05 (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025) | EXPLICIT |
| Persisted revision/version semantics; history survives reload (so there is durable history to surface) | D6-02 (adopted); P6-002 DONE | EXPLICIT |
| Explicit historical revision selection reserved for P6-008; activation is CAS against the active revision id | Frozen domain contract §§2–3 | EXPLICIT |
| Reads the durable graph; must not bypass authorization, ownership, active-pointer CAS, or history semantics; must not redefine undo/redo | Eligibility matrix (P6-008 row); P6-002 closure | EXPLICIT |
| P6-008 DONE gates P6-009; D6-02 adopted persisted history so the cancellation condition is not met — HPO-008-D DECIDED PROCEED | Dependency graph; wave plan (WAVE P6-6/P6-7); eligibility matrix §C; `DECISION-P6-008-READY-001` | EXPLICIT |
| Browser evidence required (surface is browser-material) | DC-01; P6-008 classification rows | DERIVED |
| Depends only on P6-002 DONE; independent of P6-003/004/005/007 | Eligibility matrix | EXPLICIT |
| Explicit-selection scope: arbitrary eligible same-transcription historical revision activation (no artificial recent-only/bounded-history restriction) | HPO-008-A DECIDED (`DECISION-P6-008-READY-001`) | DECIDED |
| Audit depth: D6-05 lightweight minimum (list + active marker + persisted metadata) | HPO-008-B DECIDED (`DECISION-P6-008-READY-001`) | DECIDED |
| Retro-wiring staleness persistence for P6-003/P6-004 text/timing edits: EXCLUDED from P6-008 (tracked separately if desired) | HPO-008-C DECIDED (`DECISION-P6-008-READY-001`) | EXCLUDED |
| Metadata probe wiring, upload-progress evidence, staging cleanup, Phase 7 hardening | Phase 2/7 ownership (P2-005 area; TD-006→P7-010; TD-007→P7-011) | EXCLUDED (not P6-008) |

## Dependencies

Requires (all satisfied except HPO scope decisions):

- P6-001 DONE (frozen editing domain semantics; consumed, not redefined).
- P6-002 DONE (durable revision graph, `historyFor()`, CAS `activate()`
  primitive, view/update-fenced `RevisionService::history()`).
- D6-02 + D6-05 adopted (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025).
- DC-01 browser governance (evidence standard only).
- HPO-008-A/B/C/D decisions (scope; see below) + explicit HPO READY promotion.

## Scope

Implement only:

- A revision-history/audit surface in the transcript workspace listing the
  durable revisions of the transcription in version order, identifying the
  active/current revision and the machine-source state (D6-05 minimum).
- Per-entry audit metadata strictly limited to persisted data (version,
  author, created time, parent linkage, segment count, active marker); no
  fabricated fields.
- An authorization-fenced (`update`), CAS-fenced explicit-activation service
  operation reusing the verified repository `activate()` primitive, exposed
  through a thin controller/route pair, permitting activation of any eligible
  persisted historical revision valid for the same transcription under the
  existing domain constraints (HPO-008-A DECIDED; no artificial
  recent-only/bounded-history restriction).
- Presentation consistent with persisted translation-staleness markers
  (P6-005): surface must not contradict or infer staleness.
- Feature/domain tests + real-browser DC-01 evidence for the surface and
  activation behavior.

## Out of Scope

Do not implement:

- redefining P6-001..P6-007 domain semantics (undo/redo, timing invariants,
  split/merge, comparison, navigation, invalidation);
- Phase 5 translation/translation-segment writes of any kind;
- retro-wiring staleness persistence for text/timing edits (HPO-008-C DECIDED:
  excluded; tracked separately if desired);
- metadata probe wiring, upload-progress evidence, staging cleanup;
- D6-08/D6-09 (speaker/annotations/waveform);
- Phase 7 production hardening;
- P6-009 execution (P6-008 DONE is an input to it, not the gate itself).

Future ideas are not authorization.

## Acceptance Criteria

- [ ] AC1 — History list renders every durable revision of the transcription
  in version order and unambiguously identifies the active revision (D6-05).
- [ ] AC2 — Each entry shows only persisted audit metadata (version, author,
  created time, parent linkage, segment count, active marker); verified by
  inspection that no displayed field lacks a persistence source.
- [ ] AC3 — Explicit activation of any eligible persisted historical revision
  valid for the same transcription (HPO-008-A DECIDED: arbitrary eligible
  historical selection) succeeds through an `update`-authorized, CAS-fenced
  operation; a stale base is rejected as a conflict with no silent merge and
  no history rewrite.
- [ ] AC4 — Cross-transcription activation, activation of a revision invalid
  for the transcription under the existing domain constraints, and non-owner
  activation are all rejected (authorization + ownership negative tests).
- [ ] AC5 — Read path allows viewing per the `view` policy (owner/admin +
  authorized viewers); viewing never mutates history or the active pointer.
- [ ] AC6 — Undo/redo semantics unchanged: strict-ancestor undo, unique-child
  redo, branch-then-no-auto-redo; existing P6-002/P6-003 regression suites
  remain green unmodified in behavior.
- [ ] AC7 — Machine-source state is distinguishable on the surface (null
  active revision renders as machine-source authoritative, consistent with
  frozen presentation).
- [ ] AC8 — Translation-staleness presentation matches persisted P6-005
  markers exactly; no freshness inferred by the surface.
- [ ] AC9 — Full PHP suite green, Pint clean, PHPStan 0 errors; real-browser
  DC-01 evidence retained for list rendering, activation, and conflict paths.
- [ ] AC10 — No P6-001..P6-007 semantic change, no Phase 5 write, no
  D6-08/D6-09 feature, no unrelated file changed (verified by diff audit).

## Verification Requirements

- Feature tests against the real HTTP routes (list, activate, stale-base
  conflict, ownership negatives, cross-transcription rejection).
- Domain tests for scope enforcement per decided HPO-008-A scope (arbitrary
  eligible same-transcription activation; cross-transcription rejection).
- Real-browser DC-01 run: history rendering, activation round-trip + reload
  durability, conflict presentation; retained evidence JSON + spec.
- `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`,
  PHPStan level 7 with 0 errors.
- Independent review (Claude Code) before VERIFIED; HPO closure to DONE.

## Failure / Rollback Expectations

- Stale-base activation attempts fail closed as conflicts (existing
  `RevisionConflictException` path); no partial activation state.
- Activation never deletes or rewrites revisions; there is nothing to roll
  back beyond normal conflict surfacing.

## Review

Review Files: `reviews/P6-008-INDEPENDENT-REVIEW.md` (fresh independent
review, VERIFIED; all AC1–AC10 PASS; no BLOCKER/MAJOR/MINOR; OPTIONAL-1
non-blocking, preserved).
Review Status: VERIFIED → DONE (HPO closure `DECISION-P6-008-CLOSURE-001`,
2026-09-25).

## Completion

P6-008 cannot move directly to DONE. Required flow (orchestration policy):

DRAFT (this contract) → HPO scope decisions HPO-008-A/B/C/D DECIDED →
READY (`DECISION-P6-008-READY-001`, 2026-09-25; implementation authorized,
not started) → IN_PROGRESS (Builder execution authorized by the HPO;
implementation started) → IMPLEMENTED_PENDING_REVIEW → VERIFIED (fresh
independent review, `reviews/P6-008-INDEPENDENT-REVIEW.md`) → DONE (HPO
closure `DECISION-P6-008-CLOSURE-001`, 2026-09-25).

## Relationship to P6-009

P6-008 DONE is a gate input to P6-009 (dependency graph:
P6-003..P6-008 DONE → P6-009). It enables P6-009's durable-history checks
(domain checklist items 2 and 6: undo/redo from durable history; machine
original recoverable) to be exercised through a human-operable surface, but
P6-008 neither executes nor substitutes for the P6-009 terminal gate, which
is authored and run separately as FINAL_GATE_ONLY.

## Scope Decisions (HPO) — RECORDED DECIDED (`DECISION-P6-008-READY-001`, 2026-09-25)

- HPO-008-A — Explicit-selection scope: DECIDED as option (1) arbitrary
  eligible same-transcription activation (any eligible persisted historical
  revision valid for the same transcription under the existing domain
  constraints, CAS-fenced; no artificial recent-only/bounded-history
  restriction). Matches the repository `activate()` primitive as built and
  the frozen domain contract's "explicit selection" language (§2).
- HPO-008-B — Audit depth: DECIDED as option (1) D6-05 lightweight minimum
  (history list in version order + active marker + persisted metadata only).
  No expansion into fuller audit presentation, activity timeline, forensic,
  or analytics surface.
- HPO-008-C — OPTIONAL-1 retro-wire (persist staleness for P6-003/P6-004
  text/timing edits): DECIDED EXCLUDE from P6-008. The P6-003/P6-004 scope
  boundary stands as historical truth; any future work is tracked separately.
- HPO-008-D — Proceed vs cancel: DECIDED PROCEED. Durable persisted revision
  history exists (P6-002 DONE), D6-02 is adopted, D6-05 requires the surface,
  historical selection was reserved for P6-008, the activation primitive
  exists, and no other task owns the required UI/service-layer integration —
  so the cancellation condition is not satisfied.

## Definition of Done

P6-008 is DONE only when: all AC1–AC10 verified by independent review
(VERIFIED, no BLOCKER/MAJOR), HPO scope decisions recorded, evidence
artifacts retained, and the HPO closes VERIFIED → DONE. P6-008 DONE does not
close Phase 6.
