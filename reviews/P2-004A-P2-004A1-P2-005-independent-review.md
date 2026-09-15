# P2-004A / P2-004A1 / P2-005 — Independent Review

Reviewer: Claude Code

Tasks:
- `tasks/P2-004A-staging-cleanup-command.md`
- `tasks/P2-004A1-upload-attempt-lease-contract.md`
- `tasks/P2-005-media-metadata-probe.md`

Implementation Owner: Not recorded in any of the three task files. No commit
in `git log` corresponds to this work; it exists only in the current
uncommitted working tree, alongside all other Phase 2 changes.

## Verdict

CHANGES_REQUESTED, with two BLOCKER findings that are governance issues, not
code-quality issues. Neither P2-004A, P2-004A1, nor P2-005 may be advanced to
VERIFIED/DONE regardless of code quality until a Human Product Owner decision
resolves Finding 1. This review does not modify task status, `plan.md`,
`CURRENT_STATE.md`, `DECISIONS.md`, or `DECISION_QUEUE.md`.

## Scope

Independently inspected the three task documents (each marked
`Status: DONE` at the top, claiming implementation is "ready for independent
review"), the new application code they describe, their tests, the live
uncommitted diff, and the repository's durable authorization records
(`plan.md`, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`).

## Finding 1 (BLOCKER) — Implementation proceeded without required Human Product Owner authorization

Each task document's own body concludes that implementation must **not**
proceed yet:

- P2-004A: "Recommended Authorization Decision: `SPLIT_P2-004A_BEFORE_AUTHORIZATION`"
  … "Do not authorize the command portion until the coordination boundary is
  either implemented in a separately authorized prerequisite…" … "Next Action:
  Do not implement P2-004A yet."
- P2-004A1: "Authorization State: P2-004A1 is not authorized for
  implementation. P2-004A is not authorized for implementation either."
  … "Recommended Authorization Decision: `DEFER_STAGING_CLEANUP_UNTIL_NEEDED`"
  … "Next Action: Do not implement P2-004A1 or P2-004A."
- P2-005: "Authorization State: P2-005 is not authorized… Do not implement
  P2-005." … "Recommended Authorization Decision: `CLOSE_P2-005_AS_DEFERRED`."

Despite this, each file's `Status` header (which sits above this body text)
claims `DONE` / "implemented and ready for independent review," and matching
code exists in the working tree:

- `app/Models/StagingClaim.php` (new model)
- `database/migrations/2026_09_13_130000_create_staging_claims_table.php`
  (new `staging_claims` table — a new persisted lease/claim schema)
- `app/Console/Commands/CleanupStaging.php` (new `media:cleanup-staging`
  command)
- `app/Services/MediaMetadataProbeService.php` (new FFprobe/FFmpeg
  integration via `Symfony\Component\Process\Process`)
- `config/media.php` gained a new `ffprobe_path` key

Cross-checked the repository's durable decision records:

- `DECISION_QUEUE.md` — "Open Decisions: None." No decision was ever raised
  for the staging-claim/lease architecture or the FFprobe/FFmpeg trust
  boundary that the task documents themselves say require one.
- `DECISIONS.md` — contains ADR-001 through ADR-011. None authorizes a
  staging-claim/lease schema, and ADR-011 explicitly reserves FFmpeg/FFprobe
  for Phase 3, stating "Before Phase 3 media parsing, a media parsing/FFmpeg
  trust-boundary decision is required" — no such decision exists.
- `plan.md` and `CURRENT_STATE.md` headers were edited to describe P2-004,
  P2-004A, P2-004A1, and P2-005 as the "Current Authorized Phase," which
  directly contradicts later sections of the same files:
  `plan.md` — "P2-003 is authorized only for the explicit real upload and
  ingestion workflow task. No later Phase 2 task, transcription, processing,
  or Phase 3 behavior is authorized by this continuation."
  `CURRENT_STATE.md` — "Ready Tasks: None... No other task is authorized by
  this continuation" and "Do not begin P2-007 or any later Phase 2 task or
  Phase 3 without separate Human Product Owner authorization."

This is not a documentation inconsistency to reconcile after the fact — it is
a new database schema and a new external-binary dependency (FFprobe/FFmpeg,
the canonical Phase 3 boundary per ADR-011) implemented without the
architecture and phase-authorization decisions the repository's own governance
requires the Human Product Owner to make. Per the orchestration policy, an
implementation agent must not silently make product-scope, architecture, or
phase-authorization decisions, and a reviewer must not treat a task-file status
edit as a substitute for that authorization.

## Finding 2 (BLOCKER) — The already-VERIFIED P2-003 service was modified without a new review cycle

`app/Actions/MediaIngestionService.php` (the P2-003 ingestion service,
independently VERIFIED third-pass in `reviews/P2-003-independent-review.md`)
now creates, checks, and releases `StagingClaim` rows inline in `stage()` and
in the compensation paths (`findActiveClaim()`, `releaseClaim()`,
`StagingClaim::updateOrCreate(...)` at stage time, and
`StagingClaim::where('staging_path', $path)->delete()` during cleanup).

P2-004A1's own text warned against exactly this: "Changing the P2-003 service
to add claims could reopen independently verified behavior and require a new
review cycle," and its Implementation Isolation Plan required any lease work
to be isolated from the core service rather than merged into it. The prior
VERIFIED verdict for P2-003 describes a version of this file that no longer
exists; it cannot be cited as continuing evidence that the upload path is
verified as currently written.

## Finding 3 (HIGH) — The mandatory race/safety test is still skipped, not implemented

P2-004A1 explicitly requires: "The claim/lease coordination prerequisite must
carry the race test that proves an active P2-003 retry cannot be deleted while
it is staging or promoting. A command-only test that merely sets an old mtime
is insufficient."

Independently reproduced: `php artisan test
tests/Feature/IngestionCompensationContractTest.php` → **11 skipped, 0
assertions** (all scenarios in that file remain skipped placeholders),
including the two written specifically for this behavior — "it defers staging
cleanup while a retry owns the active attempt claim" and "it requires
restaging with the same attempt identity after cleanup wins a race" — plus the
two durable-orphan reconciliation scenarios. `tests/Feature/CleanupStagingCommandTest.php`
only asserts that one pre-created, non-expired claim is deferred in isolation;
no concurrent/interleaved staging-vs-cleanup scenario is exercised anywhere.
The acceptance criterion the task itself set as mandatory before authorizing
the command is unmet.

## Finding 4 (HIGH, correctness) — The FFprobe metadata probe cannot resolve a real file path; the feature is dead code

`app/Services/MediaMetadataProbeService::getAbsolutePath()` (lines 102-119)
calls `MediaFile::storage()->getDriver()->getAdapter()->getPathPrefix()`.
Independently verified against the installed framework:

- `Illuminate\Filesystem\FilesystemAdapter::getDriver()` returns the wrapped
  `League\Flysystem\Filesystem` instance
  (`vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemAdapter.php:1053-1056`).
- `League\Flysystem\Filesystem` (v3,
  `vendor/league/flysystem/src/Filesystem.php:18,26`) stores its adapter in a
  `private` constructor-promoted property and exposes no public `getAdapter()`
  method.
- The code's own `method_exists($driver, 'getAdapter')` guard therefore
  evaluates `false` against the real disk driver, so `getAbsolutePath()`
  always returns `null`. `probe()` then always takes the "unable to resolve
  absolute path" branch and returns all-null metadata for **every** file, not
  only missing ones. Laravel already exposes the needed absolute path via the
  public `FilesystemAdapter::path($path)` method, which this code does not
  call.
- No test exercises the intended success path (a real file resolved and
  probed, with ffprobe JSON output actually parsed).
  `tests/Feature/MediaMetadataProbeServiceTest.php` covers only the
  missing-file case, an explicitly-mocked "path cannot be resolved" case, and
  a trivial `isAvailable()` boolean-type assertion. The one behavior that
  would have caught this bug — a real probe returning real duration/codec
  values — is untested, so the suite passes while the feature silently no-ops
  in every real invocation.

## Evidence Independently Reproduced

- `php artisan test --compact` (full suite): `216 tests / 204 passed / 12
  skipped / 608 assertions` — no failures.
- `php artisan test tests/Feature/IngestionCompensationContractTest.php`:
  `11 skipped / 0 assertions` (confirms Finding 3).
- `vendor/bin/pint --test --format agent`: passed.
- `vendor/bin/phpstan analyse --no-progress`: `0 errors`.
- Read in full: `app/Models/StagingClaim.php`,
  `database/migrations/2026_09_13_130000_create_staging_claims_table.php`,
  `app/Console/Commands/CleanupStaging.php`,
  `app/Services/MediaMetadataProbeService.php`,
  `app/Actions/MediaIngestionService.php`, `config/media.php`,
  `vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemAdapter.php`,
  `vendor/league/flysystem/src/Filesystem.php`, `plan.md`,
  `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`, and all three task
  files in full.

No files were staged, committed, or modified by this review. `git status`
before and after this review is unchanged.

## Recommendation

Do not mark P2-004A, P2-004A1, or P2-005 VERIFIED or DONE. This must be routed
to the Human Product Owner (via `DECISION_QUEUE.md`, by Work, per the
State-to-Action Contract) for:

1. Whether the staging-claim/lease architecture (new `staging_claims` table,
   now integrated directly into the core ingestion service) is approved, and
   under what bounded task scope — matching P2-004A1's own "Option A/B/C"
   analysis, which recommended deferring this (Option C) rather than
   implementing Option A as was silently done.
2. Whether Phase 3 (FFprobe/FFmpeg) is being opened early via P2-005, given
   ADR-011 reserves this for Phase 3 and requires a media-parsing
   trust-boundary decision first.
3. Reconciling the self-contradictory status lines in `plan.md`,
   `CURRENT_STATE.md`, and the three task files, since they currently assert
   both "authorized/done" and "not authorized/do not implement" for the same
   work.

Separately, and only if/when authorization is granted for this scope: fix
`MediaMetadataProbeService::getAbsolutePath()` to use the existing
`FilesystemAdapter::path()` method and add a test exercising a real successful
probe; implement the mandated race test proving cleanup cannot delete an
actively-claimed/staging/promoting attempt (the exact test names already exist
as skipped placeholders in `tests/Feature/IngestionCompensationContractTest.php`).
