# P7-011 — Independent Re-Review, Corrective Cycle 1 (Cycle 2 of max 3)

Reviewer: Claude Code, independent of the P7-011 Builder (OpenCode). This
review does not implement, does not modify implementation code, does not
close Phase 7, does not mark P7-011 DONE, and does not close TD-007.

## A. Baseline

- HEAD: `4d90d5b914c0928574cff7e0f1d1d4a463bb4350`, branch `main`. Working
  tree heavily dirty (multi-wave residue), unchanged by this review.
- Confirmed before starting:
  - `reviews/P7-011-INDEPENDENT-REVIEW.md` (cycle-1 verdict, CHANGES_REQUESTED)
    is present, unmodified by this review, and its two MEDIUM findings and
    verdict text are intact — not rewritten.
  - `reviews/P7-011-BUILDER-REPORT.md` now carries an appended
    "Corrective Cycle 1" section (original builder content above it
    unchanged) describing the fixes for both findings.
  - `tasks/P7-011-retention-cleanup.md` § Status currently reads
    `REVIEW — corrective cycle 1 complete ... resubmitted for independent
    re-review`, with the full BACKLOG → READY → IN_PROGRESS → REVIEW →
    CHANGES_REQUESTED history preserved verbatim above it.
  - No VERIFIED or DONE claim exists anywhere in the task file, builder
    report, or prior review.
- This review is written to a new file
  (`reviews/P7-011-INDEPENDENT-REVIEW-CYCLE2.md`) rather than overwriting
  cycle 1's artifact, preserving both records intact.

## B. Corrective Diff Reviewed

Independently read in full (not diffed against a prior commit — this
repository does not commit per-task; both cycle-0 and cycle-1 state exist
only in the working tree, per established project practice):

- `app/Models/MediaFile.php` — `hasPhysicalFile()`.
- `app/Http/Controllers/TranscriptionController.php` — `$mediaPurged`.
- `resources/views/transcriptions/show.blade.php` — purged-source notice.
- `app/Http/Controllers/MediaActionController.php` — `download()`/`stream()`
  410 (unchanged from cycle 0, re-inspected for consistency).
- `app/Retention/RetentionPurge.php` — `purgeStaging()`,
  `deleteStagingObject()`.
- `docs/RETENTION-POLICY.md`.
- `tests/Feature/Retention/RetentionPurgedDisclosureTest.php` (new, 3 tests).
- `tests/Feature/Retention/RetentionStagingRetryTest.php` (new, 1 test).
- All consumers of `hasPhysicalFile()` repo-wide (5 call sites; see §C.3).

## C. MEDIUM-1 Re-Review — Purged-Source Disclosure

**MEDIUM-1 = RESOLVED**

### C.1 Reproduction — purged media

