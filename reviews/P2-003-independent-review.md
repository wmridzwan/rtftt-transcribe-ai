# P2-003 — Independent Review

Reviewer: Claude Code
Task: tasks/P2-003-implement-real-upload-ingestion-workflow.md
Implementation Owner: Codex

## Current Verdict (Third Pass, 2026-09-13)

VERIFIED

See "## Third Pass — Post Second Remediation" below for this pass's full
findings. The second-pass verdict (CHANGES_REQUESTED) that follows in this
document is preserved as historical record of the review that produced the
two required fixes; it is superseded by the Third Pass section.

## Second Pass Verdict (superseded — see Third Pass section below)

CHANGES_REQUESTED

This does not authorize P2-004, Phase 3, or any later work. This does not mark
P2-003 DONE. Task status in the repository is not modified by this review;
Work routes the task back to the current implementation owner per the
State-to-Action Contract (`.ai/guidelines/orchestration-policy.md`).

## Scope

This is the second independent review of P2-003. The first review
(recorded only in conversation, not as a prior file — see Known Gap below)
returned CHANGES_REQUESTED for five missing automated test-coverage gaps:
promotion-failure compensation, persistence-failure compensation, ambiguous
duplicate-key retry recovery, missing-file submission, and unauthenticated
access rejection. The implementation owner reports all five remediated in
`tests/Feature/MediaIngestionTest.php` (16 tests / 92 assertions, up from 11 /
65). This review independently verifies that remediation.

## Evidence Independently Reproduced

Commands executed directly against the live repository (not taken on the
implementer's report alone):

- `php artisan test --compact tests/Feature/MediaIngestionTest.php` →
  `16 passed / 92 assertions` — matches report.
- `php artisan test --compact` (full suite) →
  `185 passed / 12 skipped / 564 assertions` — matches report.
- `vendor/bin/pint --test --format agent` → `passed`.
- `vendor/bin/phpstan analyse --no-progress` → `0 errors`.

No files were staged, committed, or modified by this review. `git status`
before and after this review shows an identical file set.

## Findings

### HIGH — Two of the five reported remediation tests do not exercise the code path they claim to test

**Finding 1: "persistence failure compensation" test never reaches persistence.**

Evidence: `tests/Feature/MediaIngestionTest.php:259-280` submits
`folder_id => 999999` to force what its own comment calls a "database INSERT
fails" scenario. But `MediaUploadController::store()`
(`app/Http/Controllers/MediaUploadController.php:64-71`) validates
`folder_id` via `Rule::exists('folders', 'id')->where('user_id', ...)` inside
`$request->validate()`, which runs and throws `ValidationException` *before*
`MediaIngestionService::ingest()` is ever called. Staging, checksum,
promotion, and the database transaction in `ingest()`
(`app/Actions/MediaIngestionService.php:81-172`) are never reached. The
test's passing assertions (`assertSessionHasErrors('folder_id')`, zero
`MediaFile` rows, empty staging) are true because Laravel's front-door
validation rejected the request — the same code path the pre-existing
"folder association is limited to folders owned by the authenticated user"
test already covers. The `ingest()` transaction-failure/compensation branch
(lines ~118–145: promote succeeds, `$this->database->transaction()` throws
for a reason other than a recoverable duplicate-key match, durable object and
staging must be cleaned up) remains completely untested.

**Finding 2: "ambiguous duplicate key retry" test never reaches the service's internal race-recovery branch.**

Evidence: `tests/Feature/MediaIngestionTest.php:282-306` pre-inserts a
`MediaFile` with the same `(user_id, upload_attempt_id)` before posting the
upload. But `MediaUploadController::store()` performs
`$existing = $ingestion->findByAttempt($request->user(), $attemptId); if ($existing !== null) return $this->successResponse(...)`
(`app/Http/Controllers/MediaUploadController.php:52-56`) *before* any file
validation or call into `ingest()`. Because the row already exists at the
time the controller runs this check, the request short-circuits immediately
and returns the existing record — identical in mechanism to the pre-existing
"retrying one upload attempt returns the committed media file" test, just
constructed by pre-seeding the row instead of a prior real request. The
actual target — `ingest()`'s inner `catch` block
(`app/Actions/MediaIngestionService.php:118-158`), which handles a **genuine
race** where `findByAttempt` returns null, staging/promotion/checksum all
proceed, and only then does the database insert fail with a duplicate-key
violation because a concurrent request won the race — is never invoked or
tested.

**Impact:** By code inspection both branches in `MediaIngestionService::ingest()`
still appear logically correct (this review re-traced them again this pass),
but this is exactly the data-integrity-risk code ADR-009/P2-002B exists to
govern, and it remains unverified by any automated test after two review
cycles. A latent defect in either branch (e.g., an orphaned durable object on
genuine persistence failure, or a duplicate committed row on a real
concurrent-insert race) would not be caught by the current suite.

