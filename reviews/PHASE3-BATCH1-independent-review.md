# REVIEW - Phase 3 Batch 1 - P3-001 / P3-002 / P3-003

## Review Status

CHANGES_REQUESTED

## Task

Task Files:
- tasks/P3-001-transcription-domain-contract.md
- tasks/P3-002-provider-worker-transport-contract.md
- tasks/P3-003-python-ffmpeg-fasterwhisper-provider.md

Implementation Owner: OpenCode
Reviewer: Claude Code (independent, first review pass for this batch)

This is a batch review under the Phase 3 Batch-Execution Exception
(`.ai/guidelines/orchestration-policy.md`). One overall batch verdict is
recorded; per-task findings are also recorded below.

## Review Scope

Independently reconstructed from Git history and current repository state
(no reliance on chat/agent summaries):

- `git show --stat` / full diffs for 0e8a926 (P3-001), 0efd13e (P3-002),
  4d3b4ff (P3-003), 347b2da (benchmark gate), a6deb24 (REVIEW handoff)
- Full contents of every file created under `app/Transcription/`,
  `app/Providers/TranscriptionServiceProvider.php`, `config/transcription.php`,
  `worker/`, and `tests/Unit/Transcription/`
- `.ai/guidelines/orchestration-policy.md`, `DECISION_QUEUE.md`,
  `BENCHMARK-GATE-EVIDENCE.md`, `CURRENT_STATE.md`
- Independently executed commands (see Test Verification)

## Findings

### BLOCKER

**B1 — P3-003 proceeded without the Batch 1 benchmark gate being recorded, contrary to the batch's own governing sequencing rule.**

`.ai/guidelines/orchestration-policy.md` ("Phase 3 Batch-Execution Exception")
defines Batch 1's sequence explicitly as:

```
P3-001 → P3-002 → Benchmark gate recorded → P3-003 begins
```

and separately: *"If an earlier task develops a known defect that
invalidates the next task's dependency contract: STOP progression... Surface
the blocker to HPO."* P3-003's own task file lists as a Prerequisite:
*"Turbo vs Large-v3 Benchmark Gate — evidence recorded."*

`BENCHMARK-GATE-EVIDENCE.md` (commit 347b2da, same day, ~4 minutes before the
P3-003 commit) explicitly states `Status: DEFERRED — Python not installed on
current system`, with every environment/evidence field left as `TBD` and no
sample data of any kind. No benchmark was ever run. Despite this, commit
4d3b4ff implemented P3-003 in full (Python worker, FFmpeg pipeline,
faster-whisper integration hardcoded to the `turbo` default) four minutes
later, and the batch was then handed to REVIEW.

This is not a documentation gap — it is proceeding past an explicit,
repository-governed sequencing gate without the required evidence and without
surfacing the blocker to the Human Product Owner, exactly the situation the
policy tells the batch to stop for. `turbo` may well be the right default,
but that decision must be recorded as satisfying the gate (or the gate
explicitly waived by HPO), not silently bypassed by proceeding to
implementation.

### HIGH

**H1 — Bearer-token environment variable name mismatch breaks worker authentication end-to-end.**

`config/transcription.php:6` reads the token Laravel sends as
`env('RTFTT_TRANSCRIPTION_WORKER_TOKEN', '')`, and
`HttpTranscriptionProvider::transcribe()` sends it as
`Authorization: Bearer {$this->bearerToken}`. The Python worker
(`worker/config.py:7`) validates incoming requests against
`os.environ.get("RTFTT_WORKER_TOKEN", "")` (`worker/auth.py:15-22`). These are
two different environment variable names for what must be the same secret.
P3-003's own "Environment variables" documentation list only names
`RTFTT_TRANSCRIPTION_WORKER_TOKEN` — `RTFTT_WORKER_TOKEN` appears nowhere in
any task or config documentation. As configured and documented, every real
call from Laravel to the worker will receive `403 Invalid token` (or `500
Worker token not configured` if the undocumented variable is never set),
directly contradicting P3-003 AC1 ("Real internal worker callable") and AC3
("Worker is authenticated (bearer token)").

**H2 — `requested_language` is threaded through the entire contract but is never applied by the worker.**

`TranscriptionOptions::$requestedLanguage` → `WorkerRequest::$requestedLanguage`
→ the worker's `TranscriptionRequest.requested_language` Pydantic field is
fully wired end-to-end, but `worker/main.py`'s `/transcribe` handler never
reads `request.requested_language` at all, and `transcribe_audio()`
(`worker/transcription.py:42`) unconditionally calls
`model.transcribe(str(audio_path), language=None, ...)`. The explicit
language hint that P3-001's domain contract establishes ("Explicit requested
language remains a hint, not a guarantee") has no effect whatsoever — it is
parsed and silently discarded on every request. No test (PHP or Python)
exercises this path, so the gap was not caught.

