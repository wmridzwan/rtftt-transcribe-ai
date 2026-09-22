# Phase 3 — P3-008 Integration Verification Evidence

Date: 2026-09-19
Task: `tasks/P3-008-real-phase-integration-verification.md`
Status: EVIDENCE CAPTURED — P3-008 awaiting independent verification
Authority: ADR-017; ADR-018; `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`

This artifact records the Phase 3 integration verification executed by the
Builder. It does not mark P3-008 VERIFIED or DONE and does not close Phase 3.
The independent reviewer owns the verdict.

---

## A. Environment

| Component | Version / Detail |
|---|---|
| PHP | 8.4.24 (NTS, Visual C++ 2022 x64) |
| Laravel | 13.31.0 |
| Python | 3.13.14 |
| faster-whisper | 1.2.1 (ctranslate2 4.8.2) |
| FFmpeg | 9.0.1-full_build (Gyan) |
| Redis | 3.0.504 (Microsoft Archive Windows port, portable) |
| Model | `large-v3` (`Systran/faster-whisper-large-v3`, cached) |
| Queue driver | `redis` (connection `redis`), queue `transcription` |
| Database | SQLite (dedicated file-backed DB for the integration run) |
| Device / compute | `cpu` / `int8` |
| OS | Windows (win32) |

Environment provisioning (no repository/system-persistent change): a portable
Redis 3.0.504 server was started on `127.0.0.1:6379`; the project's Python
`.venv` ran `uvicorn worker.main:app` on `127.0.0.1:8000` with
`RTFTT_SHARED_MEDIA_ROOT=<repo>/storage/app/private`,
`RTFTT_WHISPER_MODEL=large-v3`, `RTFTT_WHISPER_DEVICE=cpu`,
`RTFTT_WHISPER_COMPUTE_TYPE=int8`, and a shared-secret bearer token.

## B. Mandatory Gate Status

```
Live Redis = PASS
Real FFmpeg + faster-whisper large-v3 = PASS
```

Both mandatory B3-06/B3-07 gates were exercised with real infrastructure and
real inference. No database-queue substitution and no mocked provider were used
for these gates.

## C. End-to-End Happy Path

Real private MP3 fixture (Windows SAPI speech → FFmpeg MP3, 8.649s) →
transcription request through `TranscriptionOrchestrator::request()` →
`ProcessTranscription` dispatched onto **live Redis** (`transcription` queue) →
real `queue:work redis` worker consumed it → `HttpTranscriptionProvider` →
authenticated Python worker → real FFmpeg → real faster-whisper `large-v3` →
normalized result → transcript + segment persistence → completed.

Final authoritative DB state (assert output):

```json
{
  "transcription_status": "completed",
  "attempt_status": "completed",
  "full_text": "Hello. This is a phase 3 integration verification recording. The quick brown fox jumps over the lazy dog.",
  "detected_language": "en",
  "requested_language": "en",
  "speech_detected": true,
  "model": "large-v3",
  "segment_count": 1,
  "segment_languages": ["en"],
  "segment_indices": [0],
  "processing_seconds": 58,
  "error_message": null,
  "attempt_count": 1,
  "active_attempt_count": 0
}
```

Worker wall time: 58.8s. Redis queue length after: 0.

## D. Queue / Redis Evidence

Live Redis key: `laravel-database-queues:transcription`, list length 1 before
consumption. Serialized payload (633 bytes) — ID-only:

```json
{"uuid":"1127639d-...","displayName":"App\\Jobs\\ProcessTranscription",
 "job":"Illuminate\\Queue\\CallQueuedHandler@call","maxTries":1,
 "data":{"commandName":"App\\Jobs\\ProcessTranscription",
 "command":"O:29:\"App\\Jobs\\ProcessTranscription\":4:{s:15:\"transcriptionId\";i:1;s:19:\"processingAttemptId\";i:1;s:10:\"connection\";s:5:\"redis\";s:5:\"queue\";s:13:\"transcription\";}"}}
```

Payload checks:

```json
{
  "queue_key": "queues:transcription",
  "list_length": 1,
  "payload_bytes": 633,
  "contains_storage_path": false,
  "contains_transcription_id": true,
  "contains_attempt_id": true,
  "contains_binary": false
}
```

Only `transcriptionId` and `processingAttemptId` are serialized (plus the
framework envelope). No media path, binary, user identity, provider result, or
retry-policy object. `maxTries = 1` (transport single-attempt).

## E. Real Worker Evidence

- uvicorn access log: three `POST /transcribe HTTP/1.1 200 OK` (happy path,
  no-speech, retry) and `GET /health 200 OK`.
- faster-whisper log: `Processing audio with duration 00:08.649` (happy path and
  retry) and `00:05.000` (silence), `VAD filter removed ...`, then
  `INFO:rtftt-worker:transcription_complete`.
- Model fetched from `Systran/faster-whisper-large-v3` (`HTTP/1.1 200 OK`).
- Direct worker FFmpeg invocation (`worker.ffmpeg.prepare_audio`) on the same
  fixture produced a temporary WAV verified by `ffprobe` as
  `codec_name=pcm_s16le`, `sample_rate=16000`, `channels=1` (canonical profile).
