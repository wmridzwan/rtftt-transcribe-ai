# Phase 3 — Real Transcription Engine: Canonical Specification

Date: 2026-09-17
Status: APPROVED BY HPO — governance baseline
Authority: ADR-017 in DECISIONS.md

---

## Phase Boundary Amendment

Phase 3 is the complete Real Transcription Engine. The earlier decomposition
(Phase 3 = FFmpeg/FFprobe, Phase 4 = faster-whisper worker, Phase 5 =
Laravel-worker integration) is superseded by ADR-017.

Phase 3 delivers:

- provider-neutral transcription domain contract;
- internal authenticated Python worker (faster-whisper + FFmpeg);
- private/internal HTTP transport;
- Redis-backed asynchronous queue processing;
- transcript persistence;
- segment persistence with per-segment language;
- multilingual/code-switching support (BM, English, Chinese, Tamil);
- retry, recovery, and failure hardening;
- real end-to-end integration verification.

## Owner Decisions (OD-01 through OD-12)

### OD-01 — Segment Language Granularity

One dominant/best-supported language per segment. Word/span-level language
tagging is outside Phase 3. Minimum segment contract: start_seconds,
end_seconds, text, language.

### OD-02 — faster-whisper Model

Preferred: turbo. Mandatory benchmark gate: turbo vs large-v3 before
P3-003 finalization. Canonical rule: turbo remains preferred unless
benchmark evidence shows materially unacceptable degradation for RTFTT
workloads.

### OD-03 — Laravel ↔ Python Transport

Private/internal HTTP. Python runtime isolated from PHP/Laravel runtime.

### OD-04 — Language Identifier Standard

BCP 47-compatible. Required vocabulary: ms, en, zh, ta, und.

### OD-05 — Phase Boundary Amendment

Phase 3 includes the complete real transcription-engine path defined in
this specification. The older Phase 3/4/5 split is superseded.

### OD-06 — Queue Backend

Redis without Horizon initially.

### OD-07 — Worker Media Access

Shared private filesystem. Laravel passes an opaque private media reference.
Must not POST the entire 500 MiB media object into Python memory.

### OD-08 — Prepared Audio Retention

Configurable. Default: ephemeral. Supported: durable_until_terminal.

### OD-09 — Internal Worker Authentication

Private/internal network + shared-secret bearer token from env/config.
mTLS not required for initial Phase 3.

### OD-10 — Batch Execution Model

Up to three sequential implementation tasks per OpenCode batch, followed by
one independent Claude review. IMPLEMENTED ≠ VERIFIED.

### OD-11 — Accuracy Policy

No mandatory WER threshold. BM/Tamil may differ from English/Chinese.

### OD-12 — Configuration Boundary

Phase 3 configuration centralized in a transcription-oriented config
surface. Secrets from environment. No committed secrets.

## Lifecycle Reconciliation

Phase 3 reuses existing enums:

```
TranscriptionStatus: draft, queued, preparing, transcribing, completed,
                     failed, cancelled

ProcessingStatus:   queued, running, completed, failed, cancelled

ProcessingStage:    upload, probe, extract_audio, transcribe, finalize
```

Phase 3 must not introduce parallel lifecycle vocabularies.

Canonical transcription lifecycle:

```
draft → queued → preparing → transcribing → completed
                                             → failed
                                             → cancelled
```

## Provider-Neutral Domain

Application/domain code may know:

```
TranscriptionOptions
TranscriptionMedia
TranscriptionProvider
NormalizedTranscript
TranscriptSegmentData
LanguageIdentifier
provider-neutral failures
```

Must not depend on:

```
faster-whisper Python classes
Whisper Segment objects
FFmpeg process structures
HTTP response implementation details
Python runtime-specific types
```

## Internal HTTP Worker Contract

Minimum request context: request_id, transcription_id, attempt_id,
media_reference, requested_language, contract_version.

Model selection is server/config controlled. Users may not choose
untrusted model paths or filesystem paths.

Minimum success response: contract_version, text, language,
duration_seconds, speech_detected, segments.

Worker failures use a stable error envelope: error_code, retryable,
safe_message, request_id.

## Shared Filesystem Contract

Laravel and Python worker share access to private durable media storage.

Laravel sends server-generated relative media reference (e.g.,
`opaque/storage/key.ext`), not arbitrary absolute paths.

Worker rules:

- resolve only beneath configured shared-media root
- reject absolute paths
- reject traversal
- reject path escape
- never accept browser/user-provided raw path directly

Source media mount: read-only for worker (where feasible).
Worker temp area: separate writable private location.

## FFmpeg Trust Boundary

Uploaded content remains untrusted even after Phase 2 validation.

Canonical flow:

```
private original media
  ↓
worker-controlled temporary directory
  ↓
FFmpeg preparation
  ↓
normalized private audio (16 kHz, mono, PCM 16-bit)
  ↓
faster-whisper
  ↓
cleanup according to retention policy
```

Use argument-array process execution. Never build shell commands using
interpolated user-controlled strings.

Output size must be estimated from audio duration × sample rate ×
channels × bytes per sample (~32,000 bytes/second for default profile),
not compressed input size.

## Multi-Language / Code-Switching Contract

