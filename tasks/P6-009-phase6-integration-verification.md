# P6-009 — Phase 6 Final Integration Verification Gate

## Status

DONE — closed by the Human Product Owner on 2026-09-25
(`DECISION-P6-009-CLOSURE-001`) on the basis of the rerun PASS verdict
(`P6-009-RERUN-01`, AC1–AC11 fresh), the independent review VERIFIED
verdict (`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`; technical
evidence independently reproduced with zero discrepancies; no
BLOCKER/HIGH), the governance reconciliation
(`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`; A2/B1), the
late-persisted rerun authorization
(`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`), and HPO acceptance of the
remaining procedural finding as RECONCILED / ACCEPTED FOR CLOSURE (finding
preserved in the audit trail; not deleted, downgraded, or rewritten).
Prior states: READY → IN_PROGRESS (first execution, verdict FAIL, F-001)
→ RERUN → REVIEW → VERIFIED → DONE (this closure). Historical
first-execution FAIL preserved untouched under `verification/p6-009/`.
Phase 6 remains OPEN pending a separate HPO Phase 6 closure decision;
this closure does not itself close Phase 6.

Governance correction (2026-09-25,
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`): the rerun record
originally cited `DECISION-P6-009-READY-001` + `DECISION-P6-010-CLOSURE-001`
as its authority. Read verbatim, neither grants rerun-execution authority
(the former authorized the original execution; the latter disclaims
rerunning P6-009 and frames the rerun as "eligible for explicit
authorization"). Those two decisions are context/eligibility only. The
actual authorizing act was the direct HPO rerun instruction issued before
execution, now late-persisted as
`DECISION-P6-009-RERUN-01-AUTHORIZATION-001` (persistence reconciliation
only; authority genuinely predates execution, nothing backdated). Original
wording below is annotated, not deleted.

Task type: FINAL_GATE_ONLY.

Contract acceptance and READY promotion are not gate execution. This decision
authorizes the gate to be opened; a separate implementation-owner (gate
executor) assignment and evidence-producing execution
(`IN_PROGRESS` → `REVIEW` → `VERIFIED` → HPO closure) remain required. Phase 6
remains OPEN.

## Ownership

Implementation Owner (gate executor): OpenCode — first execution + full rerun
P6-009-RERUN-01 (IN_PROGRESS 2026-09-25 → REVIEW 2026-09-25 on PASS verdict →
VERIFIED 2026-09-25 on independent review → DONE 2026-09-25 on HPO closure;
Builder-equivalent verifier; did not self-review or self-close).
Reviewer: Claude Code (independent of the gate executor) —
`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md` → VERIFIED with one MAJOR
procedural finding (reconciled; HPO-accepted for closure, preserved in the
audit trail).

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-01..D6-09 +
DC-01 adopted via `DECISION-PHASE6-OWNER-DECISIONS-001`).

## Objective

Prove that the completed Phase 6 system, as one integrated product capability,
satisfies the approved Phase 6 contract without violating frozen
earlier-phase invariants.

The gate answers: does the integrated transcript-authoring system — durable
revisions, active revision, undo/redo, split/merge, historical activation,
machine-source recoverability, translation staleness, workspace presentation,
authorization — operate coherently over completed persisted transcripts?

P6-009 verifies existing completed behaviour. It builds no new behaviour and
remediates nothing by default.

## Authority Matrix

| Requirement | Authority | Classification |
|---|---|---|
| P6-009 exists as the Phase 6 terminal integration gate; FINAL_GATE_ONLY; gated on P6-003..P6-008 DONE; browser-heavy; consumes all P5/P6 contracts; uses persisted P5 data (no real translation service) | `PHASE5-7-EXECUTION-CLASSIFICATION.md` (P6-009 row); `PHASE6-PLANNING.md` §E; `PHASE6-7-ELIGIBILITY-MATRIX.md` §C; `PHASE5-7-DEPENDENCY-GRAPH.md` (Phase 6 DAG); `PHASE5-7-WAVE-PLAN.md` (WAVE P6-7) | EXPLICIT |
| Canonical P6-009 contract accepted; prerequisite satisfied; HPO promoted `DRAFT` → READY; gate execution not yet started | `CURRENT_STATE.md`; `plan.md` (Phase 6); `DECISION-P6-008-CLOSURE-001` consequence; `DECISION-P6-009-READY-001` | EXPLICIT |
| Integrated gate checklist (10 items: edit→persist→reload; undo/redo durable + no auto-redo at branch; timing §5; split/merge identity/language; comparison reflects active; exports reflect active + machine recoverable; stale-write rejected; unicode; P3/4/5 regression; no D6-08/D6-09) | `PHASE6-EDITING-DOMAIN-CONTRACT.md` §11 (recorded; executed at the terminal gate) | EXPLICIT |
| Frozen revision semantics (immutable machine source; append-only revisions; one active pointer, null = machine source; transcription-scoped monotonic versions; ancestry via `parent_revision_id`; CAS active pointer + version uniqueness; strict-ancestor undo; unique-child redo; branch invalidates auto-redo; overlap-legal / zero-length-legal-but-never-active timing; position-ordered navigation; precedence `SegmentStructureChanged > TimingChanged > SourceTextChanged`) | `PHASE6-EDITING-DOMAIN-CONTRACT.md` §0 (HPO-frozen P6-001 inputs); P6-001/P6-002 DONE | EXPLICIT |
| P6-005 owns persisted translation staleness (`translations.stale_at`, `staleness_reason`, `stale_caused_by_revision_id`; per-row lifecycle; first/repeated invalidation); `translation_segments` never rewritten or remapped | P6-005 contract; `DECISION-P6-005-SPLIT-BOUNDARY-001`, `-MERGE-JOIN-001`, `-LANGUAGE-PROVENANCE-001`, `-STALENESS-LIFECYCLE-001`, `-SCHEMA-001`; `DECISION-P6-005-CLOSURE-001` | EXPLICIT |
| P6-003/P6-004 classify edits (`EditKind::Textual` → `SourceTextChanged`; `EditKind::Timing` → `TimingChanged`) and persist no staleness | P6-003/P6-004 contracts and closures | EXPLICIT |
| P6-007 is presentation-only; aligns machine↔translation by `segment_index` only; never silently remaps structurally incompatible revisions; no staleness inference or persistence | `DECISION-P6-007-SCOPE-001`; P6-007 contract and closure | EXPLICIT |
| P6-008 arbitrary eligible same-transcription historical activation (HPO-008-A); lightweight audit surface (HPO-008-B); HPO-008-C retro-wire EXCLUDED; HPO-008-D PROCEED | `DECISION-P6-008-READY-001`; P6-008 contract; `DECISION-P6-008-CLOSURE-001` | DECIDED |
| D6-01..D6-07 + DC-01 adopted; D6-08/D6-09 DEFERRED and non-blocking | `DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025 | EXPLICIT |
| P6-009 DONE + independent VERIFIED + HPO closure → Phase 6 CLOSED; no automatic closure | `PHASE5-7-DEPENDENCY-GRAPH.md`; `PHASE5-7-WAVE-PLAN.md`; eligibility matrix §T | EXPLICIT |
| Phase 7 entry requires Phase 6 CLOSED + D7-01..D7-08 + HPO authorization; P7-012 FINAL_GATE_ONLY, cannot run before Phase 6 CLOSED | `PHASE7-PLANNING.md` §H; `docs/PRODUCTION_READINESS_GATE.md` (entry criteria) | EXPLICIT |
| Lifecycle READY → IN_PROGRESS → REVIEW → VERIFIED → DONE; Builder must not self-verify/close; Reviewer produces durable `reviews/` artifact; max 3 CHANGES_REQUESTED cycles then BLOCKED | `.ai/guidelines/orchestration-policy.md` | EXPLICIT |
| Full-suite/Pint/PHPStan/browser evidence standard for the gate | P6-004..P6-008 evidence precedent; `reviews/REVIEW_TEMPLATE.md`; DC-01/ADR-021 | DERIVED |
| Canonical export set TXT/SRT/VTT/DOCX | ADR-019 (D4-03); P4-005; P6-001 §9 | EXPLICIT |
| New features, new revision/translation semantics, D6-08/D6-09 retro-wire, Phase 7 hardening inside P6-009 | No authority; contradicts FINAL_GATE_ONLY | EXCLUDED (not P6-009) |

## Dependencies

Requires (gate-entry prerequisites; all must hold before execution):

- P6-001 DONE (`DECISION-P6-001-CLOSURE-001`; frozen domain semantics).
- P6-002 DONE (`DECISION-P6-002-CLOSURE-001`; revision persistence layer).
- P6-003 DONE (`DECISION-P6-003-CLOSURE-001`; text editing/undo-redo).
- P6-004 DONE (`DECISION-P6-004-CLOSURE-001`; timing editing/validation).
- P6-005 DONE (`DECISION-P6-005-CLOSURE-001`; split/merge + persisted staleness).
- P6-006 DONE (`DECISION-P6-006-CLOSURE-001`; navigation/filter).
- P6-007 DONE (`DECISION-P6-007-CLOSURE-001`; presentation-only comparison).
- P6-008 DONE (`DECISION-P6-008-CLOSURE-001`; history surface + activation).
- No unresolved Phase 6 BLOCKER that invalidates gate execution (`BLOCKERS.md`).
- Explicit HPO authorization promoting P6-009 to READY/open-gate state
  (HPO-009-A). Satisfied: `DECISION-P6-009-READY-001` (2026-09-25).

## Scope

Verify only (integrated, product-path, fresh execution):

- Integrated revision workflow: revision creation, revision history, active
  revision, undo, redo, branch-after-undo behaviour, split, merge, historical
  revision activation, machine-source/original recoverability, revision
  ordering, same-transcription fencing, ownership/authorization.
- Translation staleness: existing P6-005 persisted semantics — per-row
  markers, precedence (`SegmentStructureChanged > TimingChanged >
  SourceTextChanged`), caused-by linkage, first and repeated invalidation
  lifecycle — behaving consistently after structural/revision changes. No new
  persistence behaviour.
- Workspace integration: active-revision presentation, history, machine-source
  state, structural edits, staleness state, historical activation, reload
  persistence — on the real transcript workspace product path, not isolated
  unit tests alone.
- Authorization and isolation: non-owner fencing, cross-transcription fencing,
  invalid activation rejection, stale/concurrent write rejection where
  applicable; no Phase 6 path weaker than existing ownership rules.
- Domain invariants: append-only history, revision identity, active-pointer
  semantics, original/machine-source recoverability, frozen Phase 5 write
  boundaries (no `translation_segments` rewrite/remap), translation
  boundaries, ownership isolation.
- Regression safety: required suites remain green (see Verification
  Requirements).

## Out of Scope

Do not implement or remediate inside P6-009:

- new features of any kind;
- new revision semantics;
- new translation semantics;
- new workspace features;
- D6-08 (speaker/annotations/bookmarks);
- D6-09 (waveform/timeline);
- P6-003/P6-004 staleness retro-wire (HPO-008-C exclusion preserved);
- metadata probe wiring, upload-progress work, staging cleanup;
- Phase 7 topology, deployment, worker supervision, production security,
  monitoring, production-readiness debt;
- G-01..G-13 (production readiness gate; Phase 7);
- unrelated technical debt (`docs/TECHNICAL_DEBT_REGISTER.md` TD items are
  Phase 7 / non-blocking for Phase 6 unless evidence shows direct
  contradiction of a Phase 6 DONE claim).

If the gate discovers a defect, it reports the defect, classifies it, and
routes it to the owning task/governance path. It does not silently fix it
inside P6-009.

Future ideas are not authorization.

## D6-08 / D6-09 Treatment

Current standing: DEFERRED under standing authority
(`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). Non-blocking for Phase 6
closure. The gate must verify that no D6-08/D6-09 surface was accidentally
introduced (AC10). This contract does not reactivate deferred scope; any
reactivation requires a newer superseding HPO decision.

## Acceptance Criteria

- [ ] AC1 — Integrated revision workflow round-trips through persistence and
  reload: text edits, timing edits, split, merge, and historical activation
  each persist and survive reload with ordering, identity, and language
  provenance intact.
- [ ] AC2 — Undo, redo, branch-after-undo, historical activation, and CAS
  preserve frozen revision semantics: strict-ancestor undo, unique-child
  redo, branch invalidates auto-redo (no guessing among siblings), version
  uniqueness across branches, stale-base rejection with no silent merge and
  no history rewrite.
- [ ] AC3 — Machine-source/original is recoverable from any valid revision
  state (including after structural edits, undo branches, and historical
  activation); machine rows were never mutated.
- [ ] AC4 — Translation staleness behaves consistently with P6-005 semantics
  (per-row `stale_at`/`staleness_reason`/`stale_caused_by_revision_id`,
  precedence, first/repeated lifecycle) and no forbidden Phase 5
  `translation_segments` mutation or remapping occurred.
- [ ] AC5 — Comparison/workspace presentation accurately reflects the current
  active revision and chronology: machine vs active vs persisted translation
  relationships are truthful; structurally incompatible revisions are never
  silently index-remapped; no false "edited after translation" claims.
- [ ] AC6 — Supported exports (TXT/SRT/VTT/DOCX) reflect the active revision
  with machine-source fallback/recoverability as contractually required
  (active revision authoritative when present; machine source authoritative
  when no active revision exists).
- [ ] AC7 — Stale/concurrent writes fail closed without partial mutation
  (expected-base concurrency; active-pointer CAS; version-uniqueness
  conflicts surface as domain conflicts, not raw database failures).
- [ ] AC8 — Authorization and transcription isolation remain intact:
  non-owner writes/activations rejected; cross-transcription operations
  rejected; invalid revision activation rejected; unauthorized editing
  rejected.
- [ ] AC9 — Required Unicode/language round-trip coverage remains valid
  (`ms`, `en`, `zh`, `ta`, `und`) across edited, structural, and exported
  views per approved Phase 6 scope.
- [ ] AC10 — No D6-08 or D6-09 surface has been introduced (no speaker
  labels/diarization, annotations, chapters/bookmarks-as-feature, or
  waveform/timeline artifacts, UI, schema, or routes).
- [ ] AC11 — Required regression, static-analysis, formatting, and browser
  gates pass: full PHP suite green, Pint clean, PHPStan level 7 with
  0 errors, required DC-01 browser/runtime verification retained, reserved
  Phase 4 hooks intact.

## Verification Requirements (Evidence Model)

Fresh execution required (current integrated proof; historical PASS results
may support but never replace it):

- Integrated targeted tests exercising the cross-capability workflow
  (revision + timing + structural + staleness + history + workspace) against
  real HTTP routes and persistence.
- Relevant editing/domain suites, relevant translation/workspace suites.
- Full PHP suite (`php artisan test --compact`).
- Pint (`vendor/bin/pint --dirty --format agent`) clean.
- PHPStan level 7 with 0 errors.
- Real-browser DC-01 runtime verification (Chromium per ADR-021): workspace
  presentation, history rendering, activation round-trip + reload durability,
  comparison truthfulness, conflict presentation; retained evidence JSON +
  spec under `verification/p6-009/`.

Historical evidence may support but not replace integration proof:

- `reviews/P6-005-INDEPENDENT-REVIEW.md` (14/14 PASS, VERIFIED).
- `reviews/P6-008-INDEPENDENT-REVIEW.md` (AC1–AC10 PASS, VERIFIED).
- Earlier component AC matrices and per-task browser artifacts.

Rule: where this gate explicitly requires current proof (AC1–AC11), the
executor must freshly execute and retain evidence; citing a historical
VERIFIED verdict for the same behaviour is insufficient by itself.

## Export Verification

Canonical formats (do not invent others): TXT, SRT, VTT, DOCX (ADR-019 D4-03;
P4-005 hardened; P6-001 §9 consumption semantics).

The gate must verify, on the product path with an active revision present and
with no active revision (machine fallback):

- TXT reflects active-revision order/text/language (or machine source when
  no active revision).
- SRT/VTT reflect active-revision text and active-revision timing at
  millisecond precision with deterministic ordering.
- DOCX reflects the active revision without regression.
- Completed-transcription gating and authorization remain enforced.
- Machine original remains recoverable through export-relevant views.

## Failure Semantics

If any mandatory gate AC (AC1–AC11) fails:

- P6-009 does not PASS.
- Phase 6 cannot be recommended for HPO closure.
- The defect is classified (BLOCKER/HIGH/MEDIUM/LOW per review conventions),
  attributed to its owning task or governance path, and recorded with
  evidence; P6-009 execution stops at the failure boundary for the affected
  dimension (unrelated dimensions may still be recorded where safe).
- Remediation requires separate authorization (owning-task correction cycle
  or new HPO decision); P6-009 does not repair, redefine, or retro-wire the
  owning behaviour.
- The final gate is re-run only after the owning issue is resolved through
  its own review/closure path.

P6-009 is not a repair task.

## Execution / Review Lifecycle

Repository-native flow (orchestration policy):

```text
DRAFT (contract authored 2026-09-25; non-executable)
→ HPO contract review + HPO-009-A/B/C resolution (DECIDED — DECISION-P6-009-READY-001, 2026-09-25)
→ READY (explicit HPO promotion to open the gate; execution authorized, not started) ← executed 2026-09-25
→ IN_PROGRESS (gate executor produces evidence under verification/p6-009/) ← current state (verdict FAIL recorded; awaiting HPO routing of F-001)
→ REVIEW (independent review; durable artifact in reviews/)
→ VERIFIED (reviewer verdict; no unresolved BLOCKER/HIGH)
→ DONE (HPO closure only)
```

This task is currently READY. Gate execution (IN_PROGRESS), review, and
closure have not occurred here.

## Independent Review Requirement

Gate results require independent review before HPO closure. The reviewer must
independently assess: gate evidence completeness, per-AC results, scope
integrity (no feature build, no D6-08/D6-09, no HPO-008-C retro-wire, no
Phase 7 pull-in), fresh test/Pint/PHPStan results, browser evidence,
invariant preservation (append-only, identity, CAS, machine immutability,
Phase 5 boundaries), and any findings with severity classification.

Gate execution and independent review must not be combined into one
self-approval step. The executor must not mark P6-009 VERIFIED or DONE.

## Phase 6 Closure Boundary

`P6-009 DONE` does not itself equal `PHASE 6 CLOSED`.

Instead: P6-009 DONE (HPO-closed on an independent VERIFIED verdict) →
the HPO reviews the terminal gate result → the HPO separately authorizes
Phase 6 closure. Only then may Phase 7 entry criteria be evaluated.

## Phase 7 Boundary

- No Phase 7 work is authorized by this contract.
- Production/deployment readiness (topology, supervision, security,
  monitoring, G-01..G-13, TD-001..TD-013 remediation) remains Phase 7 scope.
- Phase 7 still requires its own decisions (D7-01..D7-08) and authorization
  (`PHASE7-PLANNING.md` §H; `docs/PRODUCTION_READINESS_GATE.md`).
- P7 final gates (P7-012) cannot be pulled into Phase 6.

## HPO Decisions (DECIDED — `DECISION-P6-009-READY-001`, 2026-09-25)

- HPO-009-A — DECIDED: APPROVED. The P6-009 contract is accepted as the
  canonical Phase 6 FINAL_GATE_ONLY integration verification contract, and
  P6-009 is promoted `DRAFT` → READY. This authorizes the gate to be opened;
  it does not itself execute the gate.
- HPO-009-B — DECIDED: CONFIRMED. D6-08/D6-09 remain DEFERRED and
  non-blocking for Phase 6 closure under current authority
  (`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). Not reactivated by this
  decision; AC10 still requires the gate to verify neither was accidentally
  introduced.
- HPO-009-C — DECIDED: CONFIRMED. The HPO-008-C exclusion remains preserved;
  no P6-003/P6-004 staleness retro-wire is allowed inside this gate. P6-009
  verifies the existing approved Phase 6 state only; no remediation or
  semantic expansion is authorized.

## Implementation Notes

Gate execution completed 2026-09-25 (executor: OpenCode). AC1–AC5 and AC7–AC11
PASS with fresh evidence; AC6 FAIL (finding F-001, MAJOR — exports render the
machine source, not the active revision). Full evidence:
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`. Execution verdict: FAIL.
Phase 6 closure cannot be recommended until F-001 is resolved through a
separately authorized path and the gate is re-run.

### Rerun P6-009-RERUN-01 (2026-09-25, executor: OpenCode)

Full gate rerun under direct HPO rerun authorization (late-persisted as
`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`; the original citation of
`DECISION-P6-009-READY-001` + `DECISION-P6-010-CLOSURE-001` below is retained
verbatim for history but reclassified as context/eligibility only — see the
governance correction in ## Status and
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`; P6-010 = DONE). AC1–AC11 evaluated fresh; no
historical PASS reused as verdict evidence. The committed F-001 skip in
`tests/Feature/Editing/P6009FinalGateTest.php` was replaced with a fresh
integrated AC6 test (41 assertions: 4/4 formats from the active revision,
active timing, structural split segmentation, historical-activation flip,
machine fallback, machine immutability); AC9 export assertions were
strengthened to active-revision unicode payloads. Fresh rerun browser set
(`verification/p6-009-rerun-01/`, port 8130, dedicated DB): 12/12 passed
incl. strengthened RERUN-11 export-content check. Full evidence:
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`.
Execution verdict: PASS. Historical FAIL evidence preserved untouched.

### Files Changed

- `tasks/P6-009-phase6-integration-verification.md` (READY → IN_PROGRESS,
  executor record, this section).
- `tests/Feature/Editing/P6009FinalGateTest.php` (new gate test; 9 pass +
  1 documented F-001 skip).
- `verification/p6-009-seed.php`, `verification/p6-009-auth.setup.js`,
  `verification/playwright.p6-009.config.js`,
  `verification/p6-009/final-gate.spec.js`, `verification/p6-009/README.md`,
  `verification/p6-009-fixtures.json`,
  `verification/p6-009/p6-009-browser-results.json` (new harness + 12/12 results).
- `verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` (execution artifact).

No application, migration, route, or existing-test change was made. The
throwaway AC6 probe was deleted after capturing the failure output preserved
in the evidence artifact §4.

### Important Decisions

- HPO-009-A/B/C resolved DECIDED by `DECISION-P6-009-READY-001` (2026-09-25):
  contract accepted, P6-009 promoted READY, D6-08/D6-09 remain deferred, the
  HPO-008-C exclusion is preserved. No gate execution or remediation
  authorized by this governance batch.

### Known Limitations

- None by this task. Pre-existing non-blocking carryforwards (Phase 5
  LOW/INFO, P6-004 INFO, P6-005 MINOR-1/OPTIONAL-1, P6-008 OPTIONAL-1) are
  preserved in their owning artifacts and are not re-litigated here.

## Verification

First gate execution completed 2026-09-25. Fresh commands and exact results:

- `vendor/bin/pest tests/Feature/Editing/P6009FinalGateTest.php` → 10 tests,
  9 passed, 103 assertions, 1 skipped (AC6 F-001), 0 failures.
- `php artisan test --compact` → 892 tests, 890 passed, 3443 assertions,
  2 skipped (pre-existing 2FA + AC6 F-001 skip), 2 warnings (pre-existing), 0 failures.
- `composer lint:check` (Pint) → passed. `composer types:check` (PHPStan L7) → 0 errors.
- `playwright.cmd test -c verification/playwright.p6-009.config.js` → 12/12 passed.

Result:

FAIL — AC6 fails (F-001, MAJOR); AC1–AC5 and AC7–AC11 pass. Evidence:
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`. Lifecycle remains
IN_PROGRESS pending HPO routing of F-001. Not VERIFIED. Not DONE.

