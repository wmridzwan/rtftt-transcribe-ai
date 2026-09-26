# P7-011 — Independent Review (Retention, Derived-Artifact + Orphan Cleanup)

Reviewer: Claude Code (independent of the P7-011 Builder, OpenCode). This
review does not implement, does not touch the P7-011 Builder report, does
not close Phase 7, and does not claim G-09 (that belongs to P7-012).

## 1. Baseline

- HEAD: `4d90d5b914c0928574cff7e0f1d1d4a463bb4350`, branch `main`.
- Working tree at review start: heavily dirty (multi-wave residue from prior
  Phase 7 work, exactly as the Builder report documents at §"Baseline").
  Nothing was reset, cleaned, or stashed by this review.
- Confirmed present before review: `tasks/P7-011-retention-cleanup.md`
  (Status = REVIEW), `reviews/P7-011-BUILDER-REPORT.md`.

## 2. Approved Scope (independently reconstructed)

From `tasks/P7-011-retention-cleanup.md`: implement the D7-06 30-day
auto-purge (§6.10 eligibility table) as a scheduled, idempotent reconciler
that deletes physical bytes only, tombstones the owning row (never
hard-deletes domain/history rows, §8.4), respects P7-004 storage truth and
P7-007 backup-generation ownership, preserves ADR-005 user-deletion and P6
revision-graph invariants, and closes TD-007's implementation half (owner
policy already resolved by D7-06). Confirmed bounded to exactly this in the
diff — no P7-009/P7-012/object-storage/unrelated-TD content found.

## 3. Independent Test / Gate Reproduction (not copied from the Builder report)

Run fresh, from this review session, not read from the Builder's logs:

- `php artisan test --filter=Retention --compact`: **23 passed**, 73
  assertions, 4 warnings — matches Builder's claim.
- `php artisan test --compact` (full suite): **1073 tests, 1072 passed, 1
  skipped, 4045 assertions, 4 warnings, 0 failures** — matches the Builder
  report exactly, reproduced independently.
- `vendor/bin/pint --test --format agent`: **passed**.
- `vendor/bin/phpstan analyse --memory-limit=1G`: **0 errors**.

All four gate claims in the Builder report are independently confirmed
accurate.

## 4. Implementation Review

`app/Retention/RetentionPurge.php` (the reconciler):

- Eligibility query (`Transcription::where('status', Completed)
  ->whereNotNull('completed_at')->where('completed_at', '<=', $cutoff)`)
  matches §6.10 exactly: only completed lifecycles with a non-null
  `completed_at` age into eligibility; `updated_at` is never consulted.
  Independently verified against `RetentionEligibilityTest` (31d purged, 29d
  skipped, boundary inclusive at exactly 30d+1min, null-`completed_at`
  excluded, non-completed statuses excluded) — all pass.
- Media purge path: presence is captured **before** acting (line 119),
  delete attempted, then independently re-verified gone
  (`verifyStillPresent()`, `clearstatcache()` forced) before any row is
  touched. On verification failure the method returns `FAILED` **without**
  entering the `DB::transaction` — no tombstone is written on a failed
  delete. This is correct atomicity and is proven by
  `RetentionIdempotencyTest::'records filesystem failure without
  tombstoning and resumes on the next run'`, independently re-run.
- Tombstoning happens under `lockForUpdate()` inside the transaction, with a
  fresh re-fetch that treats a vanished row as `StalePurgeCandidate` (ADR-005
  concurrent-deletion back-off) rather than resurrecting or re-marking it —
  matches AC6/§10.
- Quarantine/backup/history exclusion (`isProtectedPath()`): rejects any
  path containing `quarantine/` and anything outside the configured media or
  staging roots. Confirmed by `RetentionAdversarialTest::'protects
  quarantine paths even if a row ever pointed at one'`.
- Audit ledger: every branch in `purgeTranscriptionMedia()` and
  `purgeStaging()` produces exactly one audit row per decision, and the
  outer loops increment the same `$outcomes` array the audit rows are
  written under — `RetentionAuditDisclosureTest::'accounts every decision
  outcome in the audit ledger'` asserts `array_sum($outcomes) ===` the
  ledger row count for the run, which holds by direct code reading as well
  as by test.
