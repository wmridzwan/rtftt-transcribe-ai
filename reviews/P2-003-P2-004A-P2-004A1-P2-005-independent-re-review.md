# P2-003 (regression) / P2-004A / P2-004A1 / P2-005 — Independent Re-Review

Reviewer: Claude Code

Prior evidence trail (preserved, not rewritten):
- `reviews/P2-003-independent-review.md` (third-pass VERIFIED, for the
  pre-remediation revision of `MediaIngestionService.php`)
- `reviews/P2-004A-P2-004A1-P2-005-independent-review.md` (CHANGES_REQUESTED,
  four findings)
- `reviews/P2-004A-P2-004A1-P2-005-remediation-report.md` (implementer's
  remediation claims, independently verified below)

Implementation owner: Codex, under `DECISIONS.md` ADR-012.

This review does not modify code, tests, governance records, or task status,
and does not commit anything. It does not authorize P2-007 or Phase 3.

## 1. Overall Summary

The governance/authorization defect from the previous review (Finding 1) is
resolved: the repository's records are now internally consistent, the prior
CHANGES_REQUESTED review is preserved and cross-referenced rather than
rewritten, and no task is misrepresented as VERIFIED. The claim-before-write
ordering defect implied by Finding 2 is correctly fixed and does not create an
unbounded or permanent claim. The FFprobe path-resolution defect (Finding 4)
is correctly fixed using Laravel's public API. However, two of the four
original findings are only **partially** resolved, and mechanical
verification surfaces a **new, reproducible test failure** that the
remediation report does not disclose:

- The mandatory "cleanup cannot delete an active attempt" guarantee (Finding
  3 / P2-004A1) is proven only for the case where a claim was already active
  at the moment the cleanup command checks it. The command has no re-check or
  lock immediately before its final `deleteDirectory()` call, leaving a real
  (if narrow) check-then-act race between claim refresh and deletion.
- The FFprobe runtime dependency required for the real-probe test (Finding 4
  / P2-005) is not installed on this review machine and is not documented,
  guarded, or provisioned anywhere in the repository. Independently
  reproduced: this makes the full suite **fail**, not pass, in this
  environment — one concrete test failure, not the "0 failures" the
  remediation report implies for the final handoff.

Both of these prevent a clean VERIFIED for P2-004A, P2-004A1, and P2-005.
P2-003's own regression surface (the claim lifecycle added to
`MediaIngestionService.php`) is independently verified as compatible with the
previously accepted contracts and is VERIFIED on its own terms; the residual
race gap is attributed to the cleanup command (P2-004A/P2-004A1), not to
P2-003's ingestion contract itself.

## 2. Verdicts

| Surface | Verdict |
|---|---|
| P2-003 (regression re-review of `MediaIngestionService.php`) | **VERIFIED** |
| P2-004A (`media:cleanup-staging` command) | **CHANGES_REQUESTED** |
| P2-004A1 (staging-claim/lease contract) | **CHANGES_REQUESTED** |
| P2-005 (`MediaMetadataProbeService`) | **CHANGES_REQUESTED** |

P2-004 closure: **remains valid** (unaffected by the above; see §6).
P2-006 closure: **remains valid** (unaffected by the above; see §6).

P2-007 and Phase 3 are **not authorized** by this review. This is the second
CHANGES_REQUESTED cycle for P2-004A/P2-004A1/P2-005 under the repository's
"at most three autonomous repair and re-review cycles" rule
(`.ai/guidelines/orchestration-policy.md:35`) — one more failed cycle after
this would require escalation to BLOCKED rather than a further silent retry.

## 3. Resolution Status of Prior Findings

### Finding 1 — Authorization / governance trail: **RESOLVED**

Independently verified:

- `DECISIONS.md` ADR-012 (lines 511-559) records the Product Owner's
  authorization, explicitly frames the earlier gap as a governance/audit-trail
  failure rather than grounds for reversion, and explicitly preserves the
  original "unauthorized implementation" finding rather than erasing it.
- `DECISION_QUEUE.md` now has `DECIDED — DECISION-P2-REMEDIATION-001`
  (previously "Open Decisions: None" with no record at all), and its
  "Open Decisions" section still correctly reads "None" (nothing is
  pending).