**H3 — Zero automated Python test coverage, despite P3-003's own acceptance criteria requiring it.**

P3-003 AC22 states: *"All tests pass; Pint clean; PHPStan 0 errors; Python
tests pass."* There is no test file anywhere under `worker/` (confirmed via
repository-wide search) — no coverage for path-traversal/absolute-path
rejection (`worker/media.py`), FFmpeg invocation and failure/timeout handling
(`worker/ffmpeg.py`), bearer-token verification (`worker/auth.py`), or
transcription normalization / no-speech handling (`worker/transcription.py`).
This is newly written, security- and correctness-sensitive code (the
traversal guard and subprocess invocation in particular) with no regression
safety net. The task's own "Quality Results" section is silent on Python
tests despite the explicit AC, and the project's test-enforcement rule
("Test every code change by adding or updating a test") was not followed for
this half of the batch.

### MEDIUM

**M1 — `HttpTranscriptionProvider` hardcodes `transcriptionId: 0` / `attemptId: 0`; no caller can ever supply real values.**

`app/Transcription/HttpTranscriptionProvider.php:27-28` hardcodes
`transcriptionId: 0, // Will be set by caller` and `attemptId: 0, // Will be
set by caller`, but `TranscriptionProvider::transcribe(TranscriptionMedia
$media, TranscriptionOptions $options)` has no parameter through which any
consumer could ever pass real identifiers — the comment describes a
capability the interface does not provide. Every real invocation through this
adapter will report `transcription_id=0, attempt_id=0` to the worker and in
its structured logs (`worker/main.py:76-83`), defeating the
retry/attempt-identity traceability that P3-001 (AC12/13) and P3-003's
"Structured logging (request_id, transcription_id, attempt_id,
processing_seconds)" claim to deliver. This will need an interface change in
a later task (likely P3-004/P3-006); flagging now while the interface is
still uncommitted-to by other consumers.

**M2 — P3-001's own "Required Tests" for lifecycle/identity/ownership were never implemented.**

