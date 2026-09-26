# Phase 7 Wave 2 — Independent Review (P7-001, P7-006, P7-007)

Reviewer: Claude Code (independent reviewer role; did not implement any of
these tasks). Builder: OpenCode. Review date: 2026-09-26.

Authorization basis: `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`
(execution authorized, scope: P7-001 + P7-006 + P7-007 only, parallel-safe
with file-ownership sequencing). Task states confirmed REVIEW at review
start (`tasks/P7-001-*.md`, `tasks/P7-006-*.md`, `tasks/P7-007-*.md`).
Builder handoff: `PHASE 7 WAVE 2 IMPLEMENTATION COMPLETE — AWAITING
INDEPENDENT REVIEW`.

Method: fresh evidence was independently reproduced by reading source,
running commands, and re-running the test suite/static analysis myself and
via three parallel independent evidence-gathering passes (one per task),
each of which re-derived evidence from the repository rather than
restating the builder reports. Builder reports
(`reviews/P7-001-BUILDER-REPORT.md`, `reviews/P7-006-BUILDER-REPORT.md`,
`reviews/P7-007-BUILDER-REPORT.md`) were treated as claims to verify, not
evidence.

## 0. Review Baseline

- HEAD: branch `main`, latest commit `4d90d5b docs(governance): close Phase 6`.
  Working tree carries substantial uncommitted pre-existing residue (Phase
  6/7 documentation, prior task/review files) predating this review —
  preserved untouched, not part of this review's scope.
- Wave 2 authorization confirmed in `DECISIONS.md`
  (`DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`,
  `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`).
- Task states confirmed REVIEW for all three at review start.
- No Wave 3 task (`P7-002`, `P7-004`, `P7-009`, `P7-011`) shows any status
  other than `BACKLOG — CONTRACT_AUTHORED`; none were started.
- No unauthorized scope found (see §5).

## 1. P7-001 — Production Configuration + Env Validation

### AC Table

| AC | Requirement | Evidence Independently Verified | Result |
|---|---|---|---|
| AC1 | Fail-fast boot per required key | `ProductionConfigGuard`/`ProductionPostureChecks::assertValid()` both throw in production, no-op elsewhere (`AppServiceProvider::boot()` calls both, additively). `tests/Feature/Deployment/ProductionPostureChecksTest.php` (6 tests) exercises per-violation-class isolation incl. a production-mode throw. Reproduced: targeted suite 42/42 across 3 independent runs. | PASS |
| AC2 | Limit audit vs 600 MiB + overhead | `app/Deployment/UploadLimitAudit.php`: `REQUIRED_BYTES=629,145,600`. Live `php artisan env:audit-limits --json` reproduced independently, output matches builder's claim exactly (exit 1, `upload_max_filesize`/`post_max_size` findings against 512M/520M dev ini). | PASS |
| AC3 | TD-004 certified per environment | `RedisPosture.php`: loopback-without-password recorded, not a violation; non-loopback-without-password is an unconditional violation (detection not environment-gated; enforcement is). Live Redis reachable on this box; version capture (`3.0.504`) independently reproduced via direct script execution, not skipped. | PASS |
| AC4 | Registry single-ownership; unregistered key fails CI | `ProductionEnvRegistry.php` registers **71** keys (builder report says "70" — see Finding F1). `.env.example` fully registered; negative unregistered-key test present. CI gate is indirect — the registry-completeness test runs as part of the whole Pest suite via `composer ci:check` → `@test`; there is no separately named "env ownership" CI step (see Finding F2). No P7-003 queue key or P7-008 matrix entry redefined (diff-audited). | PASS |
| AC5 | No secrets in repo/logs/evidence | `.env.example` diff shows zero new P7-001 keys (by design); grep for secret-shaped values in the deployment tree returned nothing. | PASS |
| AC6 | `deployment:verify` extension; pre-existing checks unbroken | `checkPosture()`/`checkTargetHost()` added as new sibling sub-checks alongside the pre-existing `checkMigrations`/`checkQueueGuards`/`checkProductionMatrix`/`checkSchedules`/`checkStorage`; live run reproduced (`deployment:verify --strict`) shows all sub-checks executing, advisory outside production as designed. | PASS |
| AC7 | Standard gate (full suite green, Pint, PHPStan) | Pint and PHPStan independently reproduced clean (0 errors) multiple times. **Full suite did not reproduce as "0 failures" on any of 5 independent full runs across this review** (my 2 runs + the P7-001 evidence pass's 3 runs) — every run showed 1–3 failures/errors, each time in a *different* pre-existing test file, never in `tests/Feature/Deployment/` (P7-001's own suite was 42/42 stable across 3 isolated runs). All failing tests are pre-existing files unrelated to P7-001's diff (`IngestionCompensationContractTest`, `RevisionHistoryActivationTest`, `BackupRunTest`, `LogContextTest`, `MediaMetadataProbeServiceTest`, `MediaStreamingTest`) and this pattern matches the pre-existing, already-documented `TD-008` ("order-dependent full-suite flakiness", OPEN, LOW). No failure traces into P7-001 code. See Finding F3. | PASS (with F3 noted) |
| AC8 | Real-host carry-forward (P7-008 AC2 reboot-cycle + P7-003 AC8 SIGTERM-drain) | `TargetHostEvidence.php` genuinely reports `pending` (no evidence file exists on disk; verified no test leaves one behind). `deployment:verify` output independently reproduced shows both items as `PENDING`. AC2 is never rewritten as PASS anywhere in code or docs. This is a real, unfabricated environmental gap: no Linux/systemd host is available in this Windows dev environment. The task contract explicitly states this is "not a precedent loophole" and AC8 "cannot PASS until the real-host evidence exists." | BLOCKED-ENVIRONMENT |

