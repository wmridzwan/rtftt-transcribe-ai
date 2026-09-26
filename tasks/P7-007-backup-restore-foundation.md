# P7-007 — Backup / Restore Foundation (Restore Drill Deferred to Wave 3)

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-007-CLOSURE-001`) on the independent VERIFIED verdict
below. Builder-report F6 inaccuracy corrected in-report (claim
removed; path real but untested — follow-up recorded, non-blocking).
Drill remains deferred to Wave 3; G-08 not claimed.

VERIFIED — independent review (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md`
§3) 2026-09-26: AC1–AC8 independently reproduced PASS; no scope creep
into P7-002, no premature G-08/drill claim found anywhere. Non-blocking
finding: F6 (builder report claims an "unwritable target" test that does
not exist in `tests/Feature/Backup/`; the underlying code path is real
but untested as claimed) — not BLOCKER/HIGH. Not DONE; DONE requires HPO
closure.

History preserved: BACKLOG — CONTRACT_AUTHORED → reconciled → READY (HPO
`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-007-BUILDER-REPORT.md`;
drill remains deferred to Wave 3, G-08 not claimed) → VERIFIED with F6
non-blocking finding (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` §3,
2026-09-26) → DONE (HPO `DECISION-P7-007-CLOSURE-001`, 2026-09-26).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 2
preparation while Wave 1 was under independent review; planning only,
no implementation) → reconciled against final Wave 1 DONE interfaces
(P7-008 DONE `DECISION-P7-008-CLOSURE-001`; hook wording verified
verbatim against the final runbook: "Backup before migrate (hook to
P7-007 mechanism)", pg_dump forward reference, "hook, not
implementation") → READY. Not authorized for implementation;
READY != EXECUTION AUTHORIZATION. No code written under this contract.

Scope note: this contract covers the backup/restore FOUNDATION only
(mechanism + procedures + verification). The executed restore drill
(D7-07's mandatory drill) is explicitly deferred to Wave 3, after
P7-002 defines the PostgreSQL production datastore — a drill against
the pre-migration SQLite-era store cannot satisfy G-08. See §7.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-007
- Canonical title: Backup / Restore + Disaster Recovery (Foundation)
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion
  `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`, 2026-09-26; hook wording
  reconciled verbatim against final P7-008 DONE runbook — see §5)

## 2. Objective

Deliver the backup/restore foundation for the adopted posture (daily
backups, documented procedures, at least one executed restore drill —
D7-07/A): a working backup mechanism for the current pre-migration
store, documented backup and restore procedures covering both the
SQLite-era mechanism and the PostgreSQL-target interface (post-P7-002),
backup integrity verification, scheduling, retention-of-backups, and
the operator hooks P7-008's runbook already references — without
executing the production drill (Wave 3), without implementing the
P7-002 migration, and without owning retention purge (P7-011).

## 3. Why This Task Exists

- No backup job, backup schedule, restore procedure, or recovery
  evidence exists; the app has only ever run on dev stores.
- D7-07 resolved the posture (daily + drill, no strict RPO/RTO) but
  the mechanism is unowned; D7-01 fixed PostgreSQL as the target, so
  the mechanism must be datastore-matched (SQLite-era now, pg-native
  after P7-002).
- P7-008's runbook already references P7-007 hooks ("Backup before
  migrate", pg_dump interface, "P7-007 mechanism (hook, not
  implementation, here)") — the hooks point at an empty socket. This
  task fills the mechanism side of that interface.
- Production gate G-08 requires restore-drill + rollback evidence; the
  drill needs this foundation plus P7-002. Foundation now, drill in
  Wave 3.

## 4. Binding Decisions / ADRs

- D7-07 = OPTION A (daily backups, documented procedures, ≥1 executed
  restore drill, no strict RPO/RTO) —
  `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026. A backup job without
  demonstrated restore evidence is insufficient for the gate — hence
  the Wave 3 drill, owned by this task's follow-on scope (not this
  contract).
- D7-01 = OPTION B (self-hosted PostgreSQL) — the mechanism must be
  reconciled with the datastore: SQLite-era file-copy mechanism now;
  `pg_dump`/PITR-capable interface defined (not executed) for the
  post-P7-002 era. Do not implement the migration.
