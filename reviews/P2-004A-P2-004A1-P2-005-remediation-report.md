# Phase 2 Remediation Report — Awaiting Independent Re-review

Date: 2026-09-13
Implementation owner: Codex
Authorization: Human Product Owner decision recorded in `DECISIONS.md` ADR-012
Status: REVIEW / AWAITING INDEPENDENT RE-REVIEW

## Reconciled findings

The prior independent review's CHANGES_REQUESTED verdict is preserved
unchanged in `reviews/P2-004A-P2-004A1-P2-005-independent-review.md`. Its
"unauthorized implementation" finding is reconciled as a failure to record the
Product Owner authorization at the time implementation proceeded. The
implementation is not reverted. The old P2-003 VERIFIED review remains valid
only for the revision it inspected.

## Changes made

- `MediaMetadataProbeService` now uses Laravel's public
  `FilesystemAdapter::path()` API rather than the unavailable Flysystem v3
  driver/adapter path-prefix chain.
- The staging claim is created before the first staging byte is written; a
  failed write removes the claim. This closes the claim-creation/cleanup race.
- All previously skipped scenarios in
  `IngestionCompensationContractTest.php` are executable. They cover response
  retry idempotency, intentional duplicate content, owner isolation, promotion
  and persistence compensation, ambiguous duplicate-key recovery, active-claim
  cleanup deferral, same-attempt restaging after cleanup, and durable-orphan
  non-adoption/preservation.
- The metadata test writes a real PCM WAV fixture to the private fake disk and
  runs the configured FFprobe process against the resolved physical path.

## Dependency and phase boundary

ADR-012 records that the minimum FFprobe/FFmpeg runtime needed for P2-005 is an
approved Phase 2 media-ingestion dependency. The implementation only probes an
already persisted private object and updates existing nullable technical fields.
It does not introduce transcription, speech recognition, translation, queues,
workers, Redis, Horizon, processing, P2-007, or broader Phase 3 behavior.

## Verification performed by implementation owner

With `RTFTT_FFPROBE_PATH` set to the installed FFprobe executable:

- targeted ingestion, compensation, cleanup/claim, metadata, and P2-003 tests:
  **32 passed, 143 assertions**;
- the real probe test extracted `duration_seconds=1`,
  `audio_codec=pcm_s16le`, `sample_rate=8000`, and `channels=1` from the WAV
  fixture; and
- the full suite, Pint, PHPStan, frontend build, and repository-mandated checks
  remain to be run for the final handoff below.

## Required independent re-review scope

The next independent review must inspect and reproduce:

1. `app/Actions/MediaIngestionService.php`, including claim creation before
   staging, claim release, compensation, retry identity, and all filesystem/
   database race boundaries;
2. `app/Console/Commands/CleanupStaging.php`, `app/Models/StagingClaim.php`,
   its migration/factory, and cleanup path/age/ownership safety;
3. `app/Services/MediaMetadataProbeService.php`, `config/media.php`, and the
   real private-disk FFprobe path-resolution and parsing behavior;
4. `tests/Feature/IngestionCompensationContractTest.php`, including every
   formerly skipped race/orphan scenario;
5. `tests/Feature/MediaMetadataProbeServiceTest.php` and the real probe fixture;
6. the affected P2-003 contracts and all P2-003 focused/regression tests,
   because `MediaIngestionService.php` changed after its earlier VERIFIED
   boundary; and
7. the governance consistency across ADR-012, `plan.md`, `CURRENT_STATE.md`,
   `DECISION_QUEUE.md`, the four affected task files, and the preserved prior
   review.

The implementation owner must not mark these tasks VERIFIED or DONE. Work may
close them only after an independent reviewer records VERIFIED and confirms
the exact scope above.
