# Remediation Cycle 2 Handoff Report

Date: 2026-09-13

Status: IMPLEMENTED / AWAITING INDEPENDENT RE-REVIEW

## A. Cleanup race root cause

`CleanupStaging` read the `staging_claims` row once, assessed the candidate as
old and unclaimed, and later deleted the staging files and directory without
synchronization or an authoritative final claim check. An ingestion retry
could establish or refresh an active claim in that interval.

## B. Concurrency mechanism selected

Cleanup now performs the final eligibility decision and filesystem deletion in
a database transaction. It locks the per-owner/per-attempt claim row with
`lockForUpdate()`, rechecks the claim and committed `MediaFile`, and creates a
short-lived cleanup claim when no row exists. Ingestion now uses an atomic claim
upsert, so it waits on that row and writes only after cleanup has committed the
directory deletion. A failed deletion rolls the claim transaction back.

This uses the existing database-backed claim architecture without adding a
new state machine or external lock service.

## C. Production files changed

- `app/Console/Commands/CleanupStaging.php`
- `app/Events/StagingCleanupCandidateObserved.php`
- `app/Actions/MediaIngestionService.php`
- `.env.example`
- `README.md`
- `CURRENT_STATE.md`
- `tasks/P2-004A-staging-cleanup-command.md`
- `tasks/P2-004A1-upload-attempt-lease-contract.md`
- `tasks/P2-005-media-metadata-probe.md`
- `tests/Feature/CleanupStagingCommandTest.php`
- `tests/Feature/MediaMetadataProbeServiceTest.php`

`MediaMetadataProbeService.php` itself was not changed in cycle 2; the real
probe implementation remains the accepted direction from the prior cycle.

## D. Exact concurrency test and interleaving

File: `tests/Feature/CleanupStagingCommandTest.php`

Test: `cleanup does not delete a candidate claimed after initial eligibility observation`.

It creates an old staging file, lets cleanup observe it as eligible, creates an
active claim at the observation event, and verifies cleanup defers and the file
remains. This exercises the production command, database claim model, and
filesystem adapter rather than a mock-only assertion.

## E. FFprobe documentation

`README.md` and `.env.example` document the metadata-only FFprobe dependency,
default PATH behavior, `RTFTT_FFPROBE_PATH`, verification commands, and safe
null-metadata behavior when the executable is unavailable. No transcription or
other Phase 3 dependency was added.

## F. FFprobe capability/skip behavior

The real WAV fixture test checks `MediaMetadataProbeService::isAvailable()` and
calls `markTestSkipped()` with a clear reason if FFprobe is unavailable. It is
not mocked. Missing-file and probe-failure behavior remains covered by the
existing service tests.

## G. Real-probe evidence

FFprobe was available in this environment at
`C:\\Users\\Admin\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg.Shared_Microsoft.Winget.Source_8wekyb3d8bbw\\e\\ffmpeg-9.0.1-full_build-shared\\bin\\ffprobe.exe`.
The real WAV fixture test ran successfully and verified approximately one
second, `pcm_s16le`, 8000 Hz, and one channel. No skip was used for that test
in this environment.

## H. Exact verification commands and results

Focused remediation/regression command:

```text
& 'C:\\Users\\Admin\\.config\\herd\\bin\\php84\\php.exe' artisan test --compact tests/Feature/CleanupStagingCommandTest.php tests/Feature/StagingClaimTest.php tests/Feature/IngestionCompensationContractTest.php tests/Feature/MediaMetadataProbeServiceTest.php tests/Feature/MediaIngestionTest.php
```

Result: 47 passed, 0 failed, 0 skipped, 177 assertions.

Full suite:

```text
& 'C:\\Users\\Admin\\.config\\herd\\bin\\php84\\php.exe' artisan test --compact
```

Result: 216 passed, 0 failed, 1 skipped, 649 assertions (217 tests total).

Formatting:

```text
& 'C:\\Users\\Admin\\.config\\herd\\bin\\php84\\php.exe' vendor/bin/pint --dirty --format agent
```

Result: passed; one import-order/style normalization was applied to the new
cleanup test.

Static analysis:

```text
& 'C:\\Users\\Admin\\.config\\herd\\bin\\php84\\php.exe' vendor/bin/phpstan analyse
```

Result: passed, 0 errors.

Frontend build:

```text
npm run build
```

Result: passed.

Diff whitespace check:

```text
git diff --check
```

Result: passed (one pre-existing CRLF normalization warning was emitted).

## I. P2-003 verified surface

The earlier P2-003 regression re-review remains preserved as historical
evidence. This cycle changes `MediaIngestionService` claim upserting, which is
part of the previously verified production surface. That affected surface is
explicitly reopened and is not self-declared VERIFIED here.

## J. Final task states

- P2-003: REVIEW / AWAITING INDEPENDENT REGRESSION RE-REVIEW for the changed claim lifecycle; prior review verdict preserved as historical VERIFIED.
- P2-004A: REVIEW / AWAITING INDEPENDENT RE-REVIEW.
- P2-004A1: REVIEW / AWAITING INDEPENDENT RE-REVIEW.
- P2-005: REVIEW / AWAITING INDEPENDENT RE-REVIEW.
- P2-004: closure remains valid.
- P2-006: closure remains valid.
- P2-007: NOT STARTED / UNAUTHORIZED.
- Phase 3: NOT STARTED / UNAUTHORIZED.

## K. Remaining risks

- Filesystem deletion is coordinated with, but not atomically part of, the
  database transaction; partial storage failure leaves the artifact for retry.
- Correctness depends on all claim writers using the shared claim row.
- FFprobe output/version variance remains an environment concern; probe
  failure safely returns null metadata.

## L. Independent re-review cycle 3 scope

Re-review only the changed claim lifecycle and cleanup boundary, the genuine
post-observation race test, FFprobe documentation/capability guard and real
probe behavior if an FFprobe-capable environment is available, exact
verification evidence, and final task-state records. Do not start P2-007 or
Phase 3.
