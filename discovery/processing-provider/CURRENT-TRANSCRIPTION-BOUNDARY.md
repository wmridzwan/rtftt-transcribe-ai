# DISC-A01 — Current Transcription Boundary (FACT-based)

Research date: 2026-09-27. Source: current repo `HEAD`. No code changed.

## Sequence (FACT, with locations)

1. Request: `app/Actions/TranscriptionOrchestrator.php:31` `Draft→Queued`, reuse-or-create `ProcessingJob`; `:93` dispatch IDs-only job.
2. Queue: `app/Jobs/ProcessTranscription.php:38,73`, `tries=1:46`, `timeout=jobTimeout:53,70`; queue `transcription`, Redis (`config/queue.php:67`, `retry_after=420`).
3. Claim+prepare: CAS `Queued→Running`, `Draft→Queued→Preparing→Transcribing`; `hasPhysicalFile()` guard.
4. Provider call: `app/Transcription/TranscriptionProvider.php:11` `transcribe(Invocation): NormalizedTranscript`; sole impl `app/Transcription/HttpTranscriptionProvider.php:22` POST `{worker_url}/transcribe` + Bearer.
5. Inputs: `TranscriptionInvocation.php:17` + `TranscriptionMedia.php` + `TranscriptionOptions.php` — IDs + storageKey only, no bytes cross the queue.
6. Worker: `worker/main.py:71` auth→resolve→ffmpeg→whisper; `worker/transcription.py:120` faster-whisper + per-segment `detect_language`; `worker/config.py:14` `MODEL_NAME=large-v3`.
7. Envelope: `WorkerRequest.php:27`, `WorkerResponseValidator.php:20`, `WorkerContract.php:12` (`1.0`); error `{error_code,retryable,safe_message,request_id}`.
8. Normalize: `NormalizedTranscript.php:12` + no-speech invariants; worker `retryable` advisory-only (`HttpTranscriptionProvider:45`).
9. Persist: `app/Actions/TranscriptionResultWriter.php:36` one txn, stale-authority no-op `:77`, `delete+recreate` segments ordered.
10. Taxonomy: `TranscriptionFailure.php:12`, 12 codes, retryable only 4 (`:30`); `TranscriptionLifecycle.php:23`.
11. Retry: `TranscriptionRetry.php:40,57` manual-only `Failed→Queued` CAS + new attempt; `StaleTranscriptionAttemptRecovery.php:46` `running→failed(WorkerTimeout)` at timeout+60.
12. Config: `config/transcription.php:3,21,36` (`worker_url/token`, `model=large-v3`, timeouts); `TranscriptionQueueConfig.php:22` invariant provider<job<retry_after; binding `TranscriptionServiceProvider.php:13`.
13. Demo stub: `routes/web.php:33` `DemoTranscriptionController@store` completes synchronously, bypasses queue (FACT).

## Domain vs implementation

- Contract (separable): iface + Invocation/Media/Options + NormalizedTranscript + Failure::isRetryable + lifecycle + writer atomicity + manual retry + IDs-only payload.
- Implementation (coupled): Http provider + Worker DTOs + ServiceProvider wiring + `worker/*.py` (FastAPI/faster-whisper/FFmpeg/shared-FS/Bearer). Coupling: baseURL/token/timeout read inline, `model` string persisted by writer, `contract_version=1.0` triplicated (PHP const, `transcription.php:8`, `worker/config.py:17`).

## Narrowest boundary (finding, not code)

Freeze iface + Invocation + NormalizedTranscript + Failure + writer/retry; isolate only construction+mapping behind `TranscriptionServiceProvider::register()` — a new `TranscriptionProvider` impl swaps there with zero domain change.

## FACT / INFERENCE / UNKNOWN

- FACT: above trace. Demo route never dispatches queue; retry manual-only; worker flag never drives retry.
- INFERENCE: real user trigger likely Livewire/action outside `routes/*.php` (only tests/console/harness call `Orchestrator::request`).
- UNKNOWN: prod `QUEUE_CONNECTION`, `worker_url/token`, `SHARED_MEDIA_ROOT` mount wiring; prod UI entry point.