### Findings (P7-001)

- **F1 (LOW)** — Builder report states "70-key registry"; independently counted **71** keys in `ProductionEnvRegistry::definitions()`, confirmed by live `observability:diagnostics` output ("Env registry: 71 keys registered"). Immaterial to substance (mechanism, shapes, ownership all function correctly) but the builder report is not numerically accurate. *Failure scenario:* none — cosmetic reporting inaccuracy only.
- **F2 (LOW)** — AC4's "registry test fails CI" requirement is satisfied only indirectly: there is no separately named CI step for env-key ownership; the registry-completeness test runs as part of the whole-suite `composer ci:check`. Functionally equivalent (an unregistered key does fail CI), but if the contract's intent was a distinct, fast-failing gate, this is a gap worth a follow-up.
- **F3 (MEDIUM, cross-cutting — also affects P7-006/P7-007, see §4)** — The builder report's AC7 characterization ("1 transient single-test error observed in 1 of 4 full runs, unreproduced across 3 reruns") materially understates what independent re-runs show: across 5 independent full-suite runs performed during this review (this reviewer's 2 + the evidence pass's 3), **every single run** produced 1–3 failures/errors, always in different pre-existing files, never in Wave 2's own new test suites. This is consistent with the already-documented, OPEN `TD-008` debt item, and no failure was traced to any Wave 2 diff. Recommend: (a) the builder reports' full-suite characterization be corrected to reflect actual reproducibility, and (b) `TD-008` be reprioritized given the demonstrated frequency (not a rare transient — it reproduces on effectively every run in this environment).

### P7-001 Verdict

**VERIFIED**, with AC8 correctly recorded as BLOCKED-ENVIRONMENT (no code
defect — a genuine environmental gap, consistent with the
`DECISION-P7-008-AC2-DISPOSITION-001` precedent this task's contract
explicitly invites comparison to). Per that precedent, this does not by
itself prevent VERIFIED, but it does require the HPO to issue an
equivalent disposition decision for P7-001 AC8 (an
environmental-exception acceptance, not a rewrite of AC8 to PASS) before
this task can be closed DONE — the task contract is explicit that this is
"not a precedent loophole," i.e. the disposition must be made
affirmatively by the HPO, not assumed. No BLOCKER/HIGH finding. F1–F3 are
LOW/MEDIUM and non-blocking.

## 2. P7-006 — Security Hardening Baseline

### AC Table