- `plan.md` and `CURRENT_STATE.md` were both updated and are now mutually
  consistent: both describe the current phase as "Authorized remediation of
  P2-003/P2-004A/P2-004A1/P2-005," both state affected tasks are
  REVIEW/awaiting re-review (not DONE), and both explicitly reserve P2-007
  and Phase 3 as unauthorized. This replaces the prior direct contradiction
  between the header lines and the body sections of the same files.
- All three task files (`tasks/P2-004A-staging-cleanup-command.md`,
  `tasks/P2-004A1-upload-attempt-lease-contract.md`,
  `tasks/P2-005-media-metadata-probe.md`) now carry `Status: REVIEW` (not the
  previous `DONE`), and each explicitly preserves its original denial text as
  historical record while noting ADR-012 supersedes it for this bounded
  remediation only. This is the correct way to reconcile a status
  contradiction: by superseding with a dated decision, not by editing away
  the original text.
- I confirmed byte-for-byte that
  `reviews/P2-004A-P2-004A1-P2-005-independent-review.md` (the prior review)
  is unmodified from what this reviewer originally wrote — `diff` against a
  reconstructed copy shows no divergence.
- No task file, `CURRENT_STATE.md`, or `plan.md` claims any of the four
  affected surfaces is VERIFIED or DONE. `CURRENT_STATE.md`'s "Active Task"
  and "Ready Tasks" sections correctly say REVIEW/awaiting re-review, and
  "Implemented, authorized, REVIEW, VERIFIED and DONE" remain distinct
  states in every record inspected.

Residual note (not a blocker): ADR-012 is a *retroactive* authorization — it
documents that the Product Owner now confirms the work was authorized, not a
contemporaneous approval captured before implementation began. This reviewer
has no way to independently verify the underlying conversation occurred at
the time claimed; only that the written record is now internally consistent
and does not claim more than that. This is an inherent limitation of
after-the-fact governance repair, not something a further remediation pass on
the repository files can resolve, and ADR-012 itself is honest about this
("a governance/audit-trail failure, not a reason to revert").

### Finding 2 — P2-003 verification boundary: **RESOLVED** (see §4 for the full regression re-review)

The surface was genuinely reopened, not silently inherited: `CURRENT_STATE.md`
explicitly states P2-003 is REVIEW pending regression re-verification, and
this review independently re-verifies the modified file rather than citing
the historical third-pass verdict as still applicable.

### Finding 3 — Mandatory race/orphan tests: **PARTIALLY RESOLVED**

The 11 previously-skipped placeholder tests in
`tests/Feature/IngestionCompensationContractTest.php` are now real,
executing tests. Independently reproduced:
`php artisan test tests/Feature/IngestionCompensationContractTest.php` →
**11 passed / 36 assertions / 0 skipped** (previously 11 skipped / 0
assertions). This is genuine remediation, not relabeling — each test now
calls real production code (`Artisan::call('media:cleanup-staging')`, the
real `MediaIngestionService`) against real fixtures, not mocks standing in
for the behavior under test.

However, per the explicit instruction to verify these tests "prove the
intended race invariant" rather than being accepted merely because they
pass: the two tests that exercise the claim/cleanup interaction
(`it defers staging cleanup while a retry owns the active attempt claim`,
`it requires restaging with the same attempt identity after cleanup wins a
race`) are **sequential, single-process, state-setup tests**. They prove
"if a claim is already active/expired at the moment the command reads it,
the command does the right thing" — a legitimate and necessary property —
but they do not, and structurally cannot, exercise true concurrent
interleaving. See **New Finding A** below for the resulting gap this leaves
open in the implementation itself, not just in the tests.

### Finding 4 — FFprobe path resolution: **PARTIALLY RESOLVED**

The underlying code defect is genuinely fixed.
`app/Services/MediaMetadataProbeService::getAbsolutePath()` now calls
`MediaFile::storage()->path($storagePath)` (line 107), which is
`Illuminate\Filesystem\FilesystemAdapter::path()` — a real, public,
supported API (confirmed at
`vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemAdapter.php:296-299`),
unlike the removed `getDriver()->getAdapter()->getPathPrefix()` chain, which
never worked. The call is wrapped in `try/catch`, which is the correct shape
for disks (e.g. S3) where `path()` is unsupported and throws.