- The extracted audio was ephemeral: the configured prepared-audio directory
  was empty before and after every run; no temporary file became durable media.

## F. Language / Code-Switching

Per the accepted evidence model:

- **Real worker smoke evidence**: the real `large-v3` run produced
  `detected_language = en` and a segment language `en` from real speech,
  proving the real per-segment detection path executes.
- **Deterministic normalized-result integration evidence**: the Batch 2 suite
  (re-run green) proves mixed segment languages `ms/en/zh/ta/und`, `und`
  fallback, mixed-script Unicode, segment language independent from transcript
  dominant language, and requested-language non-overwrite
  (`SegmentPersistenceTest`, `TranscriptPersistenceTest`, `ProcessTranscriptionJobTest`).

A single short English clip was deliberately not used to assert multi-language
output (model/environment-sensitive); the deterministic fixtures carry that
contract, as P3-008 permits.

## G. No-Speech

Real silence fixture (FFmpeg `anullsrc`, 5.0s) through the **real** Redis →
worker → faster-whisper path. VAD removed the full 5.0s and no speech was
produced. Final state:

```json
{
  "transcription_status": "completed",
  "attempt_status": "completed",
  "full_text": "",
  "detected_language": "und",
  "speech_detected": false,
  "model": "large-v3",
  "segment_count": 0,
  "error_message": null
}
```

No-speech is a successful terminal result; it did not enter retry/failure
recovery and produced no false transcript content.

## H. Segments / Timestamps / Unicode

Batch 2 regression (green, unmodified): deterministic `segment_index` ordering,
unique `(transcription_id, segment_index)`, `decimal(12,3)` fractional-timestamp
precision, SRT/VTT formatter compatibility, replay idempotency, and Unicode
persistence across BM/English/Chinese/Tamil scripts.

## I. Failure Taxonomy

P3-007 unit/feature tests (green): `TranscriptionFailure` retryable set is
exactly `WORKER_UNAVAILABLE, WORKER_TIMEOUT, WORKER_SATURATED,
RESOURCE_EXHAUSTED`; a worker envelope `retryable: true` for `FFMPEG_FAILED` is
still resolved non-retryable through `WorkerErrorResponse`; safe
`error_message` with no raw exception/SQL/path leakage; normalized
`failure_code` persisted on the attempt.

## J. Manual Retry / Recovery

Real manual-retry integration through live Redis + real worker:

1. Seeded a `failed` transcription with a failed attempt
   (`failure_code = WORKER_TIMEOUT`).
2. Explicit `TranscriptionRetry::retry()` created a new attempt and dispatched
   onto Redis.
3. Real `queue:work redis` consumed it; real `large-v3` inference completed.

Final attempts:

```json
[
  { "id": 3, "status": "failed", "failure_code": "WORKER_TIMEOUT" },
  { "id": 4, "status": "completed", "failure_code": null }
]
```

Same `transcription_id`; new attempt id; old attempt preserved and immutable;
transcription `completed`; exactly 2 attempts; 0 active. No automatic retry
occurred before the explicit manual action.

Stale running recovery, obsolete-worker protection, repeated-retry idempotency,
and the one-active-attempt invariant are covered by the green
`StaleTranscriptionAttemptRecoveryTest` and `TranscriptionRetryTest`.

## K. Concurrency

Genuine independent-process evidence re-run green:

- `TranscriptionClaimConcurrencyTest` (P3-006 CAS claim; two OS processes).
- `TranscriptionRetryConcurrencyTest` (P3-007 retry CAS; two OS processes,
  separate SQLite connections, deterministic barrier, exactly one winner, one
  new active attempt, one dispatch, loser resolves winner).

Combined result: 2 passed, 22 assertions.

## L. Ownership / Isolation

Green: cross-user retry denied (`403`, no new attempt), unauthenticated retry
redirects to login, ownership integrity asserted server-side, worker payload
cannot carry owner identity. Pre-existing admin `update` policy semantics remain
the deferred actor-vs-owner gate (INFO, not a P3-008 regression).

## M. Persistence / Atomicity

Green Batch 2 tests: persistence failure rolls back with no partial transcript
fields/segments and no false completion; duplicate delivery invokes the provider
exactly once and does not duplicate segments; completed state cannot be
overwritten by a stale write.

## N. Phase 1 / Phase 2 Regression

Full PHP suite green (see Q). Phase 1 screens/navigation/metadata/export and
Phase 2 upload validation, 500 MiB contract, checksum, private storage,
ownership, and ingestion lifecycle tests all pass unmodified. No Phase 3
regression introduced.

## O. Batch 1 / Batch 2 / P3-007 Regression

- Batch 1: provider-neutral DTOs, `large-v3`, mixed-language/`und` contract,
  worker error envelope, ephemeral audio, opaque media reference — green.
