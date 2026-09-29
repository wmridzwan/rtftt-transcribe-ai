# DISC-A02 — Current Translation Boundary (FACT-based)

Research date: 2026-09-27. Source: current repo. No code changed.

## Files/sequence (FACT)

- Port: `app/Translation/TranslationProvider.php:13` `translate(Invocation):Result`, no mutation.
- Input: `TranslationInvocation.php:15` text-only (ids + `TranslationSegmentData` list, unique indices); HTTP DTO `TranslationRequest.php:11` (`request_id,transcription_id,translation_id,target,segments,contract_version`).
- Impl: `HttpTranslationProvider.php:26` sole impl, POST `{base}/translate`; worker `worker/translation.py:93` NLLB (`facebook/nllb-200-distilled-600M`, `ms→zsm_Latn`, per-segment `forced_bos`, `max_length=512`), endpoint `worker/main.py:197`.
- Validator: `TranslationResponseValidator.php:22` strict — count/index/timestamp±0.0005s/source-echo must match; alignment copied from invocation `:159`, never provider.
- Result: `TranslationResult.php:12` (target, fullText, segments, provider, model); errors via `TranslationWorkerError.php` → `TranslationFailure.php:12` (authoritative; worker flag ignored `:193`).
- Writer: `TranslationResultWriter.php:30` atomic/idempotent txn, lifecycle-gated, alignment from DB rows `:156`.
- Lifecycle: `TranslationLifecycle.php:18` `pending→queued→translating→completed/failed`, `failed→queued` manual only; `TranslationQueueConfig.php:15` invariant provider<job<retry_after, `tries=1`.
- Orchestration: `TranslationOrchestrator.php:48` (completed-only gate, single-row converge, `failed→retry`); `TranslationDispatcher.php:26` (`translation` queue + `dispatched_at`); `TranslationRetry.php:51` (manual, fresh token, CAS); `StaleTranslationAttemptRecovery.php:41` (`translating` older than timeout+60s → `failed/PROVIDER_TIMEOUT`, token-fenced); `Jobs/ProcessTranslation.php:65` claim by token `:209`, `failed():284`.
- Config/binding: `config/translation.php:25,39,58` (provider/model, queue, timeouts, `contract_version=1.0`); `AppServiceProvider.php:43` binds Http impl. Hosted adapter needs separate ADR (`config/translation.php:14` comment — FACT).
- Revision interaction: reads machine `transcription.segments` only (`sourceSegments():193`); edits never retrigger — only `invalidate()` additive (`stale_at/reason/causing_revision_id`, never cleared): `TranslationInvalidationPolicy.php:27`, `EloquentTranslationStalenessWriter.php:35`, `RevisionService.php:157`; models `Translation.php:97`, `TranslationSegment.php:23`.

## Domain vs implementation

Contract: provider-neutral text-only translate, segment-aligned, no source mutation; taxonomy authoritative; alignment from invocation/DB. Implementation: single HTTP→NLLB path; identity `self-hosted`/model recorded on completed row.

## Narrowest boundary (finding)

`TranslationProvider::translate()` + `TranslationRequest::toArray()` + `TranslationResponseValidator::fromArray()` + error-code map + Result/SegmentData shape. Swapping model/tokenizer/batching/transport inside Http provider + `worker/translation.py` preserves semantics iff alignment, error-code, `contract_version=1.0` contracts hold.

## FACT / INFERENCE / UNKNOWN

- FACT: `tries=1`, no auto-retry; completed rows immutable; stale flag additive.
- INFERENCE (strong): boundary deliberately text-only — no media path crosses translation.
- UNKNOWN: scheduler wiring for stale-recovery, controller/auth for request/retry endpoints, migration unique index, worker auth verify details.