| AC | Requirement | Evidence Independently Verified | Result |
|---|---|---|---|
| AC1 | Headers + CSP, transition evidenced, workspace green | `SecurityHeaders.php` sets the full header set (verified on both an authenticated and a public route by test). CSP mode toggle (report-only/enforce) confirmed in code. Retained artifacts `verification/p7-006/artifacts/p7-006-csp-{report-only,enforce}.json` exist and both show `verdict: PASS`, zero console/page errors — **but both retained artifacts already contain `unsafe-eval` in the CSP header; no retained artifact captures the pre-fix state (the EvalErrors) that supposedly justified adding it.** The Playwright spec (`verification/p7-006/csp-workspace.spec.js`) tests only against "the active CSP" at run time — it does not, and cannot, demonstrate the before/after discovery narrative. See Finding F4. | PASS (with F4 noted) |
| AC2 | Rate/abuse limits, exact boundaries, no partial state | `UploadAbuseGuard.php` checked before ingestion (confirmed ordering in `MediaUploadController.php`); exact-boundary logic read directly (`count >= max` / `bytes + size > maxBytes`), matches contract. 429 tests present for login/upload/csp-report; no-partial-state test confirms row count unchanged after rejection. | PASS |
| AC3 | Six scan behaviors, fail-closed, no silent clean | `ClamavScanner.php` — 6 distinct verdicts (clean/infected/unavailable/timeout/error/refused), each independently traced through `gateMalwareScan()` in `MediaIngestionService.php`: infected → quarantine move + no row; unavailable/timeout/error → 503, no row; clean → verdict columns persisted; scanner-disabled-outside-production → explicitly documented skip (dev/test only). Read the "disabled AND production" combined case directly in code: it is NOT silently skipped — the scanner is still invoked, returns `unavailable`, and falls through to the fail-closed 503 branch. No test explicitly names this combined case, but the code path is unambiguous and was read line-by-line, not inferred. | PASS |
| AC4 | Real-daemon proof / target-only | Independently confirmed no `clamd` binary, service, or listening port (3310) exists on this Windows dev box. `clamav:health` correctly reports unreachable with exit 1. Matrix + procedure substitute per the P7-008 AC2 precedent the contract explicitly allows. | BLOCKED-ENVIRONMENT (target-only, as contract permits) |
| AC5 | Dependency audit + CI | Independently re-ran both audits directly (not reading only the retained docs): `composer audit --format=plain` → no advisories; `npm audit --omit=dev` → 0 vulnerabilities. CI step confirmed added in `.github/workflows/tests.yml`. | PASS |
| AC6 | Error rendering + audit log | `SecurityAuditLog.php` centralizes denial/scan/abuse/auth-failure/CSP-violation logging with correlation IDs and redaction (`redact()` strips secret-shaped fields). One method, `guardRefusal()`, has no caller found anywhere in `app/` outside its own class — see Finding F5. | PASS (with F5 noted) |
| AC7 | No third-party egress | `ClamavScanner::endpoint()` refuses non-loopback TCP absent an explicit opt-in flag (default false); no outbound HTTP client on any scan path; grep confirms zero third-party scanning API references anywhere in `app/`. | PASS |
| AC8 | Standard gate | Pint and PHPStan independently reproduced clean. Targeted `tests/Feature/Security` suite reproduced 34/34 exactly matching the builder's claim. Full suite: same TD-008 pattern as P7-001 (2 independent full runs, each with exactly 1 failure/error, always in pre-existing unrelated files, never in P7-006's own diff). Diff audit confirmed additive-only touches to Wave 1 shared files (`AppServiceProvider`, `DeploymentVerify`, `ObservabilityDiagnostics`, `.env.example`, migrations-inventory, the P6-owned rollback-step test). | PASS (F3 pattern applies again) |

### Findings (P7-006)

