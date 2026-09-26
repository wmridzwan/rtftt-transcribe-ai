# Phase 7 Wave 1 — Independent Review (P7-003, P7-008, P7-010)

Reviewer: Claude Code (independent reviewer role, per `.ai/guidelines/orchestration-policy.md`).
Reviewed against: `tasks/P7-003-queue-worker-supervision-recovery.md`,
`tasks/P7-008-deployment-migration-safety-rollback.md`,
`tasks/P7-010-browser-support-matrix-flake-elimination.md` (adopted contracts,
2026-09-26), and the corresponding builder reports
(`reviews/P7-003-BUILDER-REPORT.md`, `reviews/P7-008-BUILDER-REPORT.md`,
`reviews/P7-010-BUILDER-REPORT.md`).

This review does not authorize any later Phase 7 wave, does not close any
task DONE, and does not modify implementation code. HPO closure remains a
separate required step for every VERIFIED item below.

## A. Evidence Methodology

Distinguishing reproduced-by-reviewer vs. implementer-reported evidence, per
governance:

- **Reproduced independently in this review** (commands run fresh, this
  session): full Pest suite, Pint, PHPStan, targeted `tests/Feature/Queue`
  (incl. real-Redis, confirmed reachable and genuinely exercised, not
  skipped), `tests/Feature/Editing`, export-filtered suite, migration-hash
  spot check, playwright config audit (14/14 chromium-only/workers 1/
  retries 0), source-level trace of the TD-013 blade fix against Flux's
  actual JS bundle and the pre-existing `delete-user-form.blade.php`
  precedent, and inspection of retained (gitignored, locally present)
  Playwright JSON result artifacts for P7-010 and P4-006.
- **Implementer-reported, not independently reproduced** (accepted as
  reasonable given environment constraints, not verified first-hand by this
  reviewer): the P7-003 live SIGKILL/SIGTERM demo narrative, the P7-008
  release-pointer junction v1→v2→v1 cycle, and `deployment:verify`/`migrate
  --pretend` manual walkthrough narrative. These are manual/operational
  demonstrations that leave no committed artifact by design; their
  plausibility was corroborated by reading the guard/command code they
  exercise, but the demo runs themselves are implementer-reported.
- **Not executable in this environment**: P7-008 AC2 (reboot-cycle
  systemd verification) — no Linux/systemd host is available here or, per
  the builder report, on the implementer's own machine. Correctly
  self-flagged by the builder as BLOCKED (environmental), not claimed PASS.

## B. P7-003 — Queue / Worker Supervision + Recovery

### Verdict: **VERIFIED**

No BLOCKER or HIGH finding. All 10 acceptance criteria are satisfied by
independently reproducible evidence or code/config that provably enforces
the claimed invariant.

Key independent confirmations:

- `app/Queue/QueueDriverPolicy.php` and `app/Transcription/TranscriptionQueueConfig.php`
  read as designed: `sync`/`null` prohibited outside tests, provider (300s)
  < job (330s) < retry_after (420s) invariant enforced with margin, and the
  guard is skipped only under `runningUnitTests()` — not a blanket
  environment bypass. `config/queue.php` confirms both `database` and
  `redis` connections ship `retry_after` = 420 by default, satisfying the
  invariant out of the box (`config/queue.php:43,71`).
- `TranslationQueueConfig`'s historical `sync`/`null` exemption is
  genuinely closed (diff confirmed), and the corresponding test was
  correctly flipped from an "exempts" assertion to a "prohibits" assertion
  rather than deleted.
- `app/Console/Commands/RecoverStaleTranscriptionAttempts.php` genuinely
  exists (not just referenced) and is scheduled every minute with
  `withoutOverlapping()` in `routes/console.php`, mirroring the translation
  precedent exactly.
- `tests/Feature/Queue/RedisQueueIntegrationTest.php` ran against a real,
  reachable Redis in this review session (3/3 passed, not skipped) —
  independently confirms the "real Redis, not just a fake" claim. The
  duplicate-delivery test is honestly scoped: it proves the Redis-driver
  delivery path doesn't break the already-established (pre-existing,
  Phase 3-proven) claim-fencing/idempotency mechanism for unknown
  identifiers; it does not re-litigate fencing correctness itself, which
  is appropriately out of this task's scope.
- Full suite (927/926/1 skip/0 fail), Pint, PHPStan(0) all reproduced
  fresh in this session — exact match to the builder's numbers.

Non-blocking observations (LOW, informational, do not block VERIFIED):

