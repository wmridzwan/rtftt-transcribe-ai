# P7-011 — Retention, Derived-Artifact + Orphan Cleanup

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-011-CLOSURE-001`) on the independent VERIFIED verdict
below (both cycle-1 MEDIUMs resolved; no BLOCKER/HIGH/MEDIUM; new LOW
carried as TD-014). TD-007 stays OPEN.

VERIFIED — independent re-review (Claude Code) returned VERIFIED on
2026-09-26 for corrective cycle 1
(`reviews/P7-011-INDEPENDENT-REVIEW-CYCLE2.md`). Both cycle-1 MEDIUM
findings independently confirmed RESOLVED with direct reproduction (not
accepted on the builder's description alone); no BLOCKER/HIGH; one new
LOW, non-blocking observation recorded (staging-claim crash-recovery gap,
pre-existing since cycle 0, out of this cycle's scope). Fresh gates
independently reproduced: retention suite 27/27, full suite ×2 exact
match (1077/1076 passed/1 skip/4066 assertions, 0 failures), Pint clean,
PHPStan L7 0 errors. TD-007 stays OPEN (closure deferred to P7-012 G-09
consumption). Not DONE — VERIFIED → DONE closure is an HPO action.
Cycle-1 review history preserved unchanged below, not rewritten.

Superseded status text (cycle-1 resubmission, preserved for history):
REVIEW — corrective cycle 1 complete 2026-09-26, resubmitted for
independent re-review. Both cycle-1 MEDIUM findings corrected
(builder report `reviews/P7-011-BUILDER-REPORT.md` § Corrective
Cycle 1): (1) purged-source disclosure implemented on the
transcription surface + `hasPhysicalFile()` purged-aware; (2)
staging claim retained + released on failed byte deletion with a
6-step retry regression test. History below preserved unchanged;
CHANGES_REQUESTED record retained, not rewritten.

CHANGES_REQUESTED — independent review (Claude Code) returned cycle 1 of
max 3 on 2026-09-26 (`reviews/P7-011-INDEPENDENT-REVIEW.md`). Two MEDIUM
findings, no BLOCKER/HIGH: (1) §8.4's required "source purged"
transcript-surface state is not implemented and `docs/RETENTION-POLICY.md`
inaccurately claims it is (AC7 fails as written); (2)
`RetentionPurge::purgeStaging()` deletes the `StagingClaim` row
unconditionally even when the file deletion failed, violating the §6 item 2
row/file atomicity discipline. Implementation owner (OpenCode, unchanged)
fixes both and resubmits to REVIEW.

History preserved: BACKLOG — CONTRACT_AUTHORED + RECONCILED → READY (HPO
`DECISION-P7-011-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-P7-011-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-011-BUILDER-REPORT.md`;
TD-007 stays OPEN) → CHANGES_REQUESTED (`reviews/P7-011-INDEPENDENT-REVIEW.md`,
cycle 1 of 3). Not VERIFIED, not DONE.

IN_PROGRESS — implementation begun 2026-09-26 under
`DECISION-P7-011-EXECUTION-AUTHORIZATION-001`.

READY — promoted by the HPO on 2026-09-26
(`DECISION-P7-011-READY-PROMOTION-001`) after reconciliation against
final DONE state (see §6.10, §8.4, §10 amendments + history below).
Not authorized for implementation; READY != EXECUTION AUTHORIZATION.
No code written under this contract.

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 3
preparation) + RECONCILED 2026-09-26 against final DONE state (P7-004 DONE
`DECISION-P7-004-CLOSURE-001`; P7-002 DONE; P7-007 manifest semantics;
P6 frozen invariants; scheduler conventions). NOT READY — awaiting
HPO READY promotion only.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-011
- Canonical title: Retention, Derived-Artifact + Orphan Cleanup
- Phase: 7 — Production Hardening
- Proposed state: BACKLOG (CONTRACT_AUTHORED; awaiting P7-004 VERIFIED +
  HPO READY promotion)

## 2. Objective

Implement the D7-06 30-day time-based automatic purge with safe deletion
behavior, audit records, and recovery semantics — covering primary media,
derived artifacts, and orphaned records — while preserving the Phase 6
revision/history invariants and resolving the Option D staging-cleanup
posture (TD-007 implementation ownership).

## 3. Why It Exists

No automated retention/cleanup exists: abandoned staging accumulates by
accepted interim cost (Option D), derived artifacts (prepared audio,
exports) have no lifecycle, and orphaned rows/files have no reconciler.
D7-06 decides the policy (30-day auto-purge); this task implements it and
carries the G-09 gate input.

## 4. Binding D7 Decisions / ADRs

- D7-06 = MODIFIED OPTION A (30-day retention auto-purge) —
  `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026. Owner-policy portion
  of TD-007 resolved; implementation OPEN here.
