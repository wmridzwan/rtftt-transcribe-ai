# P3-003 — Internal Python / FFmpeg / faster-whisper Provider

## Status

REVIEW

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: Claude Code

## Review Verdict History

Cycle 1 = CHANGES_REQUESTED (`reviews/PHASE3-BATCH1-independent-review.md`)
Cycle 2 = CHANGES_REQUESTED (`reviews/PHASE3-BATCH1-cycle2-independent-review.md`)

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Prerequisites

- P3-001 (Transcription Domain Contract) — complete
- P3-002 (Provider + Worker Transport Contract) — complete
- Turbo vs Large-v3 Benchmark Gate — evidence recorded

## Objective

Implement the first real transcription provider: internal Python HTTP service with FFmpeg preparation and self-hosted faster-whisper inference.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- P3-001, P3-002 domain and transport contracts
- Benchmark gate evidence (turbo vs large-v3)

## Scope

Implement:

- Python internal HTTP service (FastAPI or equivalent)
- Bearer token authentication
- Shared-filesystem media resolution (reject absolute paths, reject traversal)
- FFmpeg preparation (16 kHz, mono, PCM 16-bit)
- Selected faster-whisper model (per benchmark gate outcome)
- Multilingual inference with code-switching support
- Segment-language normalization (one dominant language per segment)
- No-speech handling (speech_detected=false, segments=[])
- Worker response contract (versioned JSON)
- Laravel HTTP provider adapter (implements TranscriptionProvider)
- Temporary media cleanup (ephemeral default)
- Cross-runtime observability (structured logs)

## Out of Scope

- Queue infrastructure (P3-006)
- Transcript persistence (P3-004)
- Segment persistence (P3-005)
- Failure/retry hardening (P3-007)

## Dependencies

- P3-001, P3-002 — domain and transport contracts
- Benchmark gate — model selection

## Acceptance Criteria

1. Real internal worker callable.
2. Selected model matches benchmark outcome.
3. Worker is authenticated (bearer token).
4. Public access is not the default deployment.
5. Media reference cannot escape configured storage root.
6. Original media remains unchanged.
7. FFmpeg preparation succeeds for representative supported media.
8. Prepared audio is private.
9. Default retention is ephemeral.
10. Retention can be configured.
11. Real faster-whisper inference succeeds.
12. Dominant transcript language returned.
13. Segment languages returned where defensible.
14. `und` returned where language cannot be supported.
15. Segment languages are not fabricated from transcript-level language.
16. No-speech result works.
17. Worker timeout works.
18. Worker errors become provider-neutral Laravel failures.
19. Malformed worker response rejected.
20. Cleanup occurs on success/failure/exception.
21. Observability events emitted safely.
22. All tests pass; Pint clean; PHPStan 0 errors; Python tests pass.

## Review

Review File: reviews/PHASE3-BATCH1-cycle2-independent-review.md
Review Status: CHANGES_REQUESTED (batch cycle 2)

Findings status (independently reviewed in cycle 2):
- B1: STILL OPEN (BLOCKER) — Python installation failed in current
  environment. Benchmark gate cannot be executed. HPO decision required on
  how to proceed (alternative environment, manual Python install, or gate
  waiver). DECISION-P3-BENCHMARK-GATE-001 remains OPEN. This should be
  escalated to the Human Product Owner now rather than consuming a third
  autonomous cycle, since it is not resolvable by further OpenCode code
  changes.
- H1: RESOLVED (independently confirmed) — worker/config.py now uses
  RTFTT_TRANSCRIPTION_WORKER_TOKEN.
- H2: RESOLVED (independently confirmed) — worker/transcription.py accepts
  and forwards requested_language to model.transcribe().
- H3: Test files created and read as correct, but NEW FINDING H4 (HIGH):
  worker/tests/conftest.py's sys.path insertion resolves to worker/ itself
  rather than the repository root, so `from worker.X import ...` (used by
  every new test file) likely raises ModuleNotFoundError under the natural
  `cd worker && pytest` invocation implied by worker/pytest.ini. Python is
  not installed in this environment (confirmed by both the implementer and
  this review), so the suite has never actually been executed by anyone.
  Fix the import-root computation and actually run pytest before claiming
  AC22 resolved.
- L3: RESOLVED (independently confirmed) — worker error messages no longer
  echo storage keys.
- L4: RESOLVED (independently confirmed) — Task notes correctly attribute
  files to P3-002.

Required changes: resolve H4 (fix conftest.py import path, then execute
pytest and record real results) and escalate B1 to the Human Product Owner.
See reviews/PHASE3-BATCH1-cycle2-independent-review.md for full detail.

### Correction Cycle 2 (Python retry, 2026-09-17)

Python is now installed on the Windows dev environment (the earlier winget
failure was caused by an installer verification prompt that went unnoticed,
per HPO). This cycle re-verified the environment and actually executed the
worker suite for the first time.

- H4: RESOLVED. `worker/tests/conftest.py` computed the repo root as
  `Path(__file__).parent.parent` (resolves to `worker/`, not its parent).
  Fixed to `Path(__file__).resolve().parent.parent.parent`. Verified by
  running `python -m pytest tests -v` from `worker/` with the documented
  invocation: all 22 (now 25, see below) tests collect and execute.