**Required Correction:** Replace both tests with ones that genuinely reach
the target branch:
- Persistence failure: force the *database insert itself* to fail after
  promotion succeeds, without pre-failing validation — e.g., mock/swap the
  `DatabaseManager` (as the implementer's own remediation notes describe
  attempting) so `transaction()` throws only once file validation and
  `folder_id` validation have already passed, or drop/rename a required
  column on a throwaway connection, or use a valid `folder_id` and inject the
  failure at the model-save layer. The existing Mockery pattern used for the
  (correctly implemented) promotion-failure test is a good template.
- Ambiguous retry: insert the conflicting `MediaFile` row **after** the
  controller's `findByAttempt` pre-check would return null but **before**
  the service's own transaction commits — in practice this means testing
  `MediaIngestionService::ingest()` directly (not through the controller),
  inserting the conflicting row from inside a hook/callback timed to run
  between the service's own `findByAttempt` short-circuit
  (`app/Actions/MediaIngestionService.php:83-87`) and its transaction, or
  refactoring the test to bypass the controller's redundant early check for
  this one scenario. The goal is to prove the code at lines 146-158 executes,
  not just that *some* code path returns the existing record.

### CONFIRMED GOOD — Three of the five remediation items are genuinely fixed

1. **Promotion-failure compensation**
   (`tests/Feature/MediaIngestionTest.php:202-257`): well-constructed —
   mocks `FilesystemAdapter::put()` to throw specifically for the durable
   (non-staging) path while delegating staging/read/delete operations to the
   real faked disk, with a `->once()` call-count constraint. This correctly
   exercises `MediaIngestionService::ingest()`'s outer catch/compensation
   path and genuinely proves no orphaned commit and cleaned-up staging.
2. **Missing-file submission** (`:308-320`): correctly omits `media_file`
   entirely and asserts the validation error the controller's
   `uploadedFileCount()` guard produces.
3. **Unauthenticated access rejection** (`:322-326`): correctly asserts both
   `GET media.upload` and `POST media.upload.store` redirect to login for a
   guest.

### INFO — Prior review artifact was not persisted to `reviews/`

The first P2-003 review (which produced the original CHANGES_REQUESTED
verdict this remediation responds to) was delivered only as conversational
output and was never written to `reviews/`, leaving
`tasks/P2-003-implement-real-upload-ingestion-workflow.md`'s own "Review
File: None yet" pointer stale even after that review occurred. This review
file is the first durable artifact for P2-003. Future review cycles for this
task should reference this file.

## Unchanged From First Review (Still Valid)

All findings from the first review pass that are not about the five
remediation items remain valid and are not re-litigated in depth here:
upload transport, 500 MiB boundary, MIME/extension matrix, checksum
generation, private/opaque storage, ownership/authorization, migration
safety, navigation, UI progress reporting, and Phase-boundary compliance were
all independently verified correct in the first pass and are unaffected by
this remediation diff.

## Final Recommendation (Second Pass — superseded, see below)

P2-003 requires changes before verification. Two of the five test additions
need to be rebuilt so they actually exercise the code paths they claim to
cover (persistence-failure compensation and the service-level ambiguous
duplicate-key race). The underlying application logic is not shown to be
defective — it simply remains unverified in exactly the areas ADR-009 was
written to protect. Do not begin P2-004 or Phase 3. Do not close P2-003
without a subsequent VERIFIED review.

---

## Third Pass — Post Second Remediation (2026-09-13)

Scope: re-review of the two tests rewritten in response to the second-pass
findings above — `persistence failure compensation removes promoted durable
object and staging` and `ambiguous duplicate key retry returns existing media
without creating a second record` — plus independent reproduction of all
regression commands and a state/boundary check.

### Verdict

VERIFIED

P2-003 may be closed by Work after repository state updates.

### Finding 1 — Persistence-failure compensation: RESOLVED

Evidence: `tests/Feature/MediaIngestionTest.php:260-304`. The rewritten test
calls `app(MediaIngestionService::class)->ingest($user, $file, $attemptId)`
directly — the controller is never involved. Storage is mocked to delegate
every operation to the real faked disk (so staging and durable promotion both
genuinely succeed via `stage()` → `checksum()` → `copy()`), and only
`DatabaseManager::transaction()` is mocked to throw
(`Mockery::mock(DatabaseManager::class)->shouldReceive('transaction')->once()->andThrow(...)`),
bound into the container via `$this->app->instance(DatabaseManager::class, $database)`
before the service is resolved. Traced against
`app/Actions/MediaIngestionService.php:81-172`: the inner `catch` runs
`findByAttempt`, finds nothing (no conflicting row exists in this scenario),
and re-throws; the **outer** `catch` (lines 164-171) then runs because
`$committed` is still `false`, calling `deleteStaging()` and
`deleteOrLog($storage, $durablePath, 'failed upload attempt')` — both mocked
to delegate to the real disk, so the already-promoted durable file (proven to
exist, since promotion genuinely succeeded before the mocked failure) is
actually deleted. The test asserts all three outcomes directly: zero
`MediaFile` rows, empty staging (`$realDisk->allFiles('media/.staging')`),
and an empty durable directory (`$realDisk->allFiles('media')` filtered for
non-staging paths). This is a faithful, non-trivial exercise of the outer
compensation path with durable promotion genuinely completed first. No
remaining gap.