- ADR-013/ADR-014/ADR-016 (Option D): the explicit Option D outcome
  (HPO-07) is implemented/recorded here — run/schedule under the proven
  mechanism or accept + monitor, per the D7-06 retention semantics.
- D7-03/A (storage truth owned by P7-004); D7-07/A (backup retention
  counts owned by P7-007 — purge must not delete protected generations).
- Phase 6 frozen semantics: no orphaned revision records; machine source
  immutable; durable graph/history intact.

## 5. Dependencies

- Binding rule CONFIRMED STILL IN FORCE: P7-011 must not start before
  P7-004 VERIFIED (`docs/TECHNICAL_DEBT_REGISTER.md` TD-007 entry:
  "gated additionally on P7-004 VERIFIED"; Wave 2 READY promotion basis).
  Classification for Wave 2: `CONTRACT_DEPENDENCY_ONLY` (P7-007
  retention-of-backups interface; P7-001 registry) — no Wave 2
  implementation dependency. Wave 3-internal hard gate: P7-004 VERIFIED.
- Consumed DONE: P7-004 (storage truth purge operates on), P7-007
  foundation (backup generations protected; retention-math interface),
  P7-001 (registry for retention keys), P7-008 (runbook/scheduler
  conventions), P7-005 (audit logging channel).
- Downstream: P7-012 gate G-09.

## 6. In Scope

1. Purge eligibility rules (§6.10 canonical clock + eligibility table:
   per-artifact-class start events and durations, grounded below).
2. Safe deletion behavior (row + file atomicity discipline per P2-002B
   compensation precedent: never a row without its file disposition
   decided, never file deletion adopted into a new record).
3. Failed/incomplete processing behavior (never purge active/retryable
   work; stale-recovery coordination with queue semantics).
4. Retry/idempotency (purge runs idempotent; partial-failure recovery by
   resume; never double-delete accounting).
5. User-triggered deletion interaction (explicit cascade confirmation
   ADR-005 preserved; auto-purge never overrides an explicit user hold
   where contracted).
6. Audit/history records (every purge decision logged; no orphaned
   revision records left behind).
7. Export-before/after-purge behavior (contracted per class).
8. User-facing retention disclosure (docs/support copy update).
9. TD-007 implementation ownership (Option D outcome executed here).

## 6.10 Canonical 30-day clock + eligibility table (reconciled 2026-09-26)

D7-06 (ADR-026) retains source media and purge-eligible derived
artifacts "for 30 days after successful processing/completion" and
explicitly delegates the clock start to this contract. The table below
is deterministic and evidence-grounded — no new product policy is
invented; each start event is an existing persisted timestamp or an
existing accepted policy:

| Artifact class | Canonical clock start | Duration | Notes |
|---|---|---|---|
| Source media bytes + transcript artifacts (segments, revisions, prepared audio, translations) of a transcription | owning `transcriptions.completed_at` (nullable timestamp set at atomic completion; Phase 3) | 30 days | `completed_at IS NULL` → clock never starts → never eligible (see failed row) |
| Transcriptions never completed (draft/queued/preparing/transcribing/failed/cancelled) and their artifacts | — (no clock) | — (excluded) | B3-03 imposes no retry cap, so failed-but-retryable work must never age into deletion; ADR-005 user-triggered deletion remains the only path |
| Abandoned staging artifacts (`media/.staging` + `staging_claims`) | `staging_claims.created_at` (file mtime fallback) | 24 hours | Existing accepted policy (ADR-009); this task implements the Option D outcome, it does not invent the threshold |
| Quarantined malware artifacts | — (excluded from auto-purge) | — | P7-006 security ownership; deletion only by explicit operator action (never silent, never scheduled) |
| Backup generations | — (excluded) | — | P7-007 retention math owns them; purge respects, never redefines |
| Domain/history rows (media_files, transcriptions, segments, revisions, translations) + purge audit rows | — (never hard-deleted by auto-purge) | — (retained; tombstoned, see §8.4) | D7-06 "what records remain"; FK cascades would destroy revision history, which D7-06 forbids |