The new test (`tests/Feature/MediaMetadataProbeServiceTest.php:38-63`)
genuinely constructs a real, minimal, valid PCM WAV fixture (RIFF/WAVE/fmt/
data chunks, 8000 Hz, 16-bit mono, exactly 8000 samples of silence = 1.000s)
and writes it to the faked local disk, then calls the real `probe()` method
with no mocking of the process layer — this is a real end-to-end exercise of
the intended success path, not an accidental repeat of the same no-op branch
the previous review flagged. This is exactly the kind of test the previous
review said was missing.

But independently reproduced on this review machine: **this test fails.**
`ffprobe` is not installed (`which ffprobe` → not found; `RTFTT_FFPROBE_PATH`
unset in `.env`; no `ffprobe`/`ffmpeg` binary found anywhere searched). The
failure is not a defect in the service's own fallback behavior — `probe()`
correctly catches the resulting `ProcessFailedException`, logs it, and
returns all-null metadata exactly as designed for an unavailable dependency
— but the **test itself has no skip-if-unavailable guard**, so it asserts
`duration_seconds === 1` and gets `null`, and hard-fails:

```
Failed asserting that null is identical to 1.
tests/Feature/MediaMetadataProbeServiceTest.php:58
```

Cross-checked for documentation of this operational dependency: no
`ffprobe`/`ffmpeg` entry in `.env.example` (file has none), no mention as an
operational prerequisite in `README.md` (its only FFprobe/FFmpeg mention is
the pre-existing Phase 3 roadmap heading, not a current dependency note), and
no skip/guard in `phpunit.xml` or the test itself. Runtime FFprobe
availability is **silently assumed**, not "appropriately documented/deployed
as an operational dependency," which is precisely the question this review
was asked to settle. See **New Finding B**.

## 4. P2-003 Regression Re-Review — Detail

Read `app/Actions/MediaIngestionService.php` in full and compared it against
every previously accepted P2-002B/ADR-009/P2-003 contract element:

- **Same-attempt idempotency** — `ingest()` still checks `findByAttempt()`
  first and returns the existing record unchanged before touching storage.
  Unaffected by the claim addition.
- **Cross-user isolation** — claims are scoped by `(user_id,
  upload_attempt_id)` identically to `MediaFile`'s own unique boundary
  (enforced by the `staging_claims` unique index in
  `database/migrations/2026_09_13_130000_create_staging_claims_table.php:20`).
  `tests/Feature/IngestionCompensationContractTest.php:49-56` and
  `tests/Feature/StagingClaimTest.php:67-84` both independently confirm two
  different owners may hold a claim for the same attempt UUID without
  collision, and cross-user MediaFile reuse is still rejected.
- **Retry semantics** — the ambiguous-duplicate-key recovery path (lines
  119-159) is textually unchanged from the previously verified P2-003 logic;
  it still looks up by owner/attempt before replay and never double-inserts.
- **Staging behavior / claim-before-write ordering** — traced precisely, per
  the review's explicit instruction:
  1. `StagingClaim::updateOrCreate(...)` is called **before**
     `$storage->putFileAs(...)` (lines 228-235 vs. 238).
  2. If `putFileAs` throws (a catchable, synchronous failure), the `catch`
     block (lines 241-247) deletes the just-created/refreshed claim row and
     rethrows. **No claim survives a caught write failure.**
  3. If the process is killed/interrupted **between** claim creation and a
     completed write (uncatchable — e.g. OOM-kill, host crash), the claim
     row survives, but it is bounded by `expires_at = now() +
     temporary_retention_hours` (line 234) — the same 24-hour window the
     pre-existing retention contract already used for pure mtime-based
     eligibility. It is not permanent, not unbounded, and does not block
     cleanup indefinitely; it only requires the same 24-hour wait a
     crashed/abandoned attempt would have required anyway. **This specific
     scenario the review asked about is correctly handled.**
  4. Traced the ambiguous-duplicate-key branch's interaction with claim
     cleanup (§3, Finding 3 discussion): regardless of which of two
     concurrent `stage()` calls last updated the shared claim row's
     `staging_path` column, exactly one of the two completing `ingest()`
     flows' `deleteStaging()` calls will match that column's current value
     and delete the row; the other is a harmless no-op. No permanent orphan
     claim row results from this race either.
- **Promotion/persistence compensation** — `deleteOrLog()` and the
  try/catch/finally structure around promotion and the DB transaction are
  unchanged from the prior verified revision; claims are additive
  bookkeeping layered on top, not a replacement for the existing
  compensation logic.