- Idempotency: a second run against an already-tombstoned row is routed to
  `OUTCOME_SKIPPED` before any storage or DB write is attempted (line
  98-101); confirmed by `RetentionIdempotencyTest::'treats a second run as
  idempotent re-runs, not new purges'`.

No BLOCKER or HIGH finding in the core media-purge path. It matches §6.10,
§8.4, and §10 of the contract.

## 5. Finding 1 (MEDIUM) — §8.4's "explicit source-purged state" is not implemented, and the disclosure copy overclaims it

§8.4 of the task contract requires: *"transcript/history surfaces render
from retained rows with an explicit 'source purged' state"*. The reviewer
checklist (§18) separately requires *"Disclosure copy present and
accurate."*

Independently verified by reading the full implementation surface:

- `grep -rn "purged_at" app/` shows `purged_at` is read in exactly two
  places outside the reconciler itself: `MediaActionController::download()`
  and `::stream()` (the 410 abort). No other controller — including
  `TranscriptionExportController.php` — and no Blade view reads `purged_at`
  or `mediaFile->purged_at` (`grep -rn "purged_at" resources/` returns no
  matches).
- `resources/views/transcriptions/show.blade.php:88-103` unconditionally
  renders an `<audio>`/`<video>` element pointing at the `media.stream`
  route with no purged-state branch. For a purged transcription this page
  renders a player whose source now 410s, with no "source purged" message
  anywhere on the page — not the explicit state §8.4 calls for.
- `docs/RETENTION-POLICY.md` (the disclosure copy, AC7) states: *"Your
  transcript history stays available: transcripts, revision history,
  translations, and text exports ... continue to work — they are marked as
  'source purged' so it is always clear the original recording is gone."*
  This is not accurate to the current implementation: nothing marks a
  transcript, revision, translation, or export as "source purged" anywhere
  in the app. Only the media download/stream endpoints report the state
  (as a 410), which is real and correctly tested, but is not what the
  disclosure text describes to the user.

Impact: this is not a data-loss, security, or P6-invariant issue — text
exports and history rows genuinely keep working post-purge, which is the
core AC3/AC7 guarantee, and that part is correctly implemented and tested
(`RetentionAuditDisclosureTest::'keeps transcript history and text exports
working after purge'`). But the contract's explicit UI requirement is
unmet, and the user-facing disclosure document makes a specific factual
claim about the product's behavior that the product does not do. Per §18's
own checklist item ("Disclosure copy present and accurate"), this does not
pass as written.

**Recommendation**: either (a) add a purged-state indicator to the
transcript show view (and, if intended, to export headers/metadata) so the
copy is true, or (b) correct `docs/RETENTION-POLICY.md` to describe only
what is actually implemented (410 on media access; history/exports
continue silently, with no visible "source purged" marker). Either fix is
small and scoped; this does not require reopening the eligibility, audit,
or safety-fencing work, which is sound.

## 6. Finding 2 (MEDIUM) — staging-claim row is deleted unconditionally, even when the file delete failed

`RetentionPurge::purgeStaging()` (lines ~198-221):

```php
self::deleteStagingObject($storage, $claim->staging_path, 'staging-claim', $claim->id, $dryRun, $runId, $outcomes, $failures);

$claim->delete();
```

`deleteStagingObject()` catches every `Throwable` internally and records a
`FAILED` audit outcome without re-throwing (lines 296-299 of
`RetentionPurge.php`). Control returns normally to `purgeStaging()`, which
then calls `$claim->delete()` **unconditionally** — including in the branch
where the file deletion was just recorded as `FAILED`.

This contradicts the task's explicit scope requirement (§6 item 2): *"Safe
deletion behavior (row + file atomicity discipline ... never a row without
its file disposition decided)"*. Here the claim row's disposition is
decided (deleted) independently of whether the file's disposition
succeeded or failed. Contrast this with the media-purge path (§4 above),
where the equivalent failure branch correctly withholds the row write —
the staging path does not carry the same discipline.