`updated_at` is NEVER a clock (any incidental touch would reset
retention). `created_at` is used only for staging (ADR-009), never as
a substitute completion event.

## 7. Explicit Non-Scope

- Backup-generation pruning (owned by P7-007 retention math; this task
  respects it, never redefines it).
- Storage topology changes (P7-004); migration content (P7-002);
  malware-scan behavior (P7-006); capacity measurement (P7-009).
- Tenancy/authorization redesign (reserved DC-02).

## 8. Architecture / Domain Contract

- Purge is a scheduled, idempotent reconciler (P7-008 scheduler
  conventions: `withoutOverlapping`, logged to the P7-005 channel),
  NOT inline deletion in request paths.
- Storage truth owned by P7-004; backup generations owned by P7-007;
  this task deletes only what both owners' contracts mark eligible.
- P6 invariants are deletion invariants: purge traverses the revision
  graph (never strands a revision, never deletes an active revision's
  ancestry out from under it, never touches the immutable machine source
  except through whole-transcription lifecycle rules).

## 8.4 Physical vs history deletion boundary (reconciled 2026-09-26)

`DELETE PHYSICAL ARTIFACT` and `DELETE DOMAIN / AUDIT HISTORY` are
distinct operations and auto-purge performs ONLY the first:

- Physical purge deletes BYTES: source media objects, prepared-audio
  objects, staging objects. Each deletion is verified (object gone)
  before any row is marked.
- Domain/history rows are NEVER hard-deleted by auto-purge. Purged
  lifecycles are tombstoned (additive purge marker on the owning row,
  e.g. a nullable `purged_at`-style column — additive migration only),
  preserving the full revision graph, translations, and audit trail.
  Hard-deleting a transcription row would cascade-destroy revision
  history through the existing FK cascades — D7-06 forbids exactly this.
- Post-purge behavior (no P6 redesign): transcript/history surfaces
  render from retained rows with an explicit "source purged" state;
  text exports (TXT/SRT/VTT/DOCX, derived from DB segments) keep
  working; media download/playback report the purged state instead of
  a bare 404. Pre-expiry behavior is unchanged (full export +
  playback).
- User-triggered deletion (ADR-005 + TASK-002A fresh-query guard) is
  unchanged and remains the ONLY path that removes domain rows; auto-
  purge never overrides an explicit user hold where contracted and
  never escalates a tombstone into a row deletion.

## 9. Detailed Implementation Requirements

1. Eligibility matrix per artifact class (clock start, duration, exclusions).
2. Scheduler command + overlap/missed-run behavior.
3. Claim-before-delete discipline for shared/racy candidates (P2-004A2
   CAS precedent where DB-coordinated; filesystem candidates reconciled,
   never adopted).
4. Dry-run mode (report eligible, delete nothing) for operator review.
5. Audit record schema + retention of audit rows themselves.
6. User disclosure copy (where the 30-day policy is surfaced).
7. Option D outcome recorded (schedule active or accept+monitor with
   accumulation alerting).
8. Runbook deltas by reference (P7-008 owns the file).

## 10. Failure / Recovery Semantics

- Partial-failure recovery: run checkpoints; resume continues, never
  restarts destructively; failures loud (exit codes, structured logs,
  verify signal).
- A failed purge run never leaves half-deleted logical objects
  presented as complete (per-object atomicity or explicit failed state).
- Already-missing artifacts are idempotent no-ops (verified-missing
  before delete; never an error, never double-counted).