P3-001 lists as Required Tests: *"Valid lifecycle transitions, Invalid
lifecycle transitions, ... Retry identity preservation, Retranscription
identity independence, Ownership isolation."* The delivered diff (0e8a926)
contains only DTOs and enums (`LanguageIdentifier`, `TranscriptSegmentData`,
`NormalizedTranscript`, `TranscriptionOptions`, `TranscriptionMedia`,
`TranscriptionFailure`, `TranscriptionException`, `TranscriptionProvider`) —
none of the six test files created contain any test for lifecycle
transitions, retry-vs-retranscription identity distinction, or ownership
invariants, and no code in the diff implements those concepts (they exist
only as prose in AC 11-13 / docstrings). Acceptance Criteria 11 ("Ownership
invariants defined"), 12 ("Retry and retranscription are distinct
concepts"), and 13 ("Logical and attempt identities defined") are asserted
but not actually encoded or verified anywhere in this contract.

**M3 — `HttpTranscriptionProvider` itself (the real HTTP/auth/error-mapping boundary) has zero test coverage.**

`tests/Unit/Transcription/WorkerTransportTest.php` only exercises the DTOs
(`WorkerRequest`, `WorkerResponse`) and `WorkerResponseValidator`. No test
constructs an `HttpTranscriptionProvider` and asserts on the request headers
it sends, the URL it calls, its handling of a non-2xx HTTP response without
an error envelope, or its timeout behavior. This is the actual code path
that will run in production; it is currently unverified by any automated
test (`Http::fake()` would be sufficient and is idiomatic for this
codebase's stack).

### LOW

**L1 — `TranscriptionMedia`'s absolute-path guard does not catch Windows drive-letter paths.**

`app/Transcription/TranscriptionMedia.php:28` only rejects storage keys
starting with `/` or `\`; a value like `C:\Windows\file.mp3` passes this
constructor unrejected. The Python worker independently re-validates with
`os.path.isabs()` (`worker/media.py:24`), which does catch this case, so the
gap is not currently exploitable end-to-end — but it is an incomplete
defense-in-depth check on a security-relevant boundary type, worth tightening
given this is a Windows development/deployment environment.

**L2 — `config/transcription.php`'s `timeout_seconds` is unused; the real timeout is hardcoded.**

`config/transcription.php:10` defines `'timeout_seconds' => 300`, but
`HttpTranscriptionProvider::transcribe()` calls `->timeout(300)` as a literal
rather than reading `config('transcription.timeout_seconds')`. No functional
impact today (values match), but the config value will silently stop doing
anything if ever changed.

**L3 — Worker error messages echo the raw storage key into the "safe" message field.**

`worker/media.py` raises `MediaAccessError` messages such as `"Absolute path
rejected: {storage_key}"` and `"Path traversal rejected: {storage_key}"`,
which flow unmodified into `error_response(..., safe_message=str(e), ...)`
and then into `WorkerErrorResponse::$safeMessage` /
`TranscriptionException::$message` on the Laravel side — a field intended to
be safe for eventual user-facing display. Storage keys are currently
server-generated (low sensitivity), but this should be tightened before this
message path is ever surfaced directly in UI.

**L4 — P3-003's task file re-lists files it did not change as if newly created.**

P3-003's "Implementation Notes → PHP Files" section lists
`app/Providers/TranscriptionServiceProvider.php` and
`app/Transcription/HttpTranscriptionProvider.php`, and its "Configuration"
section lists `config/transcription.php`, as though created by this task.
`git show --stat 4d3b4ff` shows none of these files were touched by the
P3-003 commit — they were created in P3-002 (0efd13e). Documentation-only;
no functional effect, but it overstates what P3-003 actually delivered.

## Acceptance Criteria Verification

### P3-001

- [x] 1. Provider-neutral domain contract exists in PHP.
- [x] 2. Existing lifecycle vocabulary reused (no new lifecycle code added; pre-existing enums untouched).
- [x] 3. Requested language nullable; null = auto-detect.
- [ ] 4. Explicit requested language remains a hint, not a guarantee — defined in the DTO, but see H2: the hint has no effect anywhere in the currently-implemented pipeline, so this cannot yet be verified as a working behavior, only as an unused field.
- [x] 5. Transcript detected_language means dominant language (documented, structurally represented).
- [x] 6. Segment language exists; one value per segment.
- [x] 7. `und` is a valid language value.
- [x] 8. Mixed-language segments supported (tested in NormalizedTranscriptTest/WorkerTransportTest).
- [x] 9. Timestamp invariants enforced and tested (TranscriptSegmentDataTest).
- [x] 10. No-speech distinct from failure (NormalizedTranscript::noSpeech, tested).
- [ ] 11. Ownership invariants defined — see M2, not implemented or tested in this diff.
- [ ] 12. Retry vs retranscription distinct — see M2, no code or test.
- [ ] 13. Logical/attempt identity defined — see M1/M2, fields exist but are not meaningfully populated or tested.
- [x] 14. No faster-whisper type leaks into PHP domain.
- [x] 15. No translation behavior introduced.
- [x] 16. Tests/Pint/PHPStan pass (independently reproduced, see below).

### P3-002

- [x] 1. Provider-neutral interface exists.
- [x] 2. Provider is container-resolvable (TranscriptionServiceProvider).
- [x] 3. Consumers can fake/mock it (interface-based).
- [x] 4. Media passed by reference, not binary.
- [x] 5. HTTP implementation hidden behind the interface.
- [x] 6. Worker contract versioned (WorkerContract::VERSION).
- [ ] 7. Bearer authentication configuration defined — the config exists, but see H1: it does not actually match the worker's expected variable, so the two ends of "configured authentication" do not currently agree.
- [x] 8. Invalid response rejected (WorkerResponseValidator, tested).
- [x] 9. Provider-neutral error taxonomy foundation exists.
- [x] 10. Mixed-language normalized result supported.
- [x] 11. No real worker execution in this task (correctly out of scope here).
- [x] 12. Tests/Pint/PHPStan pass.

### P3-003

- [ ] 1. Real internal worker callable — code exists and is independently runnable in principle, but see H1 (auth mismatch) and B1 (model selection not actually gated on recorded evidence); cannot be verified as working end-to-end as configured.
- [~] 2. Selected model matches benchmark outcome — no benchmark outcome exists to match against (B1); `turbo` was chosen as a bare default, not derived from evidence.
- [ ] 3. Worker is authenticated (bearer token) — see H1.
- [~] 4. Public access is not the default deployment — no deployment/binding config was introduced either way; not verifiable from this diff.
- [x] 5. Media reference cannot escape configured storage root (worker/media.py, traversal + realpath-prefix check).
- [x] 6. Original media remains unchanged (FFmpeg writes only to a separate prepared-audio path).
- [x] 7. FFmpeg preparation implemented for representative formats (argument-array invocation, no shell interpolation).
- [~] 8. Prepared audio is private — relies on OS/process default permissions; not explicitly enforced or tested.
- [x] 9. Default retention is ephemeral (deletes in `finally` when `PREPARED_AUDIO_RETENTION == "ephemeral"`).
- [x] 10. Retention configurable via `RTFTT_PREPARED_AUDIO_RETENTION`.
- [~] 11. Real faster-whisper inference — implemented, but see H3: entirely unverified by any automated test.
- [x] 12. Dominant transcript language returned.
- [x] 13. Segment languages returned per-segment (worker/transcription.py uses per-segment language, not copied — see L-level note that this still needs Python test coverage to prove it holds).
- [x] 14. `und` fallback present when language unavailable.
- [x] 15. Segment languages not fabricated from transcript-level language (falls back to `info.language`/`"und"` only when a segment lacks its own value; not unconditionally copied).
- [x] 16. No-speech result implemented (speech_detected=false path returns text="", language=und, segments=[]).
- [x] 17. Worker timeout handled (subprocess timeout → FfmpegError; HTTP-level timeout is the caller's `->timeout(300)`).
- [x] 18. Worker errors mapped to provider-neutral Laravel failures (WorkerErrorResponse::toFailure()).
- [x] 19. Malformed worker response rejected (WorkerResponseValidator, tested on the PHP side).
- [x] 20. Cleanup occurs on success/failure/exception (`finally` block in worker/main.py).
- [x] 21. Observability events emitted — structured logs exist, but see M1: `transcription_id`/`attempt_id` will always be logged as `0` through the current adapter, so correlation is currently non-functional.
- [ ] 22. "All tests pass; Pint clean; PHPStan 0 errors; Python tests pass." — PHP/Pint/PHPStan independently reproduced and pass; Python tests do not exist (H3), so this criterion is not met.

## Test Verification

Tests reviewed:

- `tests/Unit/Transcription/LanguageIdentifierTest.php`
- `tests/Unit/Transcription/TranscriptSegmentDataTest.php`
- `tests/Unit/Transcription/NormalizedTranscriptTest.php`
- `tests/Unit/Transcription/TranscriptionOptionsTest.php`
- `tests/Unit/Transcription/TranscriptionMediaTest.php`
- `tests/Unit/Transcription/TranscriptionExceptionTest.php`
- `tests/Unit/Transcription/WorkerTransportTest.php`

Commands independently executed by reviewer (PHP 8.4.24,
`C:/Users/Admin/.config/herd/bin/php84/php.exe`):

- `php artisan test --compact` → `269 tests, 268 passed, 1 skipped, 813 assertions` (matches P3-003's claimed count exactly; independently confirms no regressions against the pre-Phase-3 baseline of 229 passed/688 assertions — the +39 tests / +125 assertions delta corresponds exactly to the P3-001 (31/90) + P3-002 (8/35... i.e. 39 total/125 total) additions, and P3-003 added zero new PHP tests, consistent with H3).
- `vendor/bin/pint --test` → passed, no style violations.
- `vendor/bin/phpstan analyse --no-progress` → passed, 0 errors.
- Repository-wide search for Python test files under `worker/` → none found.
- Repository-wide search for `HttpTranscriptionProvider` under `tests/` → no references found (M3).
- Repository-wide search for `requested_language` under `worker/` → appears only in the unused Pydantic field (H2).

Result:

PHP-side automated verification is genuinely clean and reproduces the
claimed numbers exactly. Python-side automated verification does not exist.

## Architecture Review

Status: CHANGES_REQUESTED (see M1, M2)

Notes:

The provider-neutral boundary itself (interface, DTOs, container binding,
HTTP adapter behind an interface) is well-formed and matches the intent of
ADR-017 and the Phase 3 spec — no faster-whisper/FFmpeg/Python types leak
into the PHP domain, and the worker is not blocked by needing binary media in
the request. However, two structural gaps undermine claims already made
about this batch's own deliverables: the transcription/attempt identity
concept central to P3-001's contract is not actually load-bearing anywhere
(M1, M2), and the "provider-neutral" contract has not yet had to prove itself
against a real HTTP boundary in tests (M3). These are architectural, not
merely cosmetic, because later batch tasks (P3-004 persistence, P3-006 queue
orchestration, P3-007 retry hardening) are explicitly scoped to build on
"logical transcription identity and attempt identity" as an already-settled
contract; right now that contract is unproven and the interface shape may
need to change to carry real identifiers, which is cheaper to fix now than
after three more tasks depend on it.

## Security and Authorization Review

Status: CHANGES_REQUESTED (see H1)

Notes:

Path-traversal and absolute-path handling on the worker side
(`worker/media.py`) is sound: traversal and absolute paths are rejected
before resolution, and the resolved path is additionally verified to stay
under the shared media root via a realpath-prefix check — this is the actual
enforcement boundary and it is solid, independent of the PHP-side gap (L1).
FFmpeg is invoked via argument-array `subprocess.run` with no shell
interpolation, so there is no command-injection surface from the media path
or filenames. The one genuine security/availability finding is H1: bearer
authentication is configured with mismatched environment variable names
between the two sides of the boundary, meaning the "authenticated internal
worker" boundary described in P3-002/P3-003 cannot currently succeed at all
as documented and configured — this is a functional break, not just an
unused feature.

## Regression Risk

Status: PASS

Notes:

Full suite (269 tests) passes with the same 1 pre-existing skip as the
Phase 2 baseline and zero failures; no existing behavior was changed by this
batch (all changes are net-new files plus config/service-provider
registration). No regression risk identified against Phase 1/2 functionality.

## Required Changes

1. Resolve B1 with the Human Product Owner before P3-003 can be considered
   complete: either actually run the benchmark gate and record real evidence
   in `BENCHMARK-GATE-EVIDENCE.md`, or obtain an explicit HPO decision
   (recorded in `DECISION_QUEUE.md`/`DECISIONS.md`) to waive/defer the gate
   and accept `turbo` as the interim default. This is a phase/batch
   sequencing and product decision, not something OpenCode should resolve by
   proceeding silently.
2. Fix H1: align the bearer-token environment variable name (or explicitly
   document and wire a single shared name) between `config/transcription.php`
   and `worker/config.py`, and add a test (PHP and/or Python) that would have
   caught the mismatch.
3. Fix H2: apply `requested_language` in `worker/transcription.py`'s call to
   `model.transcribe()` when present, and add a test proving the hint is
   passed through.
4. Fix H3: add a Python test suite (pytest) covering at minimum:
   `resolve_media_path` traversal/absolute-path/escape rejection,
   `verify_token` accept/reject cases, `prepare_audio` failure/timeout
   handling, and the no-speech / segment-language normalization branches of
   `transcribe_audio` (faster-whisper itself can be mocked/stubbed for unit
   tests).
5. Address M1: either extend the `TranscriptionProvider` interface to accept
   real transcription/attempt identifiers, or explicitly document (and get
   HPO/architecture sign-off if it changes the contract) that identity
   threading is deliberately deferred to a later task — but do not leave a
   comment claiming a capability ("will be set by caller") that does not
   exist.
6. Address M2: either implement and test the lifecycle-transition and
   retry-vs-retranscription-identity behavior P3-001 itself lists as
   required, or formally descope those acceptance criteria/required tests
   from P3-001 with HPO sign-off if they are intentionally deferred to a
   later task.
7. Address M3: add `Http::fake()`-based tests for `HttpTranscriptionProvider`
   covering header construction, non-2xx-without-envelope handling, and the
   error-envelope-to-`TranscriptionFailure` mapping.
8. LOW items (L1-L4) may be fixed alongside the above or tracked as
   follow-ups at OpenCode's discretion; they are not blocking by themselves.

## Reviewer Conclusion

Current Conclusion:

CHANGES_REQUESTED

This is cycle 1 of the three-cycle escalation policy for this batch review.

## Verification Rule

Not satisfied: one BLOCKER (B1) and three HIGH findings (H1-H3) remain
unresolved. Per policy, the batch cannot be marked VERIFIED while these
remain open, regardless of the individual tasks' otherwise-clean Pint/PHPStan
results.

## Handoff

Per the Phase 3 Batch-Execution Exception: the batch verdict is
`CHANGES_REQUESTED`, so the batch does not progress even though P3-001 and
P3-002's own PHP-side acceptance criteria are largely met — B1 and the
cross-cutting H1/H2/H3 findings affect the batch as a whole (H1/H2/H3 are
P3-003 findings that also invalidate P3-002's AC7 and P3-001's AC4 as
currently unproven).

1. Task status for P3-001, P3-002, and P3-003 is set to `CHANGES_REQUESTED`
   in their task files, referencing this review.
2. Implementation ownership returns to OpenCode for all three tasks.
3. B1 additionally requires a Human Product Owner decision (see Required
   Changes #1) before P3-003 rework resumes on the model-selection question;
   the remaining findings can be fixed without a new HPO decision.