- **LOW-1**: An untracked stray file `x` (`completed-at-<epoch> pid-<n>`,
  root of the repo) remains in the working tree, contradicting the Wave 1
  implementation report's own claim ("Stray shell artifact removed").
  Harmless (not part of any diff, gitignored-equivalent by being
  untracked), but should be deleted before the batch is committed.
- **INFO-1**: AC8 (SIGTERM drain) and part of AC9 (rollback dry-run) rest
  on a documented mechanism plus a narrated manual demo rather than an
  automated regression test, because this dev machine cannot send real
  Unix process signals to a supervised worker. This is a genuine, correctly
  self-disclosed environmental gap, consistent with the AC6 automated test
  which *does* cover the equivalent kill-mid-job path end-to-end. Not
  blocking, but P7-001/target-host certification should re-confirm AC8
  against a real systemd unit (this is explicitly P7-003's own stated
  dependency split with P7-001/P7-008, not a defect).

## C. P7-008 — Deployment, Migration Safety, Rollback

### Verdict: **VERIFIED, with one outstanding environmental gap requiring an explicit HPO disposition before DONE closure (AC2)**

No finding here is a code defect requiring a corrective implementation
cycle. AC2 is not satisfiable in this development environment (no
systemd/Linux host), a fact the builder correctly self-reported as BLOCKED
rather than claiming PASS. Per the orchestration policy's escalation model,
an environmental (not implementation) gap of this kind is an HPO decision
point ("accept with known limitations" / defer to P7-001 environment
certification), not grounds for `CHANGES_REQUESTED` — there is no fix
OpenCode can make on this machine. I flag it as BLOCKER-severity *for
DONE closure specifically*, not for this review's VERIFIED verdict on the
other 8 acceptance criteria, which are independently satisfied.

Independent confirmations:

- `app/Deployment/ProductionConfigGuard.php`: pure-logic guard, correctly
  no-ops outside `app()->isProduction()`, and its 6 tests
  (`ProductionConfigGuardTest.php`) genuinely exercise debug/queue/app-key/
  worker-url/worker-token violation paths plus the non-production no-op
  path. Confirmed wired into `AppServiceProvider::boot()` alongside the
  P7-003 guards (diff confirmed, no duplication of the queue guards'
  ownership — composes them via `TranscriptionQueueConfig`/
  `TranslationQueueConfig::consistencyViolation()` inside
  `DeploymentVerify::checkQueueGuards()`).
- `deploy/migrations-inventory.json`: 29 pinned sha256 hashes. Spot-checked
  one hash (`0001_01_01_000000_create_users_table.php`) against the actual
  file on disk — matches exactly. Entry count (29) matches the actual
  migration file count on disk exactly. `MigrationInventoryTest.php`
  genuinely re-hashes every pinned file and diffs the file list against the
  manifest (not a stub test) — both tests pass.
- `deploy/systemd/*.service`/`*.timer`: reviewed for internal consistency
  against the P7-003 spec — `--timeout=330` matches
  `TranscriptionQueueConfig::jobTimeoutSeconds()`, `TimeoutStopSec=420`
  exceeds the job timeout with margin, `Restart=always` present,
  `WantedBy=multi-user.target`/`timers.target` present for boot-enable.
  This is a correct paper design; it has not been run against a real
  systemd instance (AC2 gap, above).
- `DeploymentVerify` command: `--strict` semantics correctly differentiate
  advisory (non-production, always exit 0) from enforcing (production,
  `--strict` fails loudly); tests exercise both the queue-violation and
  missing-schedule paths genuinely, not just the happy path.
- No P7-002 (migration content), P7-003 (queue internals), or P7-001
  (certification) content was found duplicated or absorbed on inspection of
  the diff; the task correctly only *installs* the P7-003 spec and *runs*
  migrations, never redefining either.
- Full suite, Pint, PHPStan reproduced identically to the builder's claim
  (shared full-suite run covers both P7-003 and P7-008 changes together,
  since they are non-overlapping files).

Non-blocking observations:

- **LOW-2**: The PostgreSQL-era `pg_dump` backup hook is documented but,
  by the builder's own admission, only the SQLite-era copy path is
  exercisable pre-P7-002. This is correctly scoped as a forward reference,
  not a gap in this task's own boundary.

## D. P7-010 — Browser Support Matrix + Flake Elimination

### Verdict: **VERIFIED**

No BLOCKER or HIGH finding.

Independent confirmations:

