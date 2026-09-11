# TASK-002 Review — Align Media Lifecycle and Display Names

Reviewer: Claude Code
Date: 2026-09-11
Task: tasks/TASK-002-align-media-lifecycle-and-display-names.md
Implementation Owner: Codex

## Verdict

VERIFIED — no BLOCKER or HIGH findings. One MEDIUM finding recorded below (non-blocking); one LOW/process finding on verification completeness.

## Scope of Review

Independently inspected, without modifying implementation code:

- `git diff` for `app/Models/MediaFile.php`, `app/Http/Controllers/MediaActionController.php`, `app/Livewire/Media/Show.php`, `resources/views/livewire/media/show.blade.php`, `tests/Feature/MediaManagementTest.php`.
- `DECISIONS.md` ADR-005, `app/Policies/MediaFilePolicy.php`, migrations for `transcriptions`, `transcription_segments`, `processing_jobs`.
- `resources/views/media/show.blade.php`, `resources/views/media/index.blade.php`, `resources/views/folders/show.blade.php`, `app/Http/Controllers/MediaController.php`, `routes/web.php` to establish which paths are actually live.
- A standalone forensic script (run outside any tracked file, no implementation code touched) to empirically verify the pre-fix and post-fix `display_name` accessor behavior against real Eloquent internals rather than reasoning from the diff alone.

## Independent Verification (re-run, not reused from the task file)

- `php artisan test --compact tests/Feature/MediaManagementTest.php` → 28 passed, 89 assertions. Matches the task's claim.
- `php artisan test --compact` (full suite) → 130 tests, 129 passed, 1 skipped (pre-existing, intentional 2FA skip), 318 assertions, **0 failures**. No regressions.
- `vendor/bin/pint --test --format agent` on all five changed files → passed.
- `vendor/bin/phpstan analyse app/Models/MediaFile.php app/Http/Controllers/MediaActionController.php app/Livewire/Media/Show.php` → the same three issues already disclosed for TASK-001 (unchanged), **plus four additional pre-existing generic-type findings in `MediaFile.php`** (missing generics on `HasFactory`, `belongsTo`/`hasMany` return types) that the task's own verification did not surface because its recorded PHPStan command omitted `MediaFile.php` even though it is listed as changed. Confirmed by line number that none of these four findings touch the modified accessor (they're on the trait-use line and the three relation methods, all untouched by this diff) — pre-existing and out of this task's scope, but the task's "no findings in the changed logic" claim is incomplete since it never actually analyzed the file containing the fix. See Finding 2 below.

## ADR-005 Compliance

ADR-005 requires: media may be deleted even with transcriptions, but deletion must require **explicit cascade confirmation** that clearly informs the user transcriptions will also be deleted.

- Controller: `confirm_cascade` is validated as `['accepted']` only when `$mediaFile->transcriptions()->exists()` is true (a fresh query at request time); otherwise `['nullable','boolean']`. Confirmed via test and direct code read.
- Livewire: `confirmCascade` is validated as `['accepted']` only when `$this->mediaFile->transcriptions->isNotEmpty()` is true; otherwise `['nullable','boolean']`.
- The delete modal (`resources/views/livewire/media/show.blade.php:127-138`) clearly states the transcription count and explicitly warns "Deleting it will also delete all associated transcriptions," backed by a required `confirmCascade` checkbox with an affirmative label. Verified by a passing content-assertion test.
- **This closes a real, pre-existing ADR-005 violation, not just a hypothetical one.** Diffing the Livewire `destroy()` method shows the *original* code had no transcription check or confirmation gate at all — it deleted unconditionally. Only the controller previously blocked (with a plain error, not a cascade option) deletion when transcriptions existed. That divergence is exactly the "conflicting media deletion behavior identified in the Phase 1 audit" ADR-005 was written to resolve, and the fix genuinely resolves it for the common case (see Finding 1 for the one remaining edge case).

## MediaFile `display_name` Accessor — Forensic Verification

This was the highest-value part of the review since the bug is subtle (self-referential access inside its own accessor) and not obviously broken from a code read alone.

- I wrote a standalone script (outside the repo's tracked files) that exercises real Eloquent internals to trace exactly what the **original** accessor (`return $this->display_name ?? $this->original_filename;`) does. It is not infinite recursion (PHP's `??` isset-check on the property bottoms out after one extra recursive call via `Model::offsetExists()`), but it **always resolves to `original_filename`**, even when `display_name` is persisted with a real value. Traced and confirmed empirically:
  - `display_name` unset → returns `original_filename` (looks correct, coincidentally).
  - `display_name` set to `'Custom Name'` → **still returns `original_filename`**, silently discarding the persisted value.
- I then verified the **fixed** accessor (`return $this->attributes['display_name'] ?? $this->attributes['original_filename'];`) against the same cases and confirmed it correctly returns the persisted value when set, and falls back correctly when null/unset.
- Impact confirmation: `resources/views/media/index.blade.php:56` and `resources/views/folders/show.blade.php:30` (both live, active views) render `$mediaFile->display_name` directly, and `database/seeders/DatabaseSeeder.php` seeds custom `display_name` values for demo data. Under the old accessor, none of those custom names — nor any user-supplied rename — were ever actually visible anywhere in the app; every view silently fell back to the raw filename. This is a genuine, previously-undetected, product-wide bug (not just a rename-feature edge case), and the fix is correct and complete for it.
- New test `persisted display name is returned and original filename is the fallback` directly locks in this behavior for both branches.

