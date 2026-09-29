# PP-T6 — Final Gate Evidence (Step 2)

Date: 2026-09-28. Authorization: `DECISION-PP-T6-EXECUTION-AUTHORIZATION-001`.
Contract: `tasks/PP-T6-integration-compatibility-verification.md` (Step-1 reconciled).
Readiness: `reviews/PP-T6-READINESS-REVIEW.md` (`READY-ELIGIBLE`).

## 1. Repository / environment identification

- Repo: RTFTT Transcribe AI, working tree (source of truth). HEAD `95e8143`
  (pre-Linux remediation); all PP-T1–PP-T5 records + implementation live in
  the working tree, identical operating mode to every prior PP step.
- Runtime: PHP 8.4.24 (cli, NTS VC++2022 x64); `php -d memory_limit=1G`
  workaround (known A-03, CLI 128M ceiling on Blade compile).
- Test env: SQLite in-memory, sync queue (per repo test config).
- Zero-egress posture: reference/external paths exercised via
  `tests/Support/FakeReferenceExternalTransport.php` and
  `tests/Support/FakeReferenceExternalTranslationTransport.php` only; no live
  vendor calls, no credentials, no customer media. No secret printed anywhere
  in this evidence.

## 2. Commands executed (exact)

```powershell
php -d memory_limit=1G vendor/bin/pest tests/Feature/ProcessingProvider tests/Feature/SelfHostedTranscriptionProviderTest.php tests/Feature/Translation/SelfHostedTranslationProviderTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Transcription/TranscriptionProviderResolutionTest.php tests/Feature/Transcription/ReferenceExternalProviderResolutionTest.php tests/Feature/Transcription/ReferenceExternalTranscriptionProviderTest.php tests/Feature/Translation/TranslationProviderResolutionTest.php tests/Feature/Translation/ReferenceExternalProviderResolutionTest.php tests/Feature/Translation/ReferenceExternalTranslationProviderTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/MediaIngestionTest.php tests/Feature/MediaUploadContractTest.php tests/Feature/MediaManagementTest.php tests/Feature/IngestionCompensationContractTest.php tests/Feature/MediaStreamingTest.php tests/Feature/MediaMetadataProbeServiceTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Transcription
php -d memory_limit=1G vendor/bin/pest tests/Feature/Translation tests/Unit/Transcription tests/Unit/Translation
php -d memory_limit=1G vendor/bin/pest tests/Unit/Editing tests/Feature/Editing tests/Unit/Comparison tests/Feature/TranscriptRevisionAwareExportTest.php tests/Feature/TranscriptExportTest.php tests/Feature/TranscriptExportHardeningTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Editing/RevisionHistoryActivationTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Observability tests/Feature/TranscriptExportHardeningTest.php tests/Feature/TranscriptionDetailTest.php tests/Feature/TranscriptionManagementTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Observability/LogContextTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/TranscriptExportHardeningTest.php tests/Feature/TranscriptionDetailTest.php tests/Feature/TranscriptionManagementTest.php
php -d memory_limit=1G vendor/bin/pest
composer lint:check
composer types:check
```

Browser/Playwright: N/A — PP-T6 ships no UI-affecting change (test-only
corrective cycle + `verification/` evidence + governance); per reconciled
§14 this justification is stated, not silently omitted.

## 3. Focused suite results (AC6 core + AC7 targeted)

| Surface | Result |
|---|---|
| PP-T5 + self-hosted adapters (`ProcessingProvider/`, `SelfHostedTranscriptionProviderTest`, `Translation/SelfHostedTranslationProviderTest`) — first run | 48/49 (1 fail: PP-T6-REV-01, stale BACKLOG pin — preserved below) |
| Same surface after corrective cycle 1 | 49/49 pass, 270 assertions |
| PP-T1–T4 resolution + reference adapters (6 files, both domains) | 106/106 pass, 406 assertions |

## 4. Regression results (AC1–AC5)

| AC | Surface | Result |
|---|---|---|
| AC1 Phase 2 | MediaIngestion, MediaUploadContract, MediaManagement, IngestionCompensationContract, MediaStreaming, MediaMetadataProbeService | 92/92 pass (2 pre-existing warnings: 1× MediaUploadContract, 1× IngestionCompensationContract — same baseline-warning class recorded at PP-T5) |
| AC2 Phase 3 | `tests/Feature/Transcription/` | 120/120 pass |
| AC3 Phase 5 | `tests/Feature/Translation/` + `tests/Unit/Transcription/` + `tests/Unit/Translation/` | 315/315 pass |
| AC4 Phase 6 | `tests/Unit/Editing/` + `tests/Feature/Editing/` + `tests/Unit/Comparison/` + 3 export suites | 251/251 pass (2 pre-existing warnings) |
| AC4 Phase 6 | `RevisionHistoryActivationTest` (A-02 watch) | 12/12 pass |
| AC4 Phase 6 | ExportHardening + TranscriptionDetail + TranscriptionManagement | 23/23 pass |
| AC5 Phase 7 | `tests/Feature/Observability/LogContextTest` in batch | A-01 struck (see §7); 6/6 pass in isolation |

## 5. Full-suite result (AC7)

`php -d memory_limit=1G vendor/bin/pest`: **1253 tests, 1248 passed,
5 skipped (pre-existing), 0 failures**, 4943 assertions, 4 warnings
(known baseline-warning class). Clean run — A-01 did not strike.

## 6. Lint / static analysis (AC7)

- `composer lint:check` (Pint): passed.
- `composer types:check` (PHPStan): passed, 0 errors.

## 7. Anomaly handling (§13 register)