### Finding 2 — Ambiguous duplicate-key retry: RESOLVED (with one LOW gap)

Evidence: `tests/Feature/MediaIngestionTest.php:306-359`. The rewritten test
also calls `ingest()` directly, bypassing the controller's early
`findByAttempt` short-circuit entirely — this was the specific defect in the
prior attempt. `DatabaseManager::transaction()` is mocked so that, when
called, it first inserts a "concurrent" `MediaFile` row with the same
`(user_id, upload_attempt_id)` via `MediaFile::factory()->create(...)`, then
invokes the real closure. Because the real migration
(`database/migrations/2026_09_13_120000_add_upload_attempt_id_to_media_files_table.php`)
enforces a genuine unique index (`media_files_user_attempt_unique`) on that
pair, the closure's own `$mediaFile->...->save()` triggers a real database
unique-constraint violation — not a contrived exception — which is caught by
`ingest()`'s **inner** `catch` (lines 146-158). At that point `findByAttempt`
now finds the just-inserted conflicting row, so the recovery branch runs:
`deleteOrLog` on the duplicate durable object, `deleteStaging`, and returns
the existing record. The test asserts the returned record matches the
pre-existing row's id, exactly one `MediaFile` row exists, staging is empty,
and no `Transcription`/`ProcessingJob` side effects occurred. This is a
faithful, realistic simulation of the actual race the contract is designed
to survive.

**LOW gap:** unlike the persistence-failure test, this test does not assert
that the durable ("media/", non-staging) directory is empty of the duplicate
promoted object — only that staging is empty. The mocked `delete()` method
also has no call-count expectation (`->once()`/`->twice()`), so a regression
that silently dropped the `deleteOrLog` call in the inner catch branch would
not be caught by this test. The service logic is correct today (confirmed by
trace), but the test's assertion coverage is asymmetric with its sibling
test. Not blocking — does not prevent VERIFIED — but worth tightening in a
future pass: add `expect(array_filter($realDisk->allFiles('media'), fn ($p) => ! str_contains($p, '.staging')))->toBe([$conflictingRow-relevant path or empty as appropriate])` or a `shouldReceive('delete')->once()` constraint.

### Regression Commands — Independently Reproduced

| Command | Reported | Reproduced |
|---|---|---|
| `tests/Feature/MediaIngestionTest.php` | 16 passed / 94 assertions | 16 passed / 94 assertions — match |
| Full suite | 185 passed / 12 skipped / 566 assertions | 185 passed / 12 skipped / 566 assertions — match |
| `vendor/bin/pint --test --format agent` (reviewer preferred over implementer's `--dirty`) | — | passed |
| `vendor/bin/phpstan analyse --no-progress` | 0 errors | 0 errors — match |
| `npm run build` | passed, fontaine advisory only | passed, identical advisory — match |

`git status` and `git diff --stat` were also reproduced; the implementation
surface (`app/`, `database/`, `resources/`, `routes/`, `config/`) is
byte-identical in line counts to the first and second review passes — only
`tests/Feature/MediaIngestionTest.php` and the task file changed in this
remediation. No files were staged, committed, or modified by this review
beyond this review artifact itself.

### State and Boundary Check

- P2-003 task file `## Status` remains `REVIEW`; not marked VERIFIED or DONE
  by the implementation owner (`tasks/P2-003-implement-real-upload-ingestion-workflow.md:3-10`).
- `CURRENT_STATE.md` correctly reflects `REVIEW`/awaiting-independent-review
  status and does not claim closure.
- No `tasks/P2-004*` (or later) files exist; no FFmpeg/Phase 3 artifacts
  found anywhere in the repository.
- No unrelated implementation behavior changed: diff stat for `app/`,
  `database/`, `resources/`, `routes/`, `config/` is unchanged from the
  first and second review passes.
- This review artifact (`reviews/P2-003-independent-review.md`) is updated
  by this same pass to remain consistent with the current review state; no
  further follow-up update is needed unless a future pass changes the
  verdict.

### Final Recommendation

P2-003 may proceed to closure by Work after repository state updates
(updating `plan.md`/`CURRENT_STATE.md`/the task file's Status and Review
sections to VERIFIED/closure per the State-to-Action Contract). This review
does not authorize P2-004, P2-005, P2-006, P2-007, or Phase 3, and does not
itself mark the task DONE.