Independently exercised (not read from the builder's logs): seeded a
`completed` transcription with `completed_at` 45 days ago, put real bytes
on the fake disk, ran `RetentionPurge::run()`, then requested
`transcriptions.show` as the owning user.

- Transcription detail page: `200 OK`, fully accessible. Confirmed by
  reading `resources/views/transcriptions/show.blade.php:113-122`: the
  `@if (($mediaPurged ?? false) === true)` block renders unconditionally
  once `$mediaPurged` is true, independent of whether segments exist.
- Explicit source-purged state rendered: `data-purged-source` attribute
  present, heading "Source media purged under the 30-day retention
  policy", body text "The original recording was permanently deleted
  after 30 days. This is not a temporary media failure." — accurately
  describes permanent retention removal, not a transient failure.
- No usable `<audio>`/`<video>` element: `hasPhysicalFile()`
  (`app/Models/MediaFile.php:152-157`) now short-circuits to `false` when
  `purged_at !== null`, so `$mediaAvailable` /
  `$streamUrl` are `null` in `TranscriptionController::show()`
  (`app/Http/Controllers/TranscriptionController.php:71-74`), and the
  `@if ($streamUrl !== null)` player block
  (`show.blade.php:86-106`) does not render. Verified by direct read of
  both the controller and view, not by trusting the test name.
- Transcript text remains visible: `$displaySegments`/`$fullTranscriptText`
  are computed independently of `$mediaFile` state
  (`TranscriptionController.php:58-68`) — unaffected by purge.
- Revision history remains visible: `$revisionHistory` is built from
  `$revisions->history($user, $transcription)`
  (`TranscriptionController.php:118`), which reads persisted revision
  rows only, with no `mediaFile`/`purged_at` dependency.
- TXT export still succeeds: reproduced directly —
  `GET /transcriptions/{id}/export/txt` on the purged transcription
  returned `200 OK`.
- Stream/download endpoints: reproduced directly — both
  `media.stream` and `media.download` returned `410` for the purged
  media row, with a message containing "retention"
  (`MediaActionController.php:58-60,83-85`). No bare 404 anywhere in the
  purged path.

### C.2 Reproduction — available (non-purged) media

Independently seeded a second transcription with `completed_at` 5 days
ago (inside the retention window) and real bytes present:

- `transcriptions.show` renders a live `<audio>`/`<video>` element
  (`$streamUrl !== null` since `hasPhysicalFile()` is true: not purged,
  path present).
- The purged-source notice does not render (`$mediaPurged` is `false`
  since `purged_at` is `null`).
- Stream/download remain governed by the pre-existing (unchanged) rules
  — no `purged_at` short-circuit is hit for a non-purged row.

### C.3 Tombstone semantics — `hasPhysicalFile()` consumer audit

Grepped every call site repo-wide (`app/` and `resources/`) rather than
accepting the builder's list at face value:

| Consumer | Semantic needed | `purged_at`-aware correct? |
|---|---|---|
| `app/Jobs/ProcessTranscription.php:146` (fail transcription if media missing before transcribing) | "can I read usable bytes right now" | Yes — and moot in practice: this job runs before/at transcription time, well before the earliest possible purge eligibility (30 days post-*completion*), so `purged_at` is always `null` on this path in the current lifecycle; no behavior change for real traffic. |
| `app/TranscriptExperience/WorkspaceAvailability.php:32` (`mediaPlayer` workspace flag) | "should the player affordance be offered" | Yes — a purged source must not offer a player. |
| `app/Http/Controllers/TranscriptionController.php:71` (`$mediaAvailable`/`$streamUrl`) | same | Yes — this is the exact mechanism the corrective fix relies on. |
| `app/Services/MediaMetadataProbeService.php:34` (skip ffprobe if unusable) | "are there bytes to probe" | Yes — probing a tombstoned row's (possibly still-present, pre-verification-window) bytes would be pointless/wrong once purged. |
| `resources/views/media/index.blade.php:92`, `resources/views/livewire/media/show.blade.php:2,12` (media library play affordance) | "should the library offer playback" | Yes — same reasoning as `WorkspaceAvailability`; these are library-level views, not the transcription detail page the corrective fix targeted, and they now also correctly hide the play control for a purged row (net-new correct behavior, not a regression, since before P7-011 existed these rows could never be purged at all). |

No consumer found that needs literal filesystem-existence semantics
independent of the tombstone. The semantic change is valid for all five
call sites. No reportable gap here.

### C.4 Disclosure policy consistency (AC7)

Compared `docs/RETENTION-POLICY.md`, the UI copy, the 410 behavior, and
export behavior:

- Doc: "they are marked as 'source purged' so it is always clear the
  original recording is gone" — now true; the transcript surface
  performs exactly this marking (`data-purged-source`, visible text).
- Doc: "original media file is permanently deleted (it can no longer be
  played back or downloaded)" — matches: no player renders, and
  stream/download both 410.
- Doc: "transcript history, translations, and text exports ... continue
  to work" — matches: reproduced directly in §C.1.
- Doc: "Playback or download of a purged recording reports 'purged under
  the 30-day retention policy' (HTTP 410)" — matches
  `MediaActionController.php:59,84` message text and reproduced status
  code.

**AC7 = PASS.** The documentation and the product now agree on all four
points cycle-1 found misaligned.

