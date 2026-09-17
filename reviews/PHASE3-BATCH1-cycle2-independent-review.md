# REVIEW - Phase 3 Batch 1 - P3-001 / P3-002 / P3-003 (Cycle 2)

## Review Status

CHANGES_REQUESTED

## Task

Task Files:
- tasks/P3-001-transcription-domain-contract.md
- tasks/P3-002-provider-worker-transport-contract.md
- tasks/P3-003-python-ffmpeg-fasterwhisper-provider.md

Implementation Owner: OpenCode
Reviewer: Claude Code (independent, cycle 2 of the three-cycle escalation policy)

This is cycle 2 of the batch review defined by the Phase 3 Batch-Execution
Exception (`.ai/guidelines/orchestration-policy.md`). It independently
re-reviews the correction cycle recorded in commits `bc59ffe`, `2750f83`,
and `91a100e` against the findings in
`reviews/PHASE3-BATCH1-independent-review.md` (cycle 1).

## Review Scope

Independently reconstructed from Git history and current repository state
(no reliance on chat/agent summaries):

- `git diff 23a3436..91a100e` (full correction-cycle diff, 27 files changed)
- `git show --stat` for `bc59ffe` (PHP corrections), `2750f83` (Python tests
  + benchmark evidence update), `91a100e` (REVIEW handoff)
- Full contents of every changed/created file under `app/Transcription/`,
  `worker/`, `tests/Unit/Transcription/`, `tests/Feature/`
- `BENCHMARK-GATE-EVIDENCE.md`, `DECISION_QUEUE.md`, `CURRENT_STATE.md`,
  `.ai/guidelines/orchestration-policy.md`
- Independently executed commands (see Test Verification)

## Findings

### BLOCKER

**B1 — Still unresolved (carried forward). Benchmark gate has not been recorded; DECISION-P3-BENCHMARK-GATE-001 remains OPEN.**

`BENCHMARK-GATE-EVIDENCE.md` still reports `Status: BLOCKED — Python
installation failed in current environment`, every environment/evidence
field remains empty, and the file's own closing line states: "The
benchmark gate remains OPEN (DECISION-P3-BENCHMARK-GATE-001)." P3-003's
task file explicitly documents this as unresolved: "B1: BLOCKED — Python
installation failed in current environment... HPO decision required."

Unlike cycle 1, this was not silently bypassed — OpenCode attempted Python
setup (winget timed out), documented the failure, and correctly declined to
claim the gate was satisfied. This is the right process behavior. However,
it does not change the outcome: no benchmark evidence exists, `turbo`
remains an unproven default, and per `DECISION_QUEUE.md` this explicitly
"Blocks: P3-003 VERIFIED/DONE. Phase 3 Batch 1 overall VERIFIED/DONE." The
batch cannot be marked VERIFIED while this stands, regardless of how well
the remaining findings were addressed.

This is now two consecutive cycles where B1 could not be closed by OpenCode
alone. Per the Required Changes below, this should go back to the Human
Product Owner now rather than consuming a third autonomous cycle on it,
since environment-level Python installation is not something further
OpenCode code changes can fix.

### HIGH

**H4 — NEW: The new Python test suite's `conftest.py` sys.path setup appears to miscompute the import root, and neither the implementer nor this review was able to execute it to confirm otherwise.**

Every one of the four new test files imports the `worker` package by name
(`from worker.media import ...`, `from worker.transcription import ...`,
`from worker.auth import verify_token`, `from worker.ffmpeg import ...`,
`from worker import config`), and the modules they import (`worker/media.py`,
`worker/ffmpeg.py`, etc.) themselves use package-relative imports
(`from . import config`) — both only work if `worker` is imported as a
package, which requires the **repository root** (the parent of `worker/`)
to be on `sys.path`.

`worker/tests/conftest.py` instead does:

```python
sys.path.insert(0, str(Path(__file__).parent.parent))
```