### Rerun P6-009-RERUN-01 verification (2026-09-25, fresh)

- `vendor/bin/pest tests/Feature/Editing/P6009FinalGateTest.php` → 10 tests,
  10 passed, 157 assertions, 0 skipped, 0 failures.
- `vendor/bin/pest tests/Feature/Editing/` → 127 passed, 707 assertions, 0 failures.
- Export suites (revision-aware + export + hardening + translation-export) →
  37 passed, 181 assertions, 0 failures.
- Translation/workspace suites (`Translation/` + `Comparison/` +
  `TranscriptExperience/`) → 165 passed, 717 assertions, 0 failures.
- `php artisan test --compact` → 900 tests, 899 passed, 3542 assertions,
  1 skipped (pre-existing 2FA), 4 empty-detail reporter warnings
  (pre-existing environment pattern, also on untouched files), 0 failures.
- `composer lint:check` (Pint) → passed. `composer types:check` (PHPStan L7) → 0 errors.
- `playwright.cmd test -c verification/playwright.p6-009-rerun-01.config.js`
  → 12/12 passed.

Result:

PASS — AC1–AC11 pass with fresh integrated evidence; F-001 remediation
RESOLVED. Evidence:
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`.
Historical FAIL (`verification/p6-009/`) preserved. Independently reviewed
VERIFIED (`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`; one MAJOR
procedural finding, reconciled). Not DONE.

## Review

Review File:

`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md` (Claude Code, independent of
the gate executor).

Review Status:

VERIFIED — AC1–AC11 and quality gates independently reproduced with zero
discrepancies; one MAJOR procedural finding (rerun authorization-record gap)
flagged for HPO resolution. Governance reconciliation
(`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`) late-persisted the
genuinely prior HPO authorization
(`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`) and confirmed REVIEW →
VERIFIED as legal; the HPO accepted the procedural finding as RECONCILED /
ACCEPTED FOR CLOSURE and closed P6-009 VERIFIED → DONE
(`DECISION-P6-009-CLOSURE-001`, 2026-09-25). The finding remains preserved
in the audit trail. Phase 6 remains OPEN pending separate HPO closure.

## Completion

P6-009 cannot move directly from READY to DONE. Required remaining flow
(orchestration policy):

READY (was current at authorization) → IN_PROGRESS (first execution complete,
verdict FAIL; F-001 routed via HPO-F001-A → P6-010 DONE) → RERUN
(P6-009-RERUN-01 executed fresh under direct HPO authorization, verdict PASS)
→ REVIEW → VERIFIED (independent review returned VERIFIED with one
MAJOR procedural finding, reconciled and HPO-accepted for closure) →
DONE (HPO closure `DECISION-P6-009-CLOSURE-001`, 2026-09-25).

The gate executor must not mark P6-009 VERIFIED or DONE. HPO closure of
P6-009 does not itself close Phase 6.

## Definition of Done

P6-009 is DONE only when: all AC1–AC11 are verified by fresh integrated
execution plus an independent review returning VERIFIED with no unresolved
BLOCKER/HIGH finding; evidence artifacts are retained under
`verification/p6-009/`; scope integrity holds (no feature build, no
D6-08/D6-09, no HPO-008-C retro-wire, no Phase 7 work); and the HPO closes
VERIFIED → DONE. P6-009 DONE enables, but does not itself constitute, HPO
Phase 6 closure.