RTFTT media may contain mixed Bahasa Melayu, English, Chinese, and Tamil
within a single recording. The architecture must never assume
one recording = one language.

Transcript-level language is summary metadata.
Segment-level language is the canonical multilingual unit.

Worker must derive or retain the best-supported language decision
associated with each normalized segment. Must not simply copy transcript
dominant language into every segment.

If defensible segment-level language cannot be derived: language = und.

## Benchmark Gate

Before P3-003 is finalized, benchmark turbo vs large-v3.

Representative workload: BM, English, Chinese, Tamil, mixed-language/
code-switching samples.

Record per sample/model:

- sample ID/description
- sample duration
- hardware (CPU/GPU, RAM/VRAM)
- model, device, compute_type
- processing duration
- real-time factor
- qualitative transcript observation
- BM/English/Chinese/Tamil/code-switching observations
- operational/resource notes

Record reproducibility: faster-whisper version, model identifier,
inference configuration, FFmpeg profile, benchmark date/environment.

Privacy: representative user media must not be committed unless
explicitly approved.

Decision rule: turbo preferred unless evidence shows materially
unacceptable degradation. If acceptable, record turbo as canonical
initial model. If not, STOP P3-003 finalization and surface evidence
to HPO.

## Queue Architecture

Canonical backend: Redis. No Horizon initially.

Queue payload: small identifiers only (transcription_id). Must not
serialize 500 MiB media, binary buffers, provider instances, or
Python objects. Job must reload canonical state from database.

Duplicate delivery must not create duplicate logical transcription,
duplicate inference, duplicate segments, or state regression.

## Persistence Contract

Canonical ordering:

```
receive valid normalized result
  ↓
begin persistence boundary
  ↓
persist transcript semantic data
  ↓
persist all required segments
  ↓
validate required invariants
  ↓
commit persistence
  ↓
mark transcription completed
```

Never mark completed before all segments succeed.

Segment persistence: (transcription_id, segment_index) uniqueness.
Retry cannot produce duplicate segments.

## Failure Taxonomy

At minimum: MEDIA_MISSING, MEDIA_REJECTED, WORKER_UNAVAILABLE,
WORKER_AUTH_FAILED, WORKER_TIMEOUT, WORKER_SATURATED, FFMPEG_FAILED,
RESOURCE_EXHAUSTED, INVALID_WORKER_RESPONSE, PROCESSING_FAILED,
PERSISTENCE_FAILED, CONFIGURATION_ERROR.

Each category: retryable?, user-safe message class, internal
diagnostic handling, terminal/recoverable semantics.

Retries: bounded, backed off, idempotent, correlated to same logical
transcription. No infinite retries. No busy-loop.

## Prepared Audio Policy

Configuration: prepared_audio_retention.

Default: ephemeral (deleted after successful inference, worker failure,
or terminal processing outcome).

Optional: durable_until_terminal (retained for logical transcription
lifetime, removed at terminal-retention boundary).

## Completion Gate

Phase 3 is complete when:

1. Phase 3 implementation was explicitly authorized.
2. Batch 1 independently VERIFIED.
3. Batch 2 independently VERIFIED.
4. Batch 3 independently VERIFIED.
5. P3-001 through P3-008 meet approved acceptance criteria.
6. Turbo vs large-v3 benchmark evidence exists.
7. Canonical initial model selection durably recorded.
8. Redis queue path works.
9. Internal authenticated worker works.
10. Shared private filesystem media access works.
11. Real FFmpeg preparation works.
12. Real faster-whisper inference works.
13. Original media remains unchanged/private.
14. Default prepared-audio retention is ephemeral.
15. Transcript/segment persistence is atomic enough to prevent false
    completion.
16. Duplicate queue delivery is safe.
17. Retry is bounded and idempotent.
18. Terminal failure is deterministic.
19. Cross-user isolation intact.
20. Phase 1/2 regression verification passes.
21. No translation functionality introduced.
22. Final Claude review returns VERIFIED.
23. HPO accepts final Phase 3 closure.

## Canonical Phase Gate Statement

> A user can take a real, privately stored supported media file, request
> transcription, and have RTFTT asynchronously process it through Redis,
> an authenticated internal Python service, shared private storage,
> FFmpeg, and the approved self-hosted faster-whisper model; receive and
> persist a genuine multilingual transcript with dominant transcript
> language and one best-supported language identifier per segment; safely
> survive duplicate delivery, retry and failure conditions; preserve
> ownership and privacy; and retrieve the resulting transcript through
> the existing authorized application surface without regressing completed
> Phase 1 or Phase 2 contracts.

## Explicit Non-Scope

Phase 3 does not include:

- translation
- human transcript editing
- subtitle/export workflows
- speaker diarization
- word/span-level language tagging
- hosted ASR provider fallback
- multi-provider routing/fallback registry
- Horizon
- public Python worker endpoint
- permanent public prepared audio
- full transcript UI redesign
- minimum WER SLA
- real-time/live transcription
- streaming partial transcript UX

## Reference

ADR-017 in DECISIONS.md. P3-001 through P3-008 in tasks/.
`plan-phase3-media-processing.md`. `architecture.md`.