- Batch 2: transcript/segment persistence, fractional timestamps, atomic
  completion, unique segment index, queue claim CAS, stale/newer-attempt
  protection, independent-process claim evidence — green.
- P3-007: manual-only retry, normalized failure taxonomy, retry CAS,
  one-active-attempt invariant, independent-process retry evidence, stale
  recovery, obsolete-worker protection, completed-state protection — green.

## P. P3-008 Matrix

| ID | Scenario | Evidence | Result | Notes |
|----|----------|----------|--------|-------|
| I-01 | Happy path E2E | Live Redis + real worker run | PASS | `completed`, real transcript text, `large-v3` |
| I-02 | No speech | Real silence through live Redis + real worker | PASS | `text=""`, `speech_detected=false`, `und`, 0 segments, completed |
| I-03 | Mixed languages ms/en/zh/ta/und | Deterministic fixtures (Batch 2 suite) | PASS | Segment language independent of transcript language |
| I-04 | Unicode / mixed scripts | Batch 2 suite | PASS | Round-trip preserved |
| I-05 | Fractional segment timestamps | Batch 2 suite + export tests | PASS | `decimal(12,3)`; SRT/VTT compatible |
| I-06 | Provider failure | P3-007/Batch 2 tests | PASS | Deterministic failed state; safe message |
| I-07 | Malformed provider output | Worker/validator tests | PASS | `INVALID_WORKER_RESPONSE`, terminal |
| I-08 | Persistence failure | `AtomicCompletionTest`, `ProcessTranscriptionJobTest` | PASS | Atomic rollback; no false completion |
| I-09 | Retry success after transient failure | Real retry integration (live Redis + real worker) | PASS | New attempt completed; old attempt preserved |
| I-10 | Repeated retry request | `TranscriptionRetryTest` | PASS | One new attempt; one dispatch |
| I-11 | Concurrent retry requests | `TranscriptionRetryConcurrencyTest` | PASS | Independent processes; exactly one winner |
| I-12 | Stale old job after newer attempt | `ProcessTranscriptionJobTest`, `TranscriptionQueueOrchestrationTest` | PASS | No-op; no regression |
| I-13 | Retry after completed | `TranscriptionRetryTest`, `TranscriptionRetryHttpTest` | PASS | Rejected/no-op; no overwrite |
| I-14 | Cross-user access | `TranscriptionRetryHttpTest`, Batch 2 ownership tests | PASS | Denied; no persistence |
| I-15 | Second failure after retry | `TranscriptionRetryTest` | PASS | Deterministic terminal failed; re-eligible |
| I-16 | Queue payload path/binary-free | Live Redis payload inspection | PASS | Only ids; 633 bytes |
| I-17 | Duplicate queue delivery | Batch 2 queue tests | PASS | Provider invoked once; no duplicate segments |
| I-18 | Redis outage | `TranscriptionQueueOrchestrationTest` | PASS | Surfaced safely; no corruption |
| I-19 | Live Redis integration | Real Redis 3.0.504 run | PASS | Serialized job consumed by real worker |
| I-20 | Real worker + FFmpeg + faster-whisper | Real `large-v3` run + direct FFmpeg check | PASS | HF `Systran/faster-whisper-large-v3` |
| I-21 | Ephemeral prepared audio cleanup | Prepared dir empty before/after | PASS | Temp WAV removed; no durable audio |
| I-22 | Phase 1/2/Batch1/Batch2 regression | Full suite | PASS | 365 passed / 1 pre-existing skip |

## Q. Quality Gates

| Gate | Command | Result |
|---|---|---|
| Full PHP suite | `php artisan test --compact` | 366 total / 365 passed / 1 skipped / 1152 assertions / 2 pre-existing warnings |
| Concurrency tests | claim + retry concurrency files | 2 passed / 22 assertions |
| Pint | `vendor/bin/pint --test --format agent` | passed |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=1G` | 0 errors |
| Python worker suite | `worker/.venv/Scripts/python -m pytest worker/tests -q` | 36 passed |
| Live Redis | real `queue:work redis` | 3 jobs consumed/completed |
| Real worker | real `large-v3` inference | 3 successful runs |

## R. Defects / Corrections

No production defect was found. No production code was changed during P3-008.
One hidden, env-guarded verification harness command
(`app/Console/Commands/Phase3IntegrationVerification.php`) was added solely to
drive/observe the integration run; it is disabled unless `RTFTT_P3008_RUN=1`,
is `protected $hidden = true`, and is not production behavior.

## S. Honest Scoping

- Live Redis was provisioned locally (portable Redis 3.0.504) for this gate; it
  is an environment provisioning action, not a repository or system change.
- The real worker ran on CPU with `int8`; `large-v3` was the canonical model.
- Multi-language/code-switching assertions are carried by deterministic
  normalized-result fixtures plus the real English smoke run, per the accepted
  evidence model; a single short clip was not used to assert multi-language
  output.
- No claim is made that live Redis or real inference runs in normal CI; the
  repository's automated suite remains mock/database-queue based, and this gate
  is environment-dependent as designed.