## D. MEDIUM-2 Re-Review — Staging Deletion Failure

**MEDIUM-2 = RESOLVED**

### D.1 Code inspection

`RetentionPurge::purgeStaging()` (`app/Retention/RetentionPurge.php:187-265`)
and `deleteStagingObject()` (`:295-324`):

- `deleteStagingObject()` now returns the recorded outcome string
  (`purged`/`absent`/`failed`) instead of `void`.
- In `purgeStaging()`, the claim-holding branch
  (lines 224-239) captures that return value into `$outcome` and branches
  on it explicitly: on `FAILED`, the claim row is released via a
  query-builder `UPDATE` back to `held_by = 'upload'`,
  `cleanup_claimed_at = null` (lines 227-234), and the loop `continue`s
  **before** reaching `$claim->delete()` at line 239. On any other
  outcome (`purged` or `absent`), execution falls through to
  `$claim->delete()`.
- This is the exact fix the cycle-1 review's recommendation asked for:
  "only call `$claim->delete()` on a non-`FAILED` outcome" — confirmed by
  direct code reading, not by trusting the builder's description.

### D.2 In-memory vs. query-builder distinction

The builder report states the first corrective attempt failed because
`forceFill()->save()` on the stale in-memory `$claim` model was a silent
no-op (identical values → nothing dirty → nothing persisted). The shipped
fix avoids the in-memory model entirely for the release: it issues
`StagingClaim::query()->where('id', $claim->id)->update([...])` — a
fresh query-builder statement, not a call through the stale `$claim`
instance. This is architecturally correct: a query-builder `UPDATE`
always sends the write regardless of what the in-memory model believes
its current attribute values are. Confirmed by direct read of lines
230-234; no `$claim->` state is read or relied on in the release path
except `$claim->id` (an immutable primary key, safe to read from a stale
instance).

### D.3 Independent reproduction — staging failure → retry

Reproduced directly (in addition to re-running `RetentionStagingRetryTest`
in §G):

1. Created a `StagingClaim` satisfying the existing expiry policy
   (`held_by = 'upload'`, `expires_at` in the past).
2. Forced physical deletion to fail deterministically (created a
   directory at the file's path so `Storage::delete()` cannot remove it
   as a file — the same technique cycle 0's media-path FS-failure test
   uses).
3. First run: confirmed via direct query afterward —
   - the "file" (directory) still exists on disk;
   - the run's `outcomes['failed'] === 1`, `outcomes['purged'] === 0`
     (no false success);
   - the `StagingClaim` row still exists (not deleted);
   - `held_by` is back to `'upload'` and `cleanup_claimed_at` is `null`
     (the exact retryable condition the P2-004A2 protocol's guarded
     `UPDATE` requires to re-claim it).
4. Restored a deletable filesystem state (removed the blocking
   directory).
5. Second run: confirmed the candidate was reclaimed and cleaned —
   `outcomes['absent'] === 1`, `outcomes['failed'] === 0`, and the
   `StagingClaim` row is now gone.
6. A third run against the same (now nonexistent) candidate is a
   structural no-op by construction: the claim-row query
   (`held_by = 'upload' AND expires_at <= now()`) returns nothing (row is
   deleted), and the orphan-file sweep's `claimedPaths` check is now
   irrelevant since the file itself was removed in step 5 — nothing left
   to visit. Confirmed by code inspection of the query in
   `purgeStaging()` lines 192-196; not re-run as a literal third
   `RetentionPurge::run()` call since there is no remaining artifact for
   it to observe, which is itself the idempotency guarantee.