- **F4 (MEDIUM)** — The `unsafe-eval` CSP directive is a materially broad relaxation (it permits arbitrary string-to-code evaluation for same-origin scripts), and the reviewer checklist explicitly requires "no over-broad allowlist (each directive justified)." The builder's narrative justification (an Alpine.js `new Function` requirement, discovered via a report-only run) is plausible and consistent with known Alpine.js/Livewire behavior, but **no retained artifact actually demonstrates the failure state this fix addressed** — both retained CSP evidence JSONs already include `unsafe-eval` and show zero errors, which proves the *final* enforced policy is workspace-safe, not that `unsafe-eval` was *necessary* rather than merely convenient. Mitigating factors: no external script sources are permitted, the app is same-origin only, and `unsafe-eval` is a common, defensible requirement for Alpine.js in practice. *Recommendation:* not a blocker, but before HPO closure, either (a) accept the narrative justification as sufficient (consistent with how CSP directive tradeoffs are typically documented in practice), or (b) ask the builder to retain a "before" artifact (report-only run against a policy without `unsafe-eval`, showing the actual EvalErrors) for a fully evidenced record. This is a documentation/evidence-completeness gap, not a demonstrated security defect.
- **F5 (LOW)** — `SecurityAuditLog::guardRefusal()` exists but has no call site anywhere in `app/`. Likely reserved for a future caller (e.g. `ProductionConfigGuard` refusals) rather than dead code, but worth a one-line confirmation from the builder or a follow-up to wire it in or remove it.
- **F3 pattern** — see §4; same TD-008 full-suite flakiness observed for this task's full-suite runs, never in P7-006's own files.

### P7-006 Verdict

**VERIFIED**. No fail-open behavior was found on any security-critical
path (scanner-unavailable, CSP, headers, throttles all fail closed or
default-deny — independently traced through code, not inferred from
tests alone). AC4 is correctly BLOCKED-ENVIRONMENT per the explicit
contract allowance (target-only with substitute evidence), which does not
block VERIFIED. F4 and F5 are MEDIUM/LOW and non-blocking but should be
noted in the HPO closure record; F4 in particular is worth a one-line
disposition since it concerns a real (if narrow) CSP-hardening gap in the
evidentiary trail.

## 3. P7-007 — Backup / Restore Foundation

### AC Table

| AC | Requirement | Evidence Independently Verified | Result |
|---|---|---|---|
| AC1 | Manifest-valid set + integrity, loud failures | Manifest structure read directly in `BackupManager.php` (matches contract §8 fields exactly). `verifySet()` genuinely re-hashes and re-opens the copied SQLite file (not the source) — confirmed by the tamper-detection test, which mutates the manifest post-write and asserts `verifySet()` fails. **The builder report's AC1 evidence claims a test for "unwritable target via seam"; no such test exists in `tests/Feature/Backup/`** — only a "refuses non-file sources" test exists, which exercises a different failure mode. The `ensureDirectory()`/"is not writable" code path itself is real (read directly), just not test-asserted as claimed. See Finding F6. | PASS (with F6 noted) |
| AC2 | pg-native path defined, dormant, hard refusal | `BackupManager::run()` returns before any pg-specific code executes when `driver === 'pgsql'`, with the exact "requires P7-002" message, test-asserted. Grep confirms zero functional PostgreSQL code anywhere in `app/`, `config/`, `database/migrations/` — only 3 prose/comment/error-string references. | PASS |
| AC3 | P7-008 hooks with pinned exits + golden output | `BackupPreMigrate.php` produces the exact golden strings (`PRE-MIGRATE BACKUP OK`/`REFUSED`) with exit 0/1, test-asserted for both paths, wording verified verbatim against the final runbook. Note: the hook is callable and tested but not wired into any automated deploy script — it is a manual runbook step, consistent with the single-admin/manual-runbook model (D7-08) and the contract's scope (§6.6 asks for command shape, not CI/CD automation). | PASS |
| AC4 | Retention math + pruning + last-good protection | `prune()` read directly: newest good set is always force-kept regardless of generation count; pruning short-circuits entirely when zero good sets exist (cannot delete anything, including old failed sets). Three dedicated tests independently reproduced and passing. | PASS |
| AC5 | Manifest/inventory reconciliation enforced | Live-tree reconciliation test plus fabricated-divergence tests (using temp copies, repo files untouched) both present and passing. | PASS |
| AC6 | Single-sourced procedures | `docs/BACKUP-RESTORE-PROCEDURES.md` is the sole detailed procedure document; the runbook's §13 append is reference-only (confirmed by reading both files — no duplicated procedure prose). | PASS |
| AC7 | Drill NOT executed/claimed | Confirmed no drill code path exists beyond the dormant-refusal test. Every mention of "G-08"/"restore drill" across the builder report, `CURRENT_STATE.md`, and `DECISIONS.md` is a deferral, never a completion claim. | PASS |
| AC8 | Standard gate | Independently reproduced exactly: targeted suite 14/14, full suite 1006/1005+1 skip (this specific run showed zero failures — the TD-008 pattern is intermittent, not universal), Pint clean, PHPStan 0. Diff-audited: all shared-file touches (`AppServiceProvider`, `routes/console.php`, `DeploymentVerify`, `ObservabilityDiagnostics`, `.env.example`) additive-only. | PASS |