- **Playwright config audit**: independently found all 14
  `verification/playwright.*.config.js` files (13 pre-existing + the new
  `p7-010` one), and confirmed all 14 are Chromium-only, `workers: 1`,
  `retries: 0` — exactly matching the builder's "14 configs, all
  compliant, no change needed" claim.
- **TD-013 fix**: traced the actual mechanism. The new
  `<flux:modal.trigger name="rename-transcription">` wrapper's own
  `x-on:click` handler (from Flux's own
  `vendor/livewire/flux/stubs/.../modal/trigger.blade.php`) is what
  genuinely dispatches the `modal-show` event Flux's JS bundle listens for
  (confirmed both the JS bundle's exact listener string and the PHP-side
  `InteractsWithComponents::dispatch('modal-show', ...)` source) — this
  matches the already-working `delete-user-form.blade.php` precedent
  exactly. The `?rename=1` deep-link path dispatches the identical
  `modal-show` event via `x-init`/`$nextTick`, which is the correct event
  name (not a mismatched/dead event). This is a genuine fix, not a
  reshuffle that merely moves the defect. Independently corroborated by
  the retained `p7-010-console-results.json` (real, non-round timestamps
  6 seconds apart, all four console-clean assertions true, zero console/
  page errors) — this is consistent with a genuine Playwright run, not a
  fabricated result file.
- **TD-005 (V4-08/V4-09) fix**: the diff replaces a blind `waitForTimeout
  (1500)` after `el.play()` with a helper that (1) waits for
  `readyState >= 2`, (2) awaits the `play()` promise itself (surfacing a
  rejection instead of timing out opaquely), and (3) polls
  `currentTime > 0` explicitly. This strengthens rather than weakens gate
  sensitivity, satisfying the task's own risk concern (§16) verbatim.
  Independently corroborated by the retained
  `p4-006-browser-results.json`: `currentTimeAfterPlay: 0.021225` (audio) /
  `0.011143` (video) — non-round, plausible real playback values, not
  fabricated round numbers, and the pre-P7-010 backup file
  (`*.pre-p7010.json`) is genuinely present and distinct, honoring the
  "preserve historical evidence" contract requirement.
- **V4-13 fix**: confirmed as a real, narrowly-scoped locator fix
  (`page.getByText(...).first()` → `page.locator('[data-segment-row]',
  {hasText}).first()`), consistent with the documented cause (a hidden
  P6-007 comparison-table cell matching first). No application behavior
  was changed to accommodate it.
- Full PHP suite, Pint, PHPStan reproduced identically; Phase 6
  editing/export suites re-run directly by this reviewer (127/127 and
  43/43 respectively) with zero regressions, independently corroborating
  AC9/no-P6-regression beyond the builder's own full-suite number.
- No Firefox/WebKit file was touched; historical evidence files are
  present and distinct from the new ones (backup naming confirmed).

No non-blocking findings beyond what the builder report itself already
disclosed (V4-13 exclusion-table supersession, gitignored artifacts).

## E. Cross-Task Boundary Check

- No shared-file conflict between P7-003/P7-008/P7-010 diffs (confirmed:
  disjoint file sets except `AppServiceProvider.php`, which both P7-003 and
  P7-008 edit additively — both guard registrations are present together
  and neither overwrites the other, confirmed by direct diff read).
- No Horizon, no object storage, no multi-node/clustering content found in
  any of the three diffs.
- Full regression suite (927/926/1 skip/0 fail), Pint (clean), PHPStan
  (0 errors) hold across the combined Wave 1 diff, independently
  reproduced once for the whole batch (not just per-task) in this review
  session.

## F. Overall Wave 1 Verdict

| Task | Verdict | Blocking for DONE? |
|---|---|---|
| P7-003 | VERIFIED | No |
| P7-008 | VERIFIED | AC2 environmental gap — HPO must record an explicit disposition (accept-with-limitation, pending P7-001 certification, or defer) before DONE closure |
| P7-010 | VERIFIED | No |

**This review authorizes nothing beyond itself.** HPO must still: (1)
decide AC2's disposition for P7-008, (2) close each VERIFIED task as DONE,
and (3) separately authorize any Phase 7 wave beyond this one. No later
Phase 7 work is authorized by this review.

## G. Recommended Non-Blocking Follow-Ups

- Delete the stray untracked `x` file at the repo root before this batch is
  committed (LOW-1, §B).
- When P7-001 environment certification runs on the real Linux target,
  explicitly re-verify P7-008 AC2 (reboot cycle) and P7-003 AC8 (real
  SIGTERM drain) against that host, since both currently rest on
  documented-mechanism + narrated-demo evidence rather than a reproduced
  automated test.
