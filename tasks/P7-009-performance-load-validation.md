# P7-009 — Performance / Load / Large-File Validation

## Status

IN_PROGRESS — Phase A execution begun 2026-09-26 under HPO
`DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001` (harness
preparation + rehearsal on available infrastructure only; Phase B
final production-shaped capacity run NOT AUTHORIZED). P7-009 remains
IN_PROGRESS after Phase A.

READY — promoted by the HPO on 2026-09-26
(`DECISION-P7-009-READY-PROMOTION-001`) after reconciliation against
final DONE state (P7-002 DONE, P7-004 DONE, P7-011 DONE; D7-08/A
binding; TD-001 C/C2 evidence retained; no hidden dependency on
drill/TD-008/TD-014/P7-012; measure-and-report semantics need no new
thresholds — P7-012 decides). Not authorized for implementation;
READY != EXECUTION AUTHORIZATION. No code written under this
contract. Final run additionally requires readiness confirmation +
explicit HPO execution authorization.

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 3
preparation) → RECONCILED + READY (HPO
`DECISION-P7-009-READY-PROMOTION-001`, 2026-09-26).

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-009
- Canonical title: Performance / Load / Large-File Validation
- Phase: 7 — Production Hardening
- Proposed state: BACKLOG (CONTRACT_AUTHORED; harness may be prepared
  early, final run only after P7-002 + P7-004 DONE + HPO READY promotion)

## 2. Objective

Deliver the measurable single-admin low-concurrency capacity envelope
(D7-08/A): prove the 500 MiB upload through the deployed stack (G-01),
transcription/translation throughput, streaming/render/export behavior
under target load, and the retained full-chain evidence inputs that
P7-012 (G-10/G-12) consumes.

## 3. Why It Exists

The 500 MiB product boundary was never proven through a production HTTP
stack (TD-002 DEFERRED DEPLOYMENT REQUIREMENT); inference latency,
capacity, and end-to-end behavior are unmeasured (TD-001); the G-10/G-12
gates need a concrete envelope, not estimates. Earlier wave plans
suggested a 3-task Wave 3 (P7-002/P7-004/P7-011); the dependency graph
shows P7-009 is equally Wave 3-scoped and must not be forgotten.

## 4. Binding D7 Decisions / ADRs