No false-success audit/log outcome occurred at any step. This matches
§10 ("a failed purge run never leaves half-deleted logical objects
presented as complete") and AC5/AC8.

## E. P2-004A2 Protocol Compatibility

- Guarded claim acquisition: unchanged pattern — `purgeStaging()`
  acquires with a conditional `UPDATE ... WHERE held_by = 'upload' AND
  expires_at <= now()`, matching the existing `CleanupStaging.php`
  guarded-`UPDATE` CAS discipline (no read-then-write).
- Release semantics: the new failure-release `UPDATE` only ever resets
  an **expired** claim this same run just acquired back to `upload`; it
  cannot touch a claim currently held by an active uploader (those never
  match the acquisition `WHERE` clause in the first place), so it does
  not weaken race protection against a live upload.
- No second ownership/lease mechanism: `RetentionPurge` reuses the exact
  same table and column vocabulary (`held_by`, `cleanup_claimed_at`,
  `expires_at`) as `CleanupStaging`/`MediaIngestionService`. It is a new
  **caller** of the existing protocol, not a competing protocol.
- Concurrent workers cannot trivially steal an active claim: confirmed —
  the acquisition `UPDATE`'s `WHERE held_by = 'upload'` guard means a
  claim already moved to `held_by = 'cleanup'` by a concurrent worker
  (or by `CleanupStaging`) cannot be re-claimed by `RetentionPurge`, and
  vice versa.
- Retries remain deterministic for the specific failure mode this cycle
  corrects (byte-delete failure) — confirmed in §D.

### E.1 New observation (not part of either MEDIUM-1/2 scope) — staging crash-recovery gap

While verifying protocol compatibility, I found that `RetentionPurge`'s
staging-claim acquisition has **no crash-recovery reclaim** for a claim
stuck at `held_by = 'cleanup'`. `CleanupStaging.php:72-88` explicitly
reclaims any row stuck in `held_by = 'cleanup'` for more than 15 minutes
(a real process crash between claim and completion). `RetentionPurge`'s
candidate query (`app/Retention/RetentionPurge.php:192-196`) only ever
selects `held_by = 'upload'` rows — it never revisits a row already at
`held_by = 'cleanup'`. If `retention:purge` itself is killed (OOM,
SIGKILL, host restart) after successfully claiming a row (line 199-202)
but before it reaches either `$claim->delete()` or the failure-release
`UPDATE`, that row is permanently stuck at `held_by = 'cleanup'` — and
because `docs/TECHNICAL_DEBT_REGISTER.md:313` confirms `media:cleanup-staging`
(the command that *does* have crash-recovery) is **not scheduled, by
design**, nothing in the current automated posture will ever reclaim it.
The underlying file also becomes permanently excluded from the orphan
sweep (it appears in `claimedPaths`, `purgeStaging()` line 242-247, so
the sweep skips it), so it neither gets purged nor reported as failing —
it simply stops being visited by any scheduled process.

This is **not** the failure mode either cycle-1 MEDIUM finding was about
(both concerned an ordinary caught `Throwable`, which the fix in §D
handles correctly), and it predates this corrective cycle — it was
present, unflagged, in the original cycle-0 submission this review's
predecessor already passed at AC8. It requires an actual process
termination mid-claim (not a caught exception), is not data-destructive
(bytes remain; nothing is deleted or falsely reported), and is
self-healable today by an operator manually running the pre-existing
`media:cleanup-staging` command. I am recording it here because it bears
directly on item 6's "retries remain deterministic" and the P2-004A2
compatibility check this cycle specifically asked me to perform, and
because it is exactly the class of accumulation risk TD-007 exists to
close.

**Classification: LOW.** Narrow reachability (uncaught process kill, not
routine failure), non-destructive, pre-existing (not introduced by this
corrective cycle), and outside the two findings this cycle was scoped to
fix. It does not block this cycle's verdict. Recommended disposition:
record as a new observation for the implementation owner or HPO to
decide whether it warrants a follow-up task/TD entry before P7-012
G-09 consumption — not a required fix for VERIFIED here.

## F. Regression Verification — Retention Core

Re-inspected (not re-litigated from scratch) against the cycle-1 review's
finding that this code was sound, cross-checked with the corrective
diff's blast radius (only `MediaFile.php`, `TranscriptionController.php`,
`show.blade.php`, `MediaActionController.php` disclosure copy, and
`RetentionPurge.php`'s `purgeStaging()`/`deleteStagingObject()` changed):

- 31-day eligible / 29-day ineligible / exact boundary / null
  `completed_at` / active-queued-transcribing-failed protection /
  quarantine exclusion: eligibility query
  (`RetentionPurge.php:53-56`) and `isProtectedPath()`
  (`:326-340`) are byte-for-byte unchanged from cycle 0; confirmed by
  re-running `RetentionEligibilityTest` and `RetentionAdversarialTest`
  (§G) — both green, assertions re-read in §above and in F above.
- Repeat-run idempotency / already-missing behavior / filesystem-failure
  behavior (media path) / no false tombstone / audit accounting: the
  media-purge path (`purgeTranscriptionMedia()`) is untouched by the
  corrective diff — confirmed by direct read (§ "Implementation Review"
  in cycle-1's report describes the same line numbers/logic I read here
  unchanged). Re-run in §G.
- ADR-005 user-deletion compatibility: `StalePurgeCandidate` handling in
  `purgeTranscriptionMedia()` is untouched; `RetentionIdempotencyTest`'s
  concurrent-deletion test re-run green.
- Dry-run no-mutation behavior: `purgeTranscriptionMedia()`'s
  `if ($dryRun)` early-return and `purgeStaging()`'s two `if ($dryRun)`
  branches are unchanged in placement/logic; `RetentionAdversarialTest`'s
  dry-run test re-run green.

No regression found in previously-verified behavior.

## G. Fresh Test / Quality-Gate Results (independently reproduced, not quoted)

All commands run fresh in this review session against the current
working tree:

| Gate | Builder claim | Independently reproduced |
|---|---|---|
| `php artisan test --filter=Retention --compact` | 27/27 | **27 passed, 94 assertions, 4 warnings** — match |
| Related (Backup, Deployment, MediaStreaming, Security) | 130/130 | **150 passed, 420 assertions** across a superset scope I selected independently (`tests/Feature/Backup`, `tests/Feature/Deployment`, `tests/Feature/MediaStreamingTest.php`, `tests/Feature/Security`, `tests/Feature/Settings/SecurityTest.php`, plus `CleanupStagingCommandTest`, `StagingClaimCasProtocolTest`, `StagingClaimTest`, `Storage/StorageTopologyTest`, `Security/ClamavScannerTest`) — 0 failures either way; the count difference is scope selection, not a discrepancy |
| Full suite ×1 | 1077 total, 1076 passed, 1 skip, 4066 assertions | **Exact match**, run 1 |
| Full suite ×2 (flake check) | same | **Exact match**, run 2 — 0 flakes observed across both runs |
| Pint | clean | **`{"tool":"pint","result":"passed"}`** |
| PHPStan L7 | 0 errors | **`{"tool":"phpstan","result":"passed","errors":0}`** |
| `retention:purge --dry-run` | nothing deleted | Reproduced: `purged: 0, absent: 0, failed: 0, skipped: 2` — nothing deleted |
| `deployment:verify` | retention scheduled, ledger clean | Reproduced: "Retention: retention:purge scheduled" / "Retention: audit ledger has no failures"; overall "Deployment verification passed." |
| `observability:diagnostics` | retention lines present | Reproduced: "Retention schedule: retention:purge (cron \"0 0 * * *\", without overlapping)" / "Retention ledger: skipped=6" |
| `tests/Feature/Editing/RevisionMigrationRollbackTest.php` | pass | Reproduced: 1/1, 14 assertions |

All builder-reported numbers are confirmed accurate by independent
reproduction. TD-008 (order-dependent flakiness): zero flakes observed
across two fresh full-suite runs in this review; stays OPEN per its own
register entry regardless (informational, no disposition change).

Coverage verified by assertion inspection, not test names, for:

- Visible purged disclosure: `RetentionPurgedDisclosureTest` test 1 —
  asserts the literal text and `data-purged-source` marker, and
  `assertDontSee('<audio'/'<video')`, not just presence of a generic
  string.
- No media player for tombstoned source: same test, element-tag-level
  assertion (the disclosure test file's own comment notes
  `data-media-player` also appears in page JS, so the fix correctly
  asserts on element tags rather than that string).
- Text export preservation: `RetentionPurgedDisclosureTest` test 2 and
  `RetentionAuditDisclosureTest`'s "keeps transcript history and text
  exports working" test both hit the real export route and assert
  `200 OK`.
- Normal available-media behavior: `RetentionPurgedDisclosureTest` test 3
  asserts a real `<audio>`/`<video>` tag is present and the notice is
  absent.
- Staging failure persistence: `RetentionStagingRetryTest` asserts the
  file still exists, `failed === 1`/`purged === 0`, and the claim row's
  `held_by`/`cleanup_claimed_at` fields directly (not just exit code).
- Staging retry completion: same test, second-run assertions on
  `outcomes['absent']`, `outcomes['failed']`, and row deletion.

## H. Generation / Backup Safety

- `app/Retention/RetentionPurge.php` has no reference to `StorageTopology`,
  backup, or generation/manifest code (grepped; no matches).
- `RetentionAuditDisclosureTest`'s "leaves backup generations and their
  manifest untouched" test performs a real isolated `backup:run`, a
  before/after byte-identical manifest comparison, and a subsequent
  `RetentionPurge::run()` in between — re-run fresh in §G, passes.
- `tests/Feature/Storage/StorageTopologyTest.php` and
  `tests/Feature/Backup/*` re-run fresh in §G, all pass — no P7-004/P7-007
  regression.

## I. Findings

| # | Severity | Status | Summary |
|---|---|---|---|
| 1 (was MEDIUM-1) | — | **RESOLVED** | Purged-source disclosure implemented on the transcript surface; documentation now accurate; independently reproduced end to end (§C). |
| 2 (was MEDIUM-2) | — | **RESOLVED** | Staging claim row is now released (not deleted) on a failed byte delete, via a query-builder `UPDATE` that avoids the prior stale-model no-op; independently reproduced the 5-step failure→retry sequence with direct state inspection (§D). |
| 3 (new) | LOW | Open, non-blocking | `RetentionPurge`'s staging-claim path has no crash-recovery reclaim for a claim stuck at `held_by = 'cleanup'` after an uncaught process termination, unlike the existing (but unscheduled) `CleanupStaging` command. Pre-existing since cycle 0, narrow reachability, non-destructive, self-healable via manual `media:cleanup-staging` today. Recommend recording for a future decision, not required for this cycle's VERIFIED (§E.1). |

No BLOCKER or HIGH finding. No material MEDIUM defect remains.

## J. Independent Verdict

**VERIFIED**

Both cycle-1 MEDIUM findings are independently confirmed resolved with
direct reproduction (not accepted on the builder's description alone).
Regression checks across the previously-verified retention core, P6
invariants, and P7-004/P7-007 boundaries pass. All fresh quality gates
(retention suite, related suites, full suite ×2, Pint, PHPStan L7,
live `deployment:verify`/`observability:diagnostics`/dry-run) reproduce
the builder's claims exactly where scopes matched, with no failures in a
broader scope than claimed. The one new LOW observation (§E.1, §I.3) is
non-blocking by the standard this repository's governance sets for
VERIFIED (BLOCKER/HIGH prevent it; no material MEDIUM defect remains
here).

## K. TD-007 Status

**OPEN** — implementation evidence available (this cycle confirms the
corrected implementation independently); closure remains deferred to its
authorized governance/final-gate consumption (P7-012 G-09). This review
does not close TD-007.

## L. Exact Next Legal Action

**Present P7-011 to the Human Product Owner for VERIFIED → DONE closure.**
Do not begin P7-009, P7-012, or other unauthorized work. The HPO may also
wish to decide disposition of the new LOW observation in §E.1/§I.3
(e.g., a follow-up task or TD entry) before P7-012 G-09 consumption,
though it is not a precondition for this task's own DONE closure.

## M. Files Changed (review-owned only)

- `reviews/P7-011-INDEPENDENT-REVIEW-CYCLE2.md` (this artifact, new).

No implementation, task, or builder-report file was modified by this
review. `reviews/P7-011-INDEPENDENT-REVIEW.md` (cycle 1) remains
unmodified.