Practical severity is bounded, not data-destructive: the underlying file is
not deleted on this path (deletion failed, so bytes remain on disk), and
because the claim row is gone, the file becomes a plain orphan that the
subsequent orphan-file sweep (`isStagingOrphanEligible()`, 24h age gate)
will eventually pick up and retry once its mtime crosses 24h. No bytes are
lost and no false "success" is recorded (the `FAILED` audit row is
correctly written). But it is an untested deviation from an explicit,
named safety invariant in the contract, and it means a claim-tracked
candidate that fails to delete silently downgrades to being tracked as a
plain orphan instead of surfacing as a resumable failure the way the media
path does.

No test in `tests/Feature/Retention/` exercises a staging-claim file-delete
failure (the only FS-failure test, `RetentionIdempotencyTest::'records
filesystem failure without tombstoning and resumes on the next run'`,
covers the media path only). This gap in coverage is consistent with the
gap in the implementation — the failure mode was not exercised, so the
row-deleted-on-failure behavior was not caught.

**Recommendation**: only call `$claim->delete()` on a non-`FAILED` outcome
from `deleteStagingObject()` (mirror the media path's pattern), and add a
test analogous to the existing media FS-failure test for the staging-claim
branch.

## 7. Other Areas Reviewed — No Finding

- **Migrations** (`2026_09_26_140000_add_purged_at_to_media_files_table`,
  `2026_09_26_140001_create_retention_purge_audits_table`): both additive,
  both `down()`s clean (drop column / drop table). Confirmed against
  `tests/Feature/Editing/RevisionMigrationRollbackTest.php`'s updated
  `--step 9` assertion, which passes in the full suite.
- **Scheduler** (`routes/console.php`): `retention:purge` is `->daily()
  ->withoutOverlapping()`, consistent with the P7-008 convention already
  used for the adjacent `backup:run`/`clamav:health` entries.
- **`deployment:verify` / `observability:diagnostics`** additions: additive
  lines only; no existing P7-005 field renamed or reformatted (confirmed by
  reading the diff — every new line is inside a clearly P7-011-commented
  block). `checkRetention()` correctly treats a scheduled-but-failing ledger
  as a production-fail, non-production warn.
- **Quarantine/backup exclusion**: `RetentionAuditDisclosureTest::'leaves
  backup generations and their manifest untouched'` performs a real
  before/after byte-identical manifest comparison against an isolated
  backup run, not a mocked assertion — independently re-run, passes.
- **`MediaFile` shared across multiple `Transcription` rows**: `MediaFile`
  exposes a `transcriptions(): HasMany` relation and there is no unique
  constraint on `transcriptions.media_file_id`, which is a latent risk if a
  future feature ever created a second `Transcription` against the same
  `MediaFile` (an old-but-completed transcription could then cause the
  purge to delete bytes still needed by a different, active transcription
  on the same media row). Grepped the full `app/` tree for every
  `Transcription::create(...)` call site: all three (`Phase3Integration
  VerificationCommand`, `DemoTranscriptionController`) create exactly one
  `Transcription` per newly-created `MediaFile`; no code path in the actual
  upload/ingestion flow creates a second `Transcription` against an
  existing `MediaFile`. This is a real schema-level gap but not a reachable
  defect in the current application — recorded here as an observation, not
  a finding, since P7-011 did not introduce it and no in-scope behavior
  exercises it.
- **Zero-byte stream handling** in `MediaActionController::stream()`: this
  hunk is pre-existing P7-004/TD-011 work already committed to this working
  tree before P7-011 started (per its own comment header referencing
  TD-011), not part of the P7-011 diff; not reviewed further here as it is
  out of this task's scope.

## 8. Acceptance Criteria Matrix

| AC | Requirement | Result | Evidence |
|---|---|---|---|
| AC1 | Eligibility matrix per D7-06 | PASS | §4; `RetentionEligibilityTest` (5/5) |
| AC2 | Active/retryable work never purged | PASS | §4; `RetentionAdversarialTest::'never purges active work...'` |
| AC3 | P6 invariants hold post-purge | PASS | §4, §7; history/export test green, full Phase 6 suites green |
| AC4 | Idempotency + resume | PASS | §4; `RetentionIdempotencyTest` (5/5) |
| AC5 | Audit completeness | PASS (with Finding 2 caveat on staging-row disposition) | §4, §6; `RetentionAuditDisclosureTest` sum-reconciliation test |
| AC6 | ADR-005 user-deletion interaction | PASS | §4; `RetentionIdempotencyTest::'backs off cleanly...'` |
| AC7 | Disclosure copy present and accurate | **FAIL as written** — present, but not fully accurate | §5 (Finding 1) |
| AC8 | Option D outcome recorded + executed | PASS (with Finding 2 caveat) | `RetentionAdversarialTest` staging-claim tests |
| AC9 | Backup generations respected | PASS | §7; byte-identical manifest test |
| AC10 | Standard gate | PASS | §3, independently reproduced |
| AC11 | No G-09 claim beyond evidence | PASS | no gate verdict language found in diff/docs |

## 9. Findings Summary

- **Finding 1 (MEDIUM)**: §8.4's required explicit "source purged" surface
  state is not implemented on the transcript view or exports, and
  `docs/RETENTION-POLICY.md` inaccurately states that it is. Fails AC7 as
  written.
- **Finding 2 (MEDIUM)**: `RetentionPurge::purgeStaging()` deletes the
  `StagingClaim` row unconditionally after a failed file deletion, violating
  the explicit row/file atomicity discipline required by §6 item 2. Not
  data-destructive (self-heals via the orphan sweep; failure is correctly
  audited) but untested and contract-deviating.

No BLOCKER or HIGH finding. Per `.ai/guidelines/orchestration-policy.md` /
`CLAUDE.md` governance, BLOCKER/HIGH findings are what strictly prevent
VERIFIED — neither finding here rises to that bar. However, Finding 1
directly fails an explicit, named reviewer-checklist item (§18: "Disclosure
copy present and accurate") and Finding 2 directly violates an explicit,
named scope requirement (§6 item 2), and both are cheap, well-scoped fixes.
Given this repository's evidence-over-convenience culture, this review
returns the task for one corrective cycle rather than accepting a known
gap between the contract and the shipped disclosure text.

## 10. Lifecycle

Previous: `REVIEW`

Result: **`CHANGES_REQUESTED`** (single-owner correction cycle — OpenCode
remains implementation owner per the State-to-Action Contract; this is
cycle 1 of the max-3 allowed).

## 11. Verdict

**CHANGES_REQUESTED**

Required for the next cycle:
1. Resolve Finding 1: either implement a "source purged" indicator on the
   transcript surfaces (view and/or export metadata) or correct
   `docs/RETENTION-POLICY.md` to state only what is actually implemented.
2. Resolve Finding 2: only delete the `StagingClaim` row when the
   corresponding file deletion did not fail, and add a test for the
   staging-claim FS-failure path mirroring the existing media-path test.
3. Re-run the standard gate (full suite ×1 minimum, Pint, PHPStan) and
   the `tests/Feature/Retention/` suite after the fix; report deltas only
   (no need to re-litigate AC1-AC6/AC8-AC11, which this review found sound).

Everything else reviewed in §4 and §7 is correct and does not need rework.

## 12. Files Changed (review-owned only)

- `reviews/P7-011-INDEPENDENT-REVIEW.md` (this artifact, new).

No implementation, task, or Builder-report file was modified by this
review.

## 13. Exact Next Legal Action

**OpenCode (implementation owner) addresses Finding 1 and Finding 2 under
this same task, moves `tasks/P7-011-retention-cleanup.md` back through
IN_PROGRESS → REVIEW with an updated builder report, and Claude Code
performs cycle-2 independent review. TD-007 stays OPEN; no VERIFIED/DONE
transition is authorized by this review.**