- D7-08 = OPTION A (single-admin low-concurrency) —
  `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026: the envelope is
  concrete and measurable for THIS model; no multi-tenant proof; scale-up
  needs a fresh decision.
- D7-01/B, D7-02/A, D7-03/A consumed as the measured topology (pg +
  supervised Redis + local storage).
- TD-001 STEP C/C2 evidence (`verification/REAL-MODEL-FULL-CHAIN-E2E.md`,
  `verification/LARGE-V3-FULL-CHAIN-E2E.md`) retained as history and
  prerequisite evidence — not re-litigated, not substituted for the
  deployed-stack run.

## 5. Dependencies

- Wave 2: `CONTRACT_DEPENDENCY_ONLY` (P7-001 config/audit interface for
  the G-01 proof; P7-003 supervised-queue posture the load run exercises).
  No Wave 2 implementation dependency beyond interfaces.
- Hard prerequisites (Wave 3-internal): P7-002 DONE (pg production store
  measured, not SQLite) + P7-004 DONE (production storage behavior
  measured). Final run before either is DONE is invalid evidence.
- Consumed DONE: P7-001 (validated config), P7-003 (supervision),
  P7-008 (deployment the harness runs against), P7-006 (abuse limits
  active during load), P7-007 (backup posture noted, not stressed here).
- Downstream: P7-012 gates G-01/G-10/G-12.
- Harness-vs-run split: harness scaffolding (scripts, fixtures, timers)
  may be prepared once this contract exists; the FINAL measured run is
  gated as above.

## 6. In Scope

1. Capacity-envelope definition for D7-08/A (primary workflow +
   worker/job/storage/database limits, concrete numbers).
2. 500 MiB multipart proof through the deployed HTTP stack
   (PHP/web-server/proxy limits, timeouts, temp + durable capacity) —
   the retained G-01 artifact.
3. FFprobe-on-large-media, transcription RTF on target hardware
   (canonical large-v3), translation latency/throughput (canonical NLLB
   runtime), concurrent-job saturation, streaming under load, long-
   transcript browser render, export generation timings.
4. Measurement harness (repeatable scripts, retained raw results).
5. Results report with pass/degrade/fail per envelope line item.

## 7. Explicit Non-Scope

- Fixing capacity shortfalls by redesign (findings route to governance;
  this task measures and reports).
- P7-002 migration, P7-004 storage work, P7-011 purge (consumed inputs).
- Multi-tenant/load proof (excluded per D7-08).
- The P7-012 gate verdict itself (this task supplies evidence).

## 8. Architecture / Domain Contract

- Measure the production-shaped stack (pg + supervised Redis + local
  storage + deployed topology), never the Windows dev box as a
  production proxy; dev-box numbers are labeled substitute-only.
- Product limits (500 MiB boundary) distinguished from benchmark targets
  (`PHASE7-PLANNING.md` §F).
- Attempt-token fencing / stale recovery / retry behavior are proven by
  contract + two-process suites (P5-008 precedent), not by destructive
  real-model scenarios; the successful path is real end to end.

## 9. Detailed Implementation Requirements

1. Envelope doc: each line item (upload, probe, RTF, translation,
   concurrency, streaming, render, export) with target + method.
2. Harness scripts (idempotent, timer-instrumented, raw-output retaining).
3. G-01 artifact: real 500 MiB multipart upload through the deployed
   stack with full limit/timeout/capacity chain evidenced.
4. Large-v3 transcription + NLLB translation measurements on target hardware.
5. Saturation run (worker/job/storage/database limits observed, not exceeded
   destructively).
6. Results report mapped to G-01/G-10/G-12 inputs.

## 10. Failure / Recovery Semantics

- Over-limit/degraded outcomes are FINDINGS (reported with numbers),
  not task failures — unless the harness itself is defective.
- Interrupted runs resume by re-execution (no partial-result stitching
  presented as a single run).

## 11. Security / Privacy Requirements

- Load fixtures are synthetic; no real user media used for saturation.
- Abuse limits (P7-006) stay armed during load (no guard disabling to
  "make numbers pass").
- Results contain no secrets/credentials.

## 12. Observability / Operations Requirements

- Runs emit P7-005-channel structured lines where the harness executes
  in-app; raw timer logs retained alongside.
- Report is human-readable by default, machine-consumable where cheap.

## 13. Acceptance Criteria

- AC1: Envelope doc complete for D7-08/A (all §6.1 lines).
- AC2: G-01 artifact retained (real 500 MiB through deployed stack).
- AC3: RTF + translation measurements retained (canonical models).
- AC4: Saturation/degradation behavior recorded per line item.
- AC5: Streaming/render/export timings retained.
- AC6: No finding hidden (degrades reported as degrades).
- AC7: Standard gate for harness code (suite/Pint/PHPStan).
- AC8: No gate verdict claimed (evidence only; P7-012 decides).

## 14. Test / Verification Requirements

- Harness self-tests (timer math, fixture integrity, report rendering).
- Retained `verification/p7-009/` bundle (raw + report).
- Target-host execution recorded (host identity, versions, model revisions).

## 15. Technical Debt Mapping

- TD-001 (HIGH/YES): G-12 prerequisite evidence supplied here (chain
  measured on deployed stack); CLOSED only at P7-012.
- TD-002 (HIGH/YES): G-01 proof owned here; closes at P7-012.
- TD-008: harness flakiness flagged, not hidden, if encountered.

## 16. Risks / Regression Concerns

- Dev-box-as-proxy risk (mitigated: target-host labeling rule).
- Long-run nondeterminism (mitigated: repeats + variance reported).
- Premature-run risk (final run before P7-002/P7-004 DONE presented as
  evidence — prohibited by §5 gating).

## 17. Completion Evidence

- Builder report with AC table, envelope doc, raw results + report,
  harness file list; retained `verification/p7-009/` bundle.

## 18. Reviewer Checklist

- [ ] Final run demonstrably post-P7-002 + post-P7-004 DONE.
- [ ] G-01 artifact is a real deployed-stack upload (not dev-box).
- [ ] Canonical models measured (large-v3 / NLLB runtime pinned).
- [ ] Degrades reported, none relabeled as passes.
- [ ] No gate verdict claimed; no redesign smuggled in.
- [ ] Harness standard gate green.

## 19. State Transition Rule

BACKLOG (CONTRACT_AUTHORED) → READY only by explicit HPO promotion
after P7-002 DONE + P7-004 DONE (plus readiness confirmation).
Harness preparation may proceed once Wave 3 execution is authorized;
the FINAL run additionally requires the §5 prerequisites satisfied.
READY → IN_PROGRESS only under explicit HPO authorization. REVIEW →
VERIFIED/CHANGES_REQUESTED by independent review (max 3 cycles, then
BLOCKED); only the HPO closes VERIFIED → DONE.