- **A-01 struck once**: batch run §2-line-9 failed
  `LogContextTest::counts_attempt_ordinals…` with
  `SQLSTATE[23000] UNIQUE constraint failed: processing_jobs.transcription_id`
  at test setup (`LogContextTest.php:34`, a raw `processing_jobs` insert —
  provider code uninvolved; PP tests perform zero DB writes). Failing run
  preserved in this report. Isolation rerun: **6/6 pass**. Classification
  holds: pre-existing order-dependent flake (PP-T1-REV-03 / PP-T5 §2), NOT
  PP-attributable. Both outcomes recorded; nothing concealed.
- A-02 did not strike (`RevisionHistoryActivationTest` 12/12 in the same run
  class where it previously flaked).
- A-03 applied throughout (`memory_limit=1G`); environmental, unrelated.
- Warnings (4 full-suite; 2 Phase-2 batch; 2 Phase-6 batch): pre-existing
  baseline-warning class per PP-T5 record; zero failures attached.

## 8. Dual-domain proof (AC6 detail, per-domain — no substitution)

Transcription: self-hosted adapter + resolver matrix + reference adapter
(normalization incl. chunk offsets/overlaps/duplicates, §9 error map incl.
timeout/429/5xx/saturation, request/chunk identity, `maxRequestBytes` →
`MediaRejected` boundary−1/0/+1, kill-switch disengaged/engaged/invalid,
no-fallback, zero-egress) — all green in 106-file run + 49-file run.
Translation: self-hosted adapter + resolver matrix + reference adapter
(alignment incl. ms/en/zh/ta + `und`, single-shot 1:1, §9 error map,
request identity, `maxSegments`/`maxPayloadChars` → `InvalidRequest`
boundary−1/0/+1, kill-switch both-domains incl. invalid value, no-fallback,
zero-egress) — all green in the same runs. Secrets: dual-domain
`ConfigurationError` fail-closed + absence scans green (PP-T5 suites).
Degraded observability: `LogContext` never-throw + selection-invariance green.

## 9. Kill-switch / fail-closed proof (§9)

Disengaged: selections follow canonical keys (PP-T2 matrix + PP-T5
rehearsal tests green). Engaged: both domains resolve self-hosted with zero
adapter dispatches (PP-T5 AC3 tests green). Invalid value: fails closed as
engaged with named-value warning (PP-T2 + PP-T5 tests green). Invalid
selection / missing credentials: fail closed before dispatch, zero egress
(PP-T2 AC3/AC4 + PP-T5 AC5 tests green). No fallback chain anywhere
(resolution + fault-injection asserts green).

## 10. Runtime-diff audit (§14)

PP-T6 Step-2 changed files (classified):

- PP-T6 tests/harness: `tests/Feature/ProcessingProvider/PpT5DegradedObservabilityAuditTest.php`
  — corrective cycle PP-T6-REV-01 ONLY (leakage-audit expectation updated for
  authorized PP-T6 lifecycle; no assertion weakened — guards preserved and
  extended; Pint-clean by construction, single-test-file edit).
- PP-T6 verification evidence: `verification/pp-t6/` (this report).
- PP-T6 review/governance: task file §1 lifecycle lines; this report;
  (review + closure records to follow).
- Pre-existing unrelated work: all other worktree diffs (PP-T1–PP-T4
  implementation, PP-T5 tests/runbook, discovery, Phase 7 wave records) —
  pre-date Step 2, belong to closed sessions.
- Unexpected runtime/config/schema changes: **ZERO**. No file under `app/`,
  `config/`, `database/`, worker changed by PP-T6 (verified via
  `git status --porcelain` scoping: only the one test file + governance/
  verification paths added/touched by this gate).

## 11. AC1–AC8 verdict

```text
AC1 PASS (92/92 Phase-2 surfaces; ingestion/storage/checksum/500 MiB intact)
AC2 PASS (120/120 transcription dir + unit transcription in 315-run)
AC3 PASS (315/315 translation + transcription-unit run; NLLB/staleness intact)
AC4 PASS (251/251 editing/comparison/export + 12/12 + 23/23; F-001 guard suites green)
AC5 PASS (LogContext 6/6 isolated; A-01 batch strike preserved + classified pre-existing)
AC6 PASS (49/49 + 106/106; both domains independently proven, §§8–9)
AC7 PASS (full 1253: 1248 pass / 5 pre-existing skips / 0 fail; Pint clean; PHPStan 0 errors; browser N/A justified)
AC8 PASS (this report; FAIL run PP-T6-REV-01 preserved in §3/§12 with remediation)
```

## 12. Corrective cycle + FAIL preservation

- **PP-T6-REV-01 (LOW, test-only, resolved in-cycle)**: first focused run
  48/49 — `PpT5DegradedObservabilityAuditTest::PP-T6 leakage audit` asserted
  the PP-T5-era pin (`BACKLOG` + `NOT AUTHORIZED` + no `verification/pp-t6/`),
  invalidated by PP-T6's own legal `READY → IN_PROGRESS` transition under
  `DECISION-PP-T6-EXECUTION-AUTHORIZATION-001`. Original failure output
  preserved in the builder run log (assertion dump recorded at execution).
  Correction: audit now requires any non-BACKLOG state and any gate evidence
  dir to cite the execution authorization, keeping the app/-runtime scan
  intact — no assertion weakened. Rerun: 49/49. No runtime, config, schema,
  or closed-contract change. PP-T5's DONE record is not rewritten (its
  closure-time truth stands; only the forward-looking guard was reconciled).

## 13. Final builder verdict

Gate executed strictly within the reconciled contract. AC1–AC8 PASS on fresh
evidence. One LOW test-only corrective cycle, resolved and re-verified.
Runtime diff clean. Builder claims neither VERIFIED nor DONE — lifecycle
`IN_PROGRESS → REVIEW` for independent review.