## Consistency Between Controller and Livewire Deletion Paths

Both paths now: authorize before validating, require `accepted` cascade confirmation only when transcriptions exist, delete the stored file, delete the model, and rely on the DB foreign key for cascade — structurally consistent. One asymmetry found:

### Finding 1 (MEDIUM, non-blocking) — Livewire's transcription check can use a stale relation

`Show::destroy()` checks `$this->mediaFile->transcriptions->isNotEmpty()` — the `transcriptions` relation loaded once in `mount()` (`$mediaFile->load(['transcriptions', 'folder'])`) — rather than re-querying. The controller, by contrast, uses `$mediaFile->transcriptions()->exists()`, a fresh query at destroy-time.

**Failure scenario:** a user opens a media file's show page (transcriptions relation loads as empty), and before they click Delete, a transcription is created for that media file (e.g., an in-progress transcription job completes, or another tab/user creates one). The Livewire component's `showDeleteModal` still has the stale, empty `transcriptions` collection in memory (nothing in the view triggers a reload), so `destroy()` treats it as having zero transcriptions, skips the `accepted` confirmation requirement, and deletes the media — cascading away the newly created transcription — without ever showing the required cascade warning. This is a narrow race window (not exercised by any current test, and no security impact — it only affects the same user's own confirmation UX), but it is a genuine, if narrow, ADR-005 compliance gap and the one real inconsistency between the two paths that this review was specifically asked to check for.

**Recommendation:** re-check via a fresh query (e.g. mirror the controller's `$this->mediaFile->transcriptions()->exists()`, or call `$this->mediaFile->loadCount('transcriptions')`/`refresh()` immediately before the check) rather than trusting the relation loaded at mount time. Not required to block VERIFIED given the acceptance criteria as written are satisfied and no test currently exercises concurrent creation, but should be tracked as a fast-follow given the app's async transcription workflow makes the race plausible in practice.

## Actual Cascade Behavior (DB level)

Verified via migrations, not just application code:

- `transcriptions.media_file_id` → `cascadeOnDelete()` against `media_files`.
- `transcription_segments.transcription_id` → `cascadeOnDelete()` against `transcriptions`.
- `processing_jobs.transcription_id` → `cascadeOnDelete()` against `transcriptions`.

The full chain (MediaFile → Transcription → TranscriptionSegment / ProcessingJob) is cascade-configured at the database level, so `$mediaFile->delete()` cannot orphan rows or throw an FK constraint violation. This satisfies "Associated transcriptions are deleted by the intended database cascade" completely, and confirms the new tests' `assertDatabaseMissing('transcriptions', ...)` checks are validating a real DB-enforced guarantee, not just application-level cleanup.

## Authorization Boundaries

- `MediaFilePolicy::delete` (`$user->isAdmin() || $mediaFile->user_id === $user->id`) is unchanged and still checked via `$this->authorize('delete', ...)` first in both paths.
- New tests directly cover the boundary for both paths: `user cannot delete another users media through the controller` (`assertForbidden`) and `user cannot delete another users media through Livewire` (component created as owner, acting user switched, `assertForbidden` — correctly exercises Livewire's re-authorization on a persisted component instance, the same pattern validated in TASK-001).

## Regression Risk

- Full suite re-run independently: 129 passed / 1 pre-existing skip / 0 failures, up from the 122-pass baseline this task built on (122 + 7 new = 129, consistent).
- Diff scope matches the task's declared "Files Changed" list exactly (verified via `git diff --stat` and per-file diff read); no unrelated files touched.
- `resources/views/media/show.blade.php` and its `MediaActionController` routes are, independently confirmed, not wired into any reachable live UI (that blade file's own delete/rename/move buttons are broken `<a href>` GET links against PATCH/DELETE-only routes). The real, live delete/rename/move UI exclusively goes through the Livewire component (`media.delete-modal`, `media.rename-modal`, `media.move-modal`, dispatched from `media/index.blade.php`). This is pre-existing architecture, not introduced or worsened by this task, and is explicitly out of scope ("Media-list modal wiring and navigation") — noted only because the review was asked to check controller/Livewire consistency and this context clarifies which path real users actually exercise.

## Acceptance Criteria

All eight boxes are checked and each is backed by a passing, specific test, with one caveat:

- Persisted display_name with fallback: `persisted display name is returned and original filename is the fallback` — verified correct, and independently forensically confirmed above.
- Media without transcriptions deletable: `owner can delete media without transcriptions through Livewire`, and the pre-existing controller test.
- Media with transcriptions deletable only after confirmation: `user must explicitly confirm deleting media file with transcriptions`, `user can delete media file and associated transcriptions after explicit confirmation`, `owner can delete media with transcriptions through Livewire after explicit confirmation`.
- Cascade via DB: confirmed both by test and independently by migration inspection above.
- Unauthorized deletion forbidden: both paths tested.
- Controller/Livewire consistency: true for the primary flow; Finding 1 is the one identified gap, non-blocking.
- Tests and Pint pass: independently reproduced above.
- No unrelated behavior changes: confirmed via diff and full-suite regression run.

## Disposition

Recommend moving TASK-002 from REVIEW to VERIFIED. Finding 1 (MEDIUM) should be tracked as a fast-follow (a new task or a note added to Known Limitations) rather than blocking this task, since it is narrow, non-security, and outside what the current acceptance criteria explicitly require. This review does not authorize DONE/closure or any production action.