- D7-03 = OPTION A (local private storage) — media/artifact backup
  covers node-local private disk (permissions, capacity, consistency
  with the database snapshot).
- D7-06 = modified A (30-day retention) respected: backups are not a
  retention-purge bypass; backup retention windows defined here,
  purge owned by P7-011.
- `PHASE7-SCOPE-CONTRACT.md` §§B–F; `docs/PRODUCTION_READINESS_GATE.md`
  G-08. Do not reopen any D7 decision.

## 5. Dependencies

- Hard prerequisites (satisfied at policy level):
  - D7-07 and D7-01 resolved; scope contract adopted; Phase 6 CLOSED.
  - P7-005 DONE — backup job logging/diagnostics use P7-005
    conventions unchanged.
- Wave 1 interface dependencies (reconciled 2026-09-26 against DONE):
  - P7-008 DONE (`DECISION-P7-008-CLOSURE-001`) runbook hooks — this
    task implements the mechanism the hooks reference ("Backup before
    migrate", pg interface). Contract direction: P7-007 defines,
    P7-008 references. Reconciliation outcome: hook wording verified
    verbatim against the final `docs/DEPLOYMENT-RUNBOOK.md`
    ("Backup before migrate (hook to P7-007 mechanism)"; pg_dump
    forward reference; "P7-007 mechanism (hook, not implementation,
    here)"); no wording change required. AC2 environmental exception
    is preserved: this contract executes no reboot-cycle verification
    and treats AC2 as NOT PASS throughout (that obligation sits with
    P7-001 per `DECISION-P7-008-AC2-DISPOSITION-001`).
    Classification: CONTRACT_DEPENDENCY_ONLY.
  - P7-003 supervision spec — the backup job runs under supervision
    conventions (schedule, logging); consume, do not redefine.
- Out-of-wave hard dependency (Wave 3):
  - P7-002 (datastore migration) — the executed restore drill and the
    pg-native mechanism activation wait for P7-002. This contract
    delivers everything drill-independent.
- Soft dependencies: P7-001 env registry (backup schedule/retention
  keys registered when available; §9 names are authoritative).
- Downstream: Wave 3 drill (same task ID, follow-on authorization) →
  P7-012 gate G-08.

## 6. In Scope

1. Backup mechanism (SQLite-era, executable now): consistent snapshot
   procedure for the SQLite store + media/artifact tree (write-quiesce
   or copy semantics defined; no corrupt/half-written backups),
   scheduled daily, with integrity verification (restore-to-scratch +
   checksum/migration-status check) on every run.
2. PostgreSQL-target interface (defined, not executed): `pg_dump`
   (or equivalent) command shape, required roles/credentials handling
   (no secret in repo/logs), restore command shape, and the activation
   condition (P7-002 DONE) — so Wave 3 executes rather than designs.
3. Documented backup and restore procedures: step-by-step runbook
   content delivered to P7-008's runbook by reference (P7-008 owns the
   file; coordinate), covering daily operation, integrity-check
   failures, and disaster-restore ordering (datastore → media →
   verify → supervise).
4. Backup scheduling + retention-of-backups: daily cadence, minimum
   retained generations, storage-capacity accounting against the
   D7-03 local-disk posture, and the interaction with D7-06 purge
   (backups of purged data age out per the backup-retention window —
   documented, P7-011 owns purge itself).
5. Media/artifact coverage: database + private media tree + derived
   artifacts backed up as one named set with a manifest; partial-set
   restores explicitly unsupported (fail loudly, never half-restore).
6. Operator hooks: the exact commands/checks P7-008's "Backup before
   migrate" hook invokes, with exit codes and expected output.

## 7. Explicit Non-Scope