- **Failure recovery** — unchanged; the outer `catch` in `ingest()` still
  compensates staging and the durable object identically to the previously
  verified behavior.
- **Cleanup interaction** — this is where a genuine, currently-open gap
  exists; see New Finding A. It is a property of `CleanupStaging` (P2-004A),
  not of `MediaIngestionService`'s own logic, which behaves correctly in
  isolation.
- **Processing/transcription side-effect prohibition** — confirmed no
  reference to `Transcription`, `ProcessingJob`, queues, or Phase 3 concerns
  anywhere in the file.

**Verdict: P2-003 regression surface is VERIFIED.** The claim lifecycle
added to the ingestion service is compatible with every previously accepted
P2-003 contract, does not weaken any existing guarantee, and is adequately
tested for the specific scenario the remediation targeted (claim-before-write
ordering). The residual concurrency gap belongs to the cleanup command's own
design, addressed below under P2-004A/P2-004A1.

## 5. New Findings

### New Finding A (HIGH) — `CleanupStaging` has no check-then-act protection between its claim read and its final directory delete

**File:** `app/Console/Commands/CleanupStaging.php`

**Evidence:** The command reads claim state once
(`StagingClaim::where(...)->first()`, line 67) and, if inactive/expired,
proceeds through age evaluation to `$disk->delete($file)` for every
previously-listed file and then unconditionally `$disk->deleteDirectory
($attemptDir)` (lines 120-137) — with no re-check of claim state, no
database transaction, and no row lock (`lockForUpdate()`) between the read
and the delete. There is no `DB::transaction`/lock anywhere in this method.