- Running the suite for real (not previously possible) surfaced two genuine
  defects that static review could not have caught, both fixed and now
  covered by tests:
  1. `worker/media.py::resolve_media_path` relied on `os.path.isabs()`,
     which returns `False` on Windows for a POSIX-style leading-slash path
     with no drive letter (`/etc/passwd`) — it fell through to the
     escape-root check instead of being flagged as absolute. Added explicit
     leading `/`/`\` checks so rejection does not depend on host-OS
     `isabs()` quirks. Also hardened the root-containment check from a
     naive string-prefix comparison (`str.startswith`, vulnerable to a
     `media` vs `media_backup` sibling-directory collision) to a proper
     `Path.parents` containment check.
  2. `worker/transcription.py::get_model()` unconditionally `import torch`
     when `RTFTT_WHISPER_DEVICE=auto` (the documented default) to detect
     CUDA. `torch` is not declared in `worker/requirements.txt` and is not
     installed — every existing test mocked `get_model` entirely, so this
     was never exercised until real execution. Replaced with
     `ctranslate2.get_cuda_device_count()` (ctranslate2 is already a direct
     dependency of faster-whisper); added `ctranslate2` to
     `requirements.txt` as a direct dependency. Added
     `TestGetModel` (3 new tests, real device/compute_type resolution
     logic, `WhisperModel`/`ctranslate2` mocked to avoid a network/model
     dependency in unit tests) confirming: `auto` + no CUDA → `cpu` +
     `int8` (float16 downgraded, matching ctranslate2's CPU-supported
     compute types); explicit `cpu` also downgrades `float16`→`int8`;
     model is cached (constructed once).
- Actual command and result:
  `worker/.venv/Scripts/python.exe -m pytest tests -v` (from `worker/`) →
  **25 passed, 0 failed, 0 skipped, 0.60s** (22 pre-existing + 3 new
  `TestGetModel` tests; the two real bugs above were caught while making
  the pre-existing `test_media.py` failures pass, not by new test count
  alone — see `reviews/` for the cycle-3 review of this diff).
- Minimal faster-whisper runtime check (separate from the mandatory
  benchmark; distinct from unit tests, run manually, not committed):
  imported `faster_whisper`/`ctranslate2` directly (no error), confirmed
  device resolves to `cpu` (no CUDA device on this machine — Intel UHD
  integrated graphics only, `ctranslate2.get_cuda_device_count()` → 0),
  loaded the real `turbo` model via `get_model()`, and ran
  `model.transcribe()` on a synthetic 3-second tone WAV end-to-end. See
  `BENCHMARK-GATE-EVIDENCE.md` for exact versions/timings.
- L5: RESOLVED. `TranscriptionMedia.php`'s drive-letter regex tightened to
  `/\A[a-zA-Z]:[\\\/]/i` (was backslash-only), rejecting both `C:\...` and
  `C:/...`. Two new tests added to
  `tests/Unit/Transcription/TranscriptionMediaTest.php` exercising each
  form explicitly (file now has 9 tests, not 7 or the previously-claimed
  8). `php artisan test --filter=TranscriptionMediaTest` → 9 passed, 19
  assertions.
- B1 (benchmark gate): STILL BLOCKED, but for a different, more specific
  reason than before. Python/faster-whisper/ctranslate2 are now verified
  working end-to-end on this machine (see above). However, no
  representative multilingual benchmark media (Bahasa Melayu, English,
  Chinese, Tamil, code-switching) exists anywhere in this repository or in
  a `benchmark-media/` directory. Per the retry instructions, this is a
  STOP-and-report condition, not something to route around with
  synthetic/fabricated samples. See `BENCHMARK-GATE-EVIDENCE.md` and
  `DECISION_QUEUE.md` for the exact blocker. AC2 ("Selected model matches
  benchmark outcome") remains unmet; `turbo` remains the configured
  default, unproven against RTFTT's actual multilingual workload.
- PHP regression check after all corrections: `php artisan test --compact`
  → 290 passed, 1 skipped, 866 assertions (was 288/862 at cycle 2; +2
  tests/+4 assertions is exactly the two new L5 tests). Pint clean.
  PHPStan 0 errors.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.

## Implementation Notes

### PHP Files (created by P3-002, not P3-003)

- `app/Providers/TranscriptionServiceProvider.php` — container binding (P3-002)
- `app/Transcription/HttpTranscriptionProvider.php` — HTTP provider adapter (P3-002)

### Python Files

- `worker/__init__.py` — package init
- `worker/main.py` — FastAPI application with /transcribe and /health endpoints
- `worker/config.py` — environment-backed configuration
- `worker/auth.py` — bearer token authentication middleware
- `worker/media.py` — shared filesystem media access (rejects traversal)
- `worker/ffmpeg.py` — FFmpeg audio preparation (16kHz, mono, PCM 16-bit)
- `worker/transcription.py` — faster-whisper inference with lazy model loading
- `worker/requirements.txt` — Python dependencies

### Configuration

- `config/transcription.php` — worker URL, token, timeout, retention, shared root (created by P3-002)
- Environment variables: RTFTT_TRANSCRIPTION_WORKER_URL, RTFTT_TRANSCRIPTION_WORKER_TOKEN, RTFTT_WHISPER_MODEL, RTFTT_WHISPER_DEVICE, RTFTT_WHISPER_COMPUTE_TYPE, RTFTT_SHARED_MEDIA_ROOT, RTFTT_PREPARED_AUDIO_RETENTION

### Worker Behavior

- Bearer token authentication (env-backed)
- Shared-filesystem media resolution (rejects absolute paths, traversal)
- FFmpeg preparation: 16kHz, mono, PCM 16-bit WAV
- faster-whisper inference (model configurable, default: turbo)
- Segment-level language normalization (not copied from transcript)
- No-speech: speech_detected=false, text="", language=und, segments=[]
- Prepared audio cleanup (ephemeral default)
- Structured logging (request_id, transcription_id, attempt_id, processing_seconds)
- No bearer tokens, secrets, or unsafe paths in logs

### Security

- Original media unchanged
- Traversal rejected
- Bearer auth enforced
- Temp artifacts private
- Cleanup verified (ephemeral retention)
- Secrets not leaked