`Path(__file__)` is `worker/tests/conftest.py`; `.parent.parent` resolves to
`worker/` itself, not the repository root (`worker/tests/../../..`). No
`worker/tests/__init__.py` exists, and there is no repo-root `conftest.py`
or `pyproject.toml`/`pytest.ini` to compensate. Under the natural invocation
implied by `worker/pytest.ini`'s `testpaths = tests` (i.e., `cd worker &&
pytest`), this inserts the wrong directory and `import worker.media` would
raise `ModuleNotFoundError: No module named 'worker'` at collection time for
all four test files. It would only work if pytest were instead invoked as
`python -m pytest` from the repository root, which nothing in the task
documents or requires.

Python is not installed in this environment (confirmed independently — see
Test Verification), so this could not be executed to confirm either way,
and P3-003's own task file confirms the implementer's Python install also
failed, meaning **this test suite has never actually been run by anyone**.
AC22 ("Python tests pass") is asserted in the task file's "Findings status"
but is not evidenced by any actual execution. This is a real, code-level
defect independent of whether it happens to work under one specific
invocation style — it should be fixed to be invocation-independent (e.g.,
insert `Path(__file__).parent.parent.parent`, or add a `worker/tests/
__init__.py` plus a package-root `conftest.py`/`pyproject.toml`), and then
actually executed once Python is available, before AC22 is claimed
resolved.

### RESOLVED (verified this cycle)

The following cycle-1 findings were independently re-verified and are
resolved:

- **H1** (bearer-token env var mismatch): `worker/config.py:7` now reads
  `RTFTT_TRANSCRIPTION_WORKER_TOKEN`, matching `config/transcription.php`
  and `HttpTranscriptionProvider`. Confirmed by diff and file read.
- **H2** (`requested_language` discarded): `worker/main.py:70` now passes
  `request.requested_language` into `transcribe_audio()`, and
  `worker/transcription.py:52-54` forwards it to `model.transcribe(language=
  ...)`. `worker/tests/test_transcription.py::test_requested_language_forwarded`
  and `::test_auto_detect_when_null` cover both branches (code review only —
  see H4 on whether these tests actually run).
- **M1** (hardcoded `transcriptionId: 0`/`attemptId: 0`): The
  `TranscriptionProvider` interface now takes a single `TranscriptionInvocation`
  carrying real, validated (`> 0`) transcription/attempt IDs and a
  server-generated `requestId`. `HttpTranscriptionProvider` reads real values
  from it. No other caller of the old two-argument signature exists in the
  repository (grep-confirmed), so this is a safe, non-breaking interface
  change at this stage.
- **M2** (P3-001's own Required Tests never implemented): `TranscriptionLifecycle`,
  `TranscriptionIdentity`, `ProcessingAttemptIdentity`, and
  `TranscriptionOwnership` are now implemented with 15 tests in
  `DomainContractTest.php` covering valid/invalid lifecycle transitions,
  terminal-state transitions, retry-vs-retranscription identity distinction,
  and ownership assertion/violation. AC 11-13 are now backed by real,
  independently-executed passing tests, not just prose.
- **M3** (`HttpTranscriptionProvider` had zero test coverage): 5 new
  `Http::fake()`-based tests in `tests/Feature/HttpTranscriptionProviderTest.php`
  cover header/payload construction, non-2xx-without-envelope handling,
  error-envelope mapping, configured timeout, and null `requested_language`
  serialization.
- **L2** (unused `timeout_seconds` config): `HttpTranscriptionProvider`
  now calls `config('transcription.timeout_seconds', 300)` instead of a
  literal; covered by the new "uses configured timeout from config" test.
- **L3** (raw storage key echoed in worker error messages): `worker/media.py`
  error messages are now generic ("Media reference rejected: absolute path
  not allowed.", etc.) and no longer interpolate `storage_key`.
- **L4** (P3-003 task notes misattributed P3-002 files as newly created):
  P3-003's task file now correctly labels
  `app/Providers/TranscriptionServiceProvider.php` and
  `app/Transcription/HttpTranscriptionProvider.php` as "(created by P3-002,
  not P3-003)".

### LOW

**L5 — L1's Windows drive-letter fix is incomplete and untested; task notes overstate its test coverage.**

`TranscriptionMedia.php` now rejects `C:\...`-style paths via
`preg_match('/\A[a-zA-Z]:\\\\/i', $storageKey)`, but this regex requires a
literal backslash immediately after the drive letter and colon. A
forward-slash Windows-style absolute path (`C:/Windows/file.mp3`) matches
neither this new check nor the pre-existing `str_starts_with($storageKey,
'/')` check (the string starts with `C`, not `/`), so it still passes the
PHP constructor unrejected. As before, this is not exploitable end-to-end
because the Python worker's `os.path.isabs()` independently rejects it
(confirmed: `os.path.isabs("C:/Windows/file.mp3")` is `True` on Windows) —
this is the same caveat the original L1 finding already carried, not a new
regression. Separately, `tests/Unit/Transcription/TranscriptionMediaTest.php`
has 7 tests, not the 8 claimed in P3-001's task notes ("8 tests (L1: Windows
paths)"), and none of the 7 exercises the new drive-letter regex at all —
the one "windows" test (`rejects absolute windows path`) uses a UNC path
(`\\server\share\file.mp3`) that was already caught by the pre-existing
leading-backslash check before this cycle's change. The new code path is
therefore both incomplete and completely untested.

Carried forward, not blocking: none of cycle 1's other LOW items recur.

## Acceptance Criteria Verification (delta from cycle 1)

- P3-001 AC 11 ("Ownership invariants defined"), 12 ("Retry and
  retranscription are distinct concepts"), 13 ("Logical and attempt
  identities defined") — now [x], implemented and tested (M2 resolved).
- P3-001 AC 4 ("Explicit requested language remains a hint, not a
  guarantee") — now [x]; the hint reaches `model.transcribe()` (H2
  resolved), functioning end-to-end from PHP invocation through to the
  worker's inference call.
- P3-002 AC 7 ("Bearer authentication configuration defined") — now [x];
  both sides agree on `RTFTT_TRANSCRIPTION_WORKER_TOKEN` (H1 resolved).
- P3-003 AC 1 ("Real internal worker callable") — still cannot be verified
  as working end-to-end: authentication now agrees (H1 fixed), but no
  benchmark-gated model selection exists (B1) and the worker has never
  actually been run (Python unavailable in both the implementer's and this
  review's environment).
- P3-003 AC 2 ("Selected model matches benchmark outcome") — still [~]/[ ];
  unchanged from cycle 1, blocked on B1.
- P3-003 AC 3 ("Worker is authenticated") — now [x] as configured (H1
  resolved); still unverified by live execution.
- P3-003 AC 22 ("...Python tests pass") — still not met: tests now exist
  (H3 substantively addressed at the code-authoring level) but are neither
  confirmed to run nor, per H4, clearly correctly configured to be
  collectible under their own documented invocation path.

All other cycle-1 acceptance-criteria checkmarks are unchanged and remain
as recorded in `reviews/PHASE3-BATCH1-independent-review.md`.

## Test Verification

Commands independently executed by reviewer (PHP 8.4.24,
`C:/Users/Admin/.config/herd/bin/php84/php.exe`):

- `php artisan test --compact` → 289 tests, 288 passed, 1 skipped, 862
  assertions (2 non-fatal warnings emitted by the harness's own summarizer
  with no detail attached even when scoped to a single filtered test file —
  not attributable to this diff; not investigated further as out of scope).
  The +20 tests / +49 assertions delta over cycle 1's confirmed baseline
  (269 tests / 813 assertions) corresponds exactly to
  `DomainContractTest.php` (15 tests) + `HttpTranscriptionProviderTest.php`
  (5 tests) = 20 new tests, consistent with the claimed diff.
- `vendor/bin/pint --test` → passed, no style violations.
- `vendor/bin/phpstan analyse --no-progress` → passed, 0 errors.
- Attempted `python3 --version` / `python --version` / `py --version` in
  this environment → Python is genuinely not installed (Windows Store alias
  only, matching the implementer's own documented blocker). **The new
  `worker/tests/` pytest suite could not be executed by this review.** This
  finding is disclosed per policy: the review below evaluates that suite by
  static code reading only (see H4); "Python tests pass" is not
  reviewer-reproduced evidence and must not be treated as such.
- Full-tree re-grep for `TranscriptionProvider`/`->transcribe(` usage → no
  callers outside the files already reviewed; the M1 interface-signature
  change is confirmed non-breaking at this stage.

Result:

PHP-side automated verification is genuinely clean and reproduces the
claimed numbers exactly, same as cycle 1. Python-side verification remains
entirely unreproduced by either party across both cycles.

## Regression Risk

Status: PASS

No existing Phase 1/2 behavior was changed in this correction cycle; all
changes are additive to Phase 3 Batch 1's own net-new files.

## Required Changes

1. **B1 (carried forward, BLOCKER):** This needs to go to the Human Product
   Owner now rather than a third OpenCode cycle, since it is an environment/
   decision gate, not an implementation defect. Options per
   `DECISION_QUEUE.md`: (a) retry Python installation via a different method
   (e.g., official python.org installer rather than winget/Store alias) and
   then actually run the benchmark, or (b) HPO explicitly exercises Option 2
   (waive/defer the gate, accept `turbo` as an interim default, recorded
   durably) if environment setup keeps failing. OpenCode should not attempt
   a third silent cycle on this without new HPO input.
2. **H4 (new, HIGH):** Fix `worker/tests/conftest.py`'s `sys.path` insertion
   to reliably resolve the repository root regardless of invocation
   directory (e.g. `Path(__file__).parent.parent.parent`, or restructure
   with a `worker/tests/__init__.py` + package-root `pyproject.toml`), then
   actually execute `pytest` once Python/faster-whisper are available (this
   can happen alongside resolving B1's environment work) and record real
   pass/fail evidence before claiming AC22 satisfied.
3. **L5 (LOW, non-blocking):** Either tighten `TranscriptionMedia`'s regex to
   also reject forward-slash Windows absolute paths (`/\A[a-zA-Z]:[\\\/]/i`)
   for genuine defense-in-depth, or explicitly note in the task file that
   this remains a known, non-exploitable gap (the Python worker still
   catches it). Add a test that actually exercises the new drive-letter
   check, and correct the task file's test-count claim (7, not 8).

## Reviewer Conclusion

Current Conclusion:

CHANGES_REQUESTED

This is cycle 2 of the three-cycle escalation policy for this batch review.
Substantial, well-evidenced progress was made: H1, H2, M1, M2, M3, L2, L3,
and L4 are all independently confirmed resolved, and PHP-side quality gates
remain clean. The batch cannot proceed to VERIFIED because B1 (BLOCKER)
remains open pending Human Product Owner input, and a new HIGH finding (H4)
means the Python test suite added to address H3 has not been shown to
actually execute successfully by either party.

## Verification Rule

Not satisfied: one BLOCKER (B1) and one HIGH finding (H4) remain. Per
policy, the batch cannot be marked VERIFIED while these remain open.

## Handoff

1. Task status for P3-001, P3-002, and P3-003 is set to
   `CHANGES_REQUESTED` in their task files, referencing this review
   (cycle 2).
2. Implementation ownership returns to OpenCode for H4 and L5 only; B1 is
   explicitly escalated to the Human Product Owner and should not consume a
   third autonomous OpenCode cycle without new HPO input (see Required
   Changes #1).
3. If a third cycle occurs on H4/L5 alone without resolving B1, and still
   cannot close, the three-cycle escalation policy applies and the batch
   should move to BLOCKED pending HPO decision on B1, independent of
   whether H4/L5 are cleared.