- Concurrent user deletion wins: purge claims each object before
  acting; if the row is gone (user deleted under ADR-005), the purge
  treats it as already-deleted and never resurrects or re-marks it.
- Export concurrency is a non-issue by architecture: exports are
  generated on demand from retained DB rows, so there is no export
  artifact file to race with; tombstoned rows export text with the
  "source purged" state (§8.4).
- Scheduler re-entry is fenced by `withoutOverlapping` (P7-008
  conventions) plus per-run idempotency; a second run overlapping the
  first observes claimed/in-progress objects and skips them.

## 11. Security / Privacy Requirements

- Stale user media at rest minimized (the privacy-hygiene motive for
  TD-007); purge logs are metadata-only (no media bytes, no secrets).
- Destructive scope fenced: purge commands refuse out-of-scope targets
  (explicit driver/target pattern from the P7-007 precedent).

## 12. Observability / Operations Requirements

- Every purge decision + outcome through the P7-005 channel with
  correlation fields; no format altered.
- `deployment:verify` retention sub-check (additive, P7-008 conventions).
- Diagnostics retention section (additive).

## 13. Acceptance Criteria

- AC1: Eligibility matrix implemented per D7-06 (clock-start evidenced
  per class).
- AC2: Active/retryable work never purged (adversarial tests).
- AC3: P6 invariants hold post-purge (no orphaned revisions; graph
  validation suite green).
- AC4: Idempotency + resume evidenced (kill-mid-run rehearsal).
- AC5: Audit records complete (every deletion accounted).
- AC6: ADR-005 user-deletion interaction preserved.
- AC7: Disclosure copy present.
- AC8: Option D outcome recorded + executed (or accept+monitor with alert).
- AC9: Backup generations respected (P7-007 compatibility tests green).
- AC10: Standard gate (full suite/Pint/PHPStan 0; no Wave 2 file
  semantically altered beyond contracted deltas).
- AC11: No G-09 claim beyond evidence (gate consumption belongs to P7-012).

## 14. Test / Verification Requirements

- New `tests/Feature/Retention/` suites: eligibility, adversarial
  active-work protection, graph validation, idempotency/resume,
  audit completeness, disclosure presence.
- Dry-run vs live behavioral tests.

## 15. Technical Debt Mapping

- TD-007 (MEDIUM/HPO_DECISION_REQUIRED): implementation owner = this
  task (owner-policy already RESOLVED by D7-06); closes at P7-012 G-09
  consumption.
- TD-011: untouched (P7-004). TD-002/003/004: untouched.

## 16. Risks / Regression Concerns

- Unsafe-deletion risk (the reason Option D forbids the unresolved
  mechanism): mitigated by eligibility fencing + dry-run + claim
  discipline + adversarial tests.
- P7-004-truth drift (mitigated: consume, never redefine).
- P7-007-generation collision (mitigated: compatibility tests).
- Scheduler shared surface with P7-007's `backup:run` schedule
  (`routes/console.php` coordination; overlap protection both).

## 17. Completion Evidence

- Builder report with AC table, eligibility matrix, dry-run + resume
  rehearsal logs, file list; retained `verification/p7-011/` bundle.

## 18. Reviewer Checklist

- [ ] P7-004 VERIFIED prerequisite satisfied before implementation began.
- [ ] No orphaned revision left in any purge scenario tested.
- [ ] Active/retryable work provably never eligible.
- [ ] No P7-004 truth redefinition; no P7-007 generation redefinition.
- [ ] Audit completeness (no silent deletions).
- [ ] Disclosure copy present and accurate.
- [ ] No G-09 gate claim beyond evidence.
- [ ] Standard gate green.

## 19. State Transition Rule

BACKLOG (CONTRACT_AUTHORED) → READY only by explicit HPO promotion
after P7-004 VERIFIED (binding) plus readiness confirmation.
READY → IN_PROGRESS only under a separate explicit HPO Wave 3 execution
authorization. REVIEW → VERIFIED/CHANGES_REQUESTED by independent
review (max 3 cycles, then BLOCKED); only the HPO closes VERIFIED → DONE.