**Failure scenario:** An operator manually runs
`php artisan media:cleanup-staging` (its only supported invocation per the
task's own explicit non-scope — no scheduler exists). At nearly the same
moment, a user's browser retries an in-flight upload for an attempt whose
claim had just expired. The retry's `MediaIngestionService::stage()` calls
`StagingClaim::updateOrCreate(...)`, refreshing the claim to active,
*after* the cleanup command already read it as inactive but *before* the
command reaches its `deleteDirectory()` call. The command proceeds to
delete the directory, including the file the retry is actively writing (or
just wrote).

**Why this matters against the established contract:** P2-004A1's own
acceptance criteria (`tasks/P2-004A1-upload-attempt-lease-contract.md`,
"Future Implementation Acceptance Criteria" §1) state as an unconditional
property: "An active upload cannot be cleaned while its claim/lease is
valid." The current implementation only guarantees this when the claim was
already active *at the instant the command checked it* — not for the
lifetime of the command's execution. The task explicitly anticipated this
exact class of problem and required a "race test that proves an active
P2-003 retry cannot be deleted while it is staging or promoting" — the
existing tests prove the simpler, necessary-but-insufficient property
instead (see §3, Finding 3).

**Mitigating context (why this is HIGH, not BLOCKER):** The failure mode is
recoverable, not corrupting: the affected retry's own `stage()` catch block
(traced in §4) deletes its own claim and rethrows on a storage error, and
the existing P2-002B contract already promises the user a retryable failure
using the same attempt id. No cross-user leakage, no permanent state
corruption, and no silent duplicate record results. The window is also
narrowed by the "manually invoked only" constraint — there is no scheduler
in this task's scope to make this a routine occurrence. It is nonetheless a
verified, reproducible-in-principle gap against an explicit contract clause,
not a theoretical nitpick.

**What must change before VERIFIED:** Either (a) re-check the claim's
current state transactionally (e.g. `DB::transaction` + `lockForUpdate()`
on the claim row, or an equivalent atomic claim-release step) immediately
before `deleteDirectory()`, so the decision and the deletion are not
separated by an unguarded window; or (b) the Product Owner explicitly
accepts this narrower "point-in-time-checked, not lock-protected" guarantee
as sufficient for a manually-invoked-only command, recorded as a decision
rather than left implicit. Absent one of those two, P2-004A and P2-004A1
cannot be VERIFIED against their own stated acceptance criteria.

### New Finding B (HIGH) — Undocumented, unguarded external-binary test dependency breaks the suite in this environment

**Files:** `tests/Feature/MediaMetadataProbeServiceTest.php:38-63`,
`config/media.php:29`, `README.md`, `.env.example`

**Evidence:** Independently reproduced (see §7 for exact commands/output):
`php artisan test --compact` → **216 tests, 214 passed, 1 failed, 1 skipped,
642 assertions**. The single failure is
`probe resolves a real private-disk path and extracts metadata from a WAV
fixture`, failing at its first assertion (`Failed asserting that null is
identical to 1.`) because `ffprobe` is not present on this machine's `PATH`
and `RTFTT_FFPROBE_PATH` is unset. Confirmed no `.env.example` entry, no
README operational note (its only FFprobe/FFmpeg reference is the pre-existing
Phase 3 roadmap line, not a current dependency), and no
`markTestSkipped()`/availability guard in the test itself or in
`phpunit.xml`.

**Why this matters:** This is exactly the operational-dependency question
this review was asked to settle, and the answer is that it is not settled:
FFprobe is a real external-binary dependency being silently assumed present
by an executing test, not documented as a setup prerequisite anywhere a
developer, CI system, or future reviewer would see it before running the
suite. The remediation report's "verification performed by implementation
owner" section only reports results "with `RTFTT_FFPROBE_PATH` set to the
installed FFprobe executable" — true on a machine with FFprobe installed,
but the report does not flag that the suite is red without it, and
`CURRENT_STATE.md`/`plan.md` make no mention of a new environment
prerequisite for Phase 2 test execution.

**What must change before VERIFIED:** At minimum, guard the real-probe test
with an availability check (e.g. `if (! app(MediaMetadataProbeService::class)
->isAvailable()) { $this->markTestSkipped('ffprobe is not installed'); }`)
so the suite is green (with a visible skip) on a machine without FFprobe,
and add the dependency to `.env.example`/`README.md`/`CURRENT_STATE.md` as an
explicit Phase 2 operational prerequisite for P2-005, consistent with
ADR-012's framing of FFprobe as "an approved Phase 2 media-ingestion
dependency." Silently requiring an uninstalled binary for a green build is
not consistent with "the full suite... remain to be run for the final
handoff" being reported as passing.

### New Finding C (LOW, evidence-traceability) — Reported "targeted" test count does not match any reproducible subset

The remediation report claims "targeted ingestion, compensation,
cleanup/claim, metadata, and P2-003 tests: 32 passed, 143 assertions."
Independently running what appears to be the natural targeted set —
`StagingClaimTest` (6/15), `CleanupStagingCommandTest` (8/16),
`MediaMetadataProbeServiceTest` (5/9, 1 failing), `IngestionCompensationContractTest`
(11/36), `MediaIngestionTest` (16/94), `MediaUploadContractTest` (11/29) —
totals 57 tests / 199 assertions, not 32/143. This is not by itself evidence
of a hidden problem (the discrepancy is plausibly just a narrower file
selection than this reviewer chose, and the authoritative full-suite numbers
are independently reproduced separately below), but it is a traceability gap
worth closing: the remediation report should name the exact file list behind
any reported subset count so a reviewer can reproduce it exactly rather than
reconstruct a best guess.

## 6. Regression and Architecture Scope Check

Independently checked for scope creep or regression beyond the authorized
remediation boundary (ADR-012: "limited to remediation of P2-004A, P2-004A1,
P2-005, and the affected P2-003 MediaIngestionService regression surface...
does not authorize transcription, speech recognition, translation, queues,
workers, Redis, Horizon, P2-007, or Phase 3"):

- No reference to `Transcription`, `ProcessingJob`, queue, Redis, Horizon,
  or worker code in any changed/new file inspected.
- The exact `524,288,000`-byte boundary (`MediaIngestionService::
  validateByteSize()`) is untouched.
- The MIME/extension matrix (`config/media.php`'s `supported_media`) is
  untouched by this remediation; only `ffprobe_path` was added, which is
  additive configuration for the newly-authorized P2-005 scope, not a change
  to the accepted upload matrix.
- Private/opaque storage (`MediaFile::storage()`, UUID-based durable paths)
  is unchanged; `MediaMetadataProbeService` only reads an already-persisted
  private path via the same storage abstraction, it does not introduce a new
  storage boundary or expose a public path.
- Ownership boundaries are preserved: `StagingClaim` is owner-scoped
  identically to `MediaFile`, and no cross-owner claim or cleanup path
  exists.
- No premature processing/transcription status transition:
  `MediaMetadataProbeService::probeAndUpdate()` only writes the five
  pre-existing nullable technical columns and never touches `status`.
- FFprobe/FFmpeg's scope is confined to `MediaMetadataProbeService`
  probing an already-persisted file and populating existing nullable
  columns — no transcoding, extraction, waveform generation, transcription,
  or translation code exists anywhere in the diff.

No scope expansion beyond the ADR-012 authorization boundary was found.

## 7. Mechanical Verification (Independently Reproduced)

All commands run directly by this reviewer against the live, unmodified
working tree; no files were changed as a result.

| Check | Remediation report claim | Independently reproduced | Match? |
|---|---|---|---|
| Full suite | 215 passed, 1 skip, 646 assertions | **216 tests, 214 passed, 1 failed, 1 skipped, 642 assertions** | **No — 1 failure not disclosed** (New Finding B); assertion/pass-count delta (4-5) is fully explained by that one test's chained assertions not completing on failure |
| Targeted suite | 32 passed, 143 assertions | 57 tests / 199 assertions across the 6 plausible files (1 failing, same test) | Not reproducible as stated (New Finding C); the one failure is the same ffprobe-dependent test |
| `IngestionCompensationContractTest.php` alone | (all scenarios "executable") | 11 passed / 36 assertions / 0 skipped | Confirmed — matches the qualitative claim |
| Pint (`--test --format agent`) | passed | **passed** | Match |
| PHPStan (`analyse --no-progress`) | 0 errors | **0 errors** | Match |
| Frontend build (`npm run build`) | passed | **passed** (`built in 3.23s`) | Match |
| `git diff --check` | passed | **exit 0** (one harmless CRLF-normalization warning on `.ai/guidelines/ai-development-os.md`, not a conflict marker or trailing-whitespace error) | Match |

`git status` before and after this review's mechanical checks is unchanged
except for the gitignored `public/build/*` output of `npm run build`
(confirmed via `git check-ignore -v public/build` → ignored by
`.gitignore:3`). No source, test, migration, or governance file was modified
by this review.

## 8. P2-004 / P2-006 Closure Re-Assessment

**P2-004 closure: remains valid.** Its rationale — that a broad "upload
pipeline" reimplementation task is unnecessary because P2-003 already
implements and verifies staging, validation, checksum, promotion,
persistence, retry, and compensation, with the narrower remaining
operational concerns split out to P2-004A/P2-004A1 — does not depend on
whether P2-004A/P2-004A1 themselves are currently VERIFIED or
CHANGES_REQUESTED. P2-004 never claimed those successors were complete, only
that they were the correct place for the remaining scope. That remains true.

**P2-006 closure: remains valid.** Its rationale rests on P2-002B defining
the failure/retry contract and P2-003 independently verifying same-attempt
idempotency, cross-user isolation, compensation, and no processing/
transcription side effects — all of which this review's §4 regression
re-verification confirms still hold for the current `MediaIngestionService.php`.
P2-006 explicitly and correctly scoped staging cleanup/lease safety to
P2-004A/P2-004A1 rather than claiming it itself; that those tasks currently
carry open findings does not retroactively invalidate P2-006's own closure
logic, which never depended on their completion.

## 9. Exact Safe Next Action

1. Work may **not** close P2-004A, P2-004A1, or P2-005 as VERIFIED/DONE.
   Route them back to the current implementation owner (Codex, per ADR-012)
   as `CHANGES_REQUESTED` — this is their second such cycle; one further
   failed cycle would require escalation to BLOCKED per the orchestration
   policy's three-cycle limit.
2. Work **may** record P2-003's regression surface as VERIFIED based on this
   review, updating `CURRENT_STATE.md`'s "P2-003 Active Task" section
   accordingly, subject to the State-to-Action Contract's normal closure
   step (source/schema/tests/CI evidence alone does not authorize closure —
   Work performs the state-record update).
3. Required before the next re-review can pass:
   - New Finding A: close the check-then-act gap in `CleanupStaging`
     (transactional re-check/lock immediately before deletion) or obtain an
     explicit Product Owner decision accepting the narrower guarantee.
   - New Finding B: guard the real-probe test for FFprobe availability and
     document the FFprobe/FFmpeg runtime as an explicit Phase 2 operational
     prerequisite (`.env.example`, README, and/or `CURRENT_STATE.md`).
   - New Finding C: name the exact file list behind any future "targeted
     suite" claim so it is independently reproducible.
4. Do not begin P2-007 or Phase 3. Nothing in this review authorizes either.