- The executed production restore drill (D7-07's mandatory drill) —
  deferred to Wave 3 after P7-002; this contract delivers everything
  the drill needs except the migrated datastore. Claiming G-08 here
  is prohibited.
- Datastore migration or rollback SQL (P7-002); deployment mechanics
  (P7-008); queue internals (P7-003); retention purge (P7-011);
  monitoring backends beyond P7-005.
- Point-in-time recovery / RPO ≤ 1h / RTO SLAs (D7-07 Options B/C
  deferred, not rejected; designing for them now is scope creep, but
  the pg interface must not preclude them).
- Backups as a retention bypass: no feature restores purged user data
  outside the documented disaster path; no new PII surface.

## 8. Architecture / Domain Contract

- Mechanism layering: an artisan backup command (SQLite-era) + a
  documented pg-native path (dormant until P7-002) behind one operator
  interface (same command name, `--driver` made explicit, no silent
  driver inference in production).
- Consistency contract: every backup set carries a manifest (timestamp,
  store driver + version, migration inventory hash reconciled against
  P7-008's `deploy/migrations-inventory.json`, media-tree checksum
  root, backup-tool version). Restore refuses manifest-mismatched sets.
- Scheduler: daily backup + per-run integrity check via the existing
  scheduler (P7-003 conventions: `withoutOverlapping`, logged to the
  P7-005 channel); missed-run alerting defined.
- Runbook ownership: P7-008 owns `docs/DEPLOYMENT-RUNBOOK.md`; this
  task delivers hook content as a referenced section, never a forked
  copy. Single-source rule: procedure text lives once.

## 9. Detailed Implementation Requirements

1. Backup command: consistent SQLite-era snapshot + media-tree capture
   + manifest write + per-run scratch-restore integrity check; all
   failure modes (locked store, unwritable target, checksum mismatch)
   fail loudly with actionable messages.
2. pg-native interface definition: exact commands, roles, credential
   plumbing (env keys named in-contract), activation condition, and a
   skipped-until-P7-002 test asserting the dormant path refuses with
   "requires P7-002" rather than half-executing.
3. Schedule + retention-of-backups: daily cadence, generation count,
   capacity math for the single-admin model, pruning with manifest
   update (prune never orphans the manifest chain).
4. Hook commands for P7-008 ("Backup before migrate" + verify hooks)
   with pinned exit codes and golden-output tests.
5. Manifest/migration reconciliation test: backup refuses (or warns
   loudly, HPO-visible choice documented) when the live migration
   inventory diverges from P7-008's pinned inventory.
6. Docs: procedures + disaster ordering + integrity-failure triage,
   delivered for runbook reference; backup-retention vs D7-06 purge
   interaction table.
7. `.env.example` additions limited to backup-owned keys (schedule,
   target, retention counts); P7-003/P7-008 keys untouched.

## 10. Failure / Recovery Semantics

- Failed backup fails loudly (alert + structured log) and never
  presents a partial set as valid: incomplete sets are quarantined
  with the manifest marked failed.
- Integrity-check failure blocks pruning (never delete the last good
  set because a new set looks plausible) and pages the operator path.
- Restore (when Wave 3 executes it) is all-or-nothing per set;
  partial restores are refused by manifest design.
- Missed daily run is itself a failure signal (stale-manifest check
  in `deployment:verify`).

## 11. Security / Privacy Requirements

- Backup sets contain full user data: encrypt-at-rest posture defined
  (minimum: filesystem permissions + documented operator access list;
  encryption adopted only by a follow-on HPO decision — record the
  residual risk explicitly, do not silently accept it).
- Credentials for backup targets never in the repo, logs, or
  manifests; redaction tests cover command output and retained
  evidence.
- Restore procedures include an access-control step (who may invoke a
  restore, audit-logged).

## 12. Observability / Operations Requirements

- Every backup run logs start/finish/duration/set-name/manifest hash/
  integrity verdict through the P7-005 channel with correlation
  fields; failures include the triage pointer.
- Diagnostics command gains a backup section (last-good set age,
  schedule presence, target writability, manifest chain health) within
  P7-005 field naming; stale last-good age is a failing check.
- Missed-run / stale-set alerting wired to the operator path the
  runbook defines.

## 13. Acceptance Criteria

- AC1: Daily SQLite-era backup produces a manifest-valid set;
  per-run scratch-restore integrity check passes; failures fail
  loudly with no partial-valid presentation.
- AC2: pg-native path defined with exact commands/roles/plumbing and
  refuses dormant-execution with the P7-002 activation message
  (test-asserted); no pg mechanism half-executes pre-migration.
- AC3: P7-008 hooks ("Backup before migrate" + verify) implemented
  with pinned exit codes and golden-output tests; hook wording
  reconciled against VERIFIED P7-008.
- AC4: Retention-of-backups + capacity math documented; pruning keeps
  the manifest chain intact; last-good-set protection proven (never
  pruned on a failed run).
- AC5: Manifest/migration-inventory reconciliation enforced;
  divergence fails loudly.
- AC6: Procedures complete and single-sourced (no forked runbook
  copy); disaster ordering documented.
- AC7: Drill explicitly NOT executed and NOT claimed; Wave 3 drill
  prerequisites listed (P7-002 DONE + this foundation DONE).
- AC8: Full suite green, Pint clean, PHPStan 0, no P6 regression, no
  Wave 1 file semantically altered (diff audit).

## 14. Test / Verification Requirements

- Feature tests: backup success + manifest validity, each failure
  mode (locked/unwritable/mismatch), integrity-check pass/fail,
  pruning + last-good protection, manifest/inventory divergence,
  dormant pg-path refusal, hook exit codes/golden output,
  stale-set diagnostics.
- Scratch-restore integrity check runs for real in the verification
  environment (small seeded fixture set); production-scale timing is
  Wave 3/9 concern, not claimed here.
- Full PHP suite + Pint + PHPStan level 7; Phase 6 suites in the run.

## 15. Technical-Debt Mapping

- No TD item closes under foundation scope. G-08 drill evidence is
  the Wave 3 close-out; this task records the foundation as
  drill-ready, not drill-complete.
- Consumes TD-003 supervision conventions and P7-008 inventory; owns
  no TD remediation. Nothing marked closed.

## 16. Risks / Regression Concerns

- SQLite backup consistency under write load: define quiesce/copy
  semantics explicitly and test with concurrent writers; never ship
  "copy the open file and hope."
- Runbook fork risk: procedure text must live once (P7-008's file);
  this task's contribution is a referenced section — enforce by diff
  audit, not by convention alone.
- pg-path dormancy must be a hard refusal, not dead code that rots:
  the dormant-path test runs in CI permanently until P7-002
  activates it.
- Backup-target capacity on node-local disk (D7-03/A): generations ×
  media size must fit the planned disk with margin; the capacity math
  is a reviewed artifact, not a comment.

## 17. Completion Evidence

- Builder report (files, AC verdicts + evidence pointers, test
  matrix incl. full-suite counts/Pint/PHPStan, TD mapping noting
  nothing closed, known limitations).
- Retained artifacts: sample manifest, integrity-check log,
  capacity math, hook golden outputs, procedure text for runbook
  reference.

## 18. Reviewer Checklist

- [ ] Every backup failure mode fails loudly; no partial set is ever
      presented as valid; last-good protection proven by test.
- [ ] pg path is defined-but-dormant with a hard refusal test; no
      pg mechanism executes pre-P7-002.
- [ ] P7-008 hooks reconciled against DONE P7-008 wording (verified
  verbatim 2026-09-26); no forked runbook copy (single-source audit).
- [ ] Manifest/inventory reconciliation enforced; drill NOT claimed;
      Wave 3 prerequisites explicit.
- [ ] Backup data protection posture (§11) explicit incl. any residual
      risk; no secret in repo/logs/evidence.
- [ ] Full suite + Pint + PHPStan reproduced; no P6 regression; no
      Wave 1 file semantically altered.

## 19. State Transition Rule

READY (HPO promotion `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`,
2026-09-26; hook wording reconciled verbatim against final P7-008 DONE
runbook per §5). READY → IN_PROGRESS only when work begins under an
explicit HPO Wave 2 execution authorization (not granted). The Wave 3
drill is a separate follow-on scope requiring P7-002 DONE and its own
authorization — it is not granted by any Wave 2 act. REVIEW →
VERIFIED/CHANGES_REQUESTED by the independent reviewer; VERIFIED →
DONE only by HPO closure. `READY != EXECUTION AUTHORIZATION.`