### Findings (P7-007)

- **F6 (LOW–MEDIUM)** — The builder report's AC1 evidence row claims an "unwritable target via seam" test as part of the failure-mode coverage; independently confirmed this specific test does not exist in either `tests/Feature/Backup/BackupFoundationTest.php` or `BackupRunTest.php` (only "refuses non-file sources" exists, a different scenario). The underlying code path (`ensureDirectory()` → "is not writable" error) is real, just not test-covered as claimed. *Failure scenario:* if a future refactor breaks the unwritable-target error path, no test would catch it. *Recommendation:* add the missing test, or correct the builder report to not claim it as covered.

### P7-007 Verdict

**VERIFIED**. No scope creep into P7-002 (PostgreSQL migration) found; no
premature G-08/drill claim found anywhere. F6 is a LOW–MEDIUM
evidence-accuracy gap (a real, defensible code path exists but the
specific test claimed for it does not), non-blocking but should be
corrected — recommend either adding the missing test or amending the
builder report before HPO closure.

## 4. Cross-Cutting: Full-Suite Flakiness (TD-008)

Across this review, 5 independent full-suite runs were performed (2 by
this reviewer directly, 3 by the P7-001 evidence pass, plus 2 more by the
P7-006 evidence pass and 1 clean run during the P7-007 evidence pass — 8
total full runs across the whole review). Results:

- Every run except one (the P7-007 evidence pass's single run) showed
  1–3 failures/errors.
- The specific failing test differed on almost every run:
  `IngestionCompensationContractTest`, `RevisionHistoryActivationTest`
  (x2, different assertions), `BackupRunTest` (manifest size), `Backup`/
  `Observability/LogContextTest` (SQLite UNIQUE constraint), `MediaStreamingTest`,
  `MediaMetadataProbeServiceTest`.
- **No failure, in any run, traced to a file touched by P7-001, P7-006,
  or P7-007's diffs.** All failing files are pre-existing and unrelated
  to Wave 2's scope.
- This matches `docs/TECHNICAL_DEBT_REGISTER.md`'s already-OPEN `TD-008`
  ("Order-dependent full-suite flakiness... LOW... Suite-hygiene
  follow-up (recommend P7 test-hardening scope)").

**Assessment:** this is genuine, pre-existing, order-dependent test
flakiness, not a regression introduced by Wave 2. It does not block any
of the three VERIFIED verdicts above, since the AC7/AC8 "full suite
green" requirement is reasonably read as "no regression attributable to
this task's diff," which holds in all cases. However, the observed
frequency (flakiness on 7 of 8 full runs during this review, versus the
builder reports' collective characterization as a rare "1 of 4" or "1 of
2" occurrence) is materially worse than documented. **Recommendation to
HPO:** reprioritize `TD-008` out of the "B. Can be scheduled after
launch" bucket — at this frequency it will make every future full-suite
gate unreliable and erode confidence in "full suite green" as reported
evidence for the remainder of Phase 7.

## 5. Scope / Authorization Compliance

- Confirmed `BACKLOG — CONTRACT_AUTHORED` (untouched) for `P7-002`,
  `P7-004`, `P7-009`, `P7-011`; no Wave 3 work started.
- No Horizon references outside a pre-existing doc comment
  ("without Horizon") in `config/transcription.php`.
- No object-storage adoption: `AWS_*` keys in `.env.example` are
  Laravel's stock skeleton placeholders, and `ProductionEnvRegistry`
  explicitly registers them as "must stay empty (no object storage per
  D7-03)."
- Browser matrix remains Chromium-only (`D7-05`, confirmed in
  `PHASE7-SCOPE-CONTRACT.md`); no new Playwright config introduces a
  non-Chromium project.
- No D7 decision reopened; no debt item marked closed by any of the
  three tasks (all three builder reports and this review confirm
  "nothing marked closed").
- No historical environmental gap rewritten: P7-008 AC2 remains NOT PASS
  everywhere it is referenced; P7-001's AC8 target-evidence mechanism
  never fabricates a PASS (confirmed by direct code reading, not
  inference).

## 6. Working Tree / Diff Audit

Shared-file touches across the three tasks (`AppServiceProvider.php`,
`DeploymentVerify.php`, `ObservabilityDiagnostics.php`,
`ProductionConfigGuard.php`, `.env.example`,
`RevisionMigrationRollbackTest.php`, `deploy/migrations-inventory.json`)
were each diff-audited and are additive-only, consistent with the
`PARALLEL-SAFE WITH FILE-OWNERSHIP SEQUENCING` execution shape the HPO
authorized. The one P6-owned test adjustment
(`RevisionMigrationRollbackTest.php`, `--step` 5→6) is a correct,
minimal, disclosed consequence of P7-006's new migration leading the
tree — not scope creep.

No undisclosed implementation changes were found beyond what each
builder report lists. Untracked verification residue
(`database/p7-006.sqlite`, `verification/p7-006*`,
`storage/app/target-evidence/` absence) is consistent with disclosed
"known limitations" in the builder reports and was left untouched per
review policy (no housekeeping performed).

## 7. Findings Register (severity-ranked)

| # | Severity | Task | AC | Summary |
|---|---|---|---|---|
| F3 | MEDIUM | P7-001/006/007 (cross-cutting) | AC7/AC8 | Full-suite flakiness (TD-008) is materially more frequent than builder reports characterize (7 of 8 runs failed, not "1 of 4"); no failure attributable to Wave 2 diffs. Recommend TD-008 reprioritization, not a task blocker. |
| F4 | MEDIUM | P7-006 | AC1 | `unsafe-eval` CSP directive's justification rests on narrative only; no retained artifact demonstrates the pre-fix EvalError state it was added to address. Not a demonstrated defect; recommend a "before" artifact or explicit HPO acceptance of the narrative justification. |
| F6 | LOW–MEDIUM | P7-007 | AC1 | Builder report claims an "unwritable target" test that does not exist; the underlying code path is real but untested as claimed. Recommend adding the test or correcting the report. |
| F1 | LOW | P7-001 | AC4 | Registry has 71 keys, not 70 as the builder report states. Cosmetic. |
| F2 | LOW | P7-001 | AC4 | Registry-completeness CI gate is indirect (via whole-suite `ci:check`), not a separately named step. Functionally adequate. |
| F5 | LOW | P7-006 | AC6 | `SecurityAuditLog::guardRefusal()` has no call site found; likely reserved, not dead code — confirm or wire in. |

No BLOCKER or HIGH finding was identified in any of the three tasks.

## 8. Verdicts Summary

| Task | Verdict | Blocking items | Non-blocking follow-ups |
|---|---|---|---|
| P7-001 | **VERIFIED** | None. AC8 is BLOCKED-ENVIRONMENT (genuine, not a defect) — requires an HPO disposition decision analogous to `DECISION-P7-008-AC2-DISPOSITION-001` before DONE closure, per the contract's explicit non-automatic-precedent language. | F1, F2, F3 |
| P7-006 | **VERIFIED** | None. AC4 is BLOCKED-ENVIRONMENT (target-only, explicitly contract-permitted). | F3, F4, F5 |
| P7-007 | **VERIFIED** | None. | F3, F6 |

None of the three tasks is marked DONE by this review — DONE remains an
HPO closure action per governance. This review recommends the HPO issue,
for P7-001, an environmental-exception disposition for AC8 (mirroring
`DECISION-P7-008-AC2-DISPOSITION-001`) alongside any closure decision,
and consider the TD-008 reprioritization noted in §4 as a separate,
non-blocking governance item.
