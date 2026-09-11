# TASK-001 Review — Enforce Media-Folder Ownership

Reviewer: Claude Code
Date: 2026-09-11
Task: tasks/TASK-001-enforce-media-folder-ownership.md
Implementation Owner: Codex

## Verdict

VERIFIED — no BLOCKER or HIGH findings.

## Scope of Review

Independently inspected, without modifying implementation code:

- `git diff` for `app/Http/Controllers/MediaActionController.php`, `app/Livewire/Media/Show.php`, `tests/Feature/MediaManagementTest.php`, `CURRENT_STATE.md`.
- `app/Models/MediaFile.php`, `app/Models/Folder.php`, `app/Policies/MediaFilePolicy.php`, `app/Policies/FolderPolicy.php`, `app/Http/Controllers/FolderController.php`, `routes/web.php`.
- Re-ran verification independently rather than trusting the task's recorded output.

## Independent Verification (re-run, not reused from the task file)

- `php artisan test --compact tests/Feature/MediaManagementTest.php tests/Feature/FolderAuthorizationTest.php tests/Feature/FolderManagementTest.php tests/Feature/OwnershipTest.php` → 40 passed, 100 assertions. Matches the task's claim.
- `php artisan test --compact` (full suite) → 123 tests, 122 passed, 1 skipped (pre-existing, intentional 2FA skip), 296 assertions, **0 failures**. No regressions versus the 111-passed/1-skipped baseline recorded in CURRENT_STATE.md (111 + 11 new focused tests = 122).
- `vendor/bin/pint --test --format agent` on the three changed files → passed, no formatting drift.
- `vendor/bin/phpstan analyse app/Http/Controllers/MediaActionController.php app/Livewire/Media/Show.php --no-progress --error-format=json` → exit 1, exactly the three issues the task disclosed (`MediaActionController::download()` missing return type; `Show::$folders` missing iterable value type; `Show::render()` missing return type), all on unchanged lines. Confirmed via `git diff` that none of these lines were touched by this task. Treated as pre-existing and out of scope, not a blocker for this task.
- Diffed the implementation files line-by-line against the task's documented "Files Changed" and "Implementation Notes" — the only production changes are the `Rule::exists(...)->where('user_id', $mediaFile->user_id)` destination scoping in both move paths, and reordering `authorize()` before `validate()` in `Show::moveToFolder()`. No unrelated changes found.

## Correctness

- Both move paths (`MediaActionController::moveToFolder`, `Show::moveToFolder`) now scope the `folders.id` existence check to `user_id = <media owner>`, so a destination folder belonging to anyone other than the media's owner fails validation regardless of who performs the move. This directly satisfies the objective ("Enforce ownership isolation when moving media into folders").
- Null/root destination continues to validate (`nullable` retained) and is covered by dedicated tests for both the controller and Livewire paths.
- Admin management is preserved because `MediaFilePolicy::update` still authorizes admins, and the destination check is scoped to the *media owner's* folders rather than the acting user's — an admin can move another user's media into that owner's folders but not into the admin's own folder (explicitly tested and passing). This matches the task's recorded decision that "destination ownership follows the media owner, not the acting admin," which is a sound closure of a potential ownership-transfer loophole (an admin could otherwise launder another user's media into their own folder).
- Reordering `authorize()` before `validate()` in the Livewire component closes a real gap: previously, an authorization check that runs after validation means a component whose bound user changes mid-lifecycle (e.g., session context differs from when the component was mounted) could reach the update through a validation pass. The new `Livewire reauthorizes media ownership when moving` test exercises exactly this by switching the acting user on an already-mounted component instance and asserting `assertForbidden()`. Verified this test passes with the fix and reflects real Livewire persistence behavior (mount() is not re-run on set/call against a persisted component).

## Acceptance Criteria

All eight boxes in the task are checked and each is backed by a passing, specific test:

- Owner→own folder: `owner can move media to an owned folder through Livewire`.
- Owner→other's folder rejected: `user cannot move media to another users folder`, `Livewire rejects another users folder without changing the current folder`.
- Root/null move: `owner can move media to root through Livewire`, controller null case in the original suite.
- Unauthorized update / invalid destination: `user cannot move another users media through the controller`, `controller rejects a nonexistent destination without changing the current folder`, `Livewire rejects a nonexistent destination folder`.
- Admin management within owner's folders: `admin can move another users media within the owners folders through the controller` / `...through Livewire`, with the negative case `admin cannot move another users media into the admins folder through the controller` / `...through Livewire`.
- Folder-visibility checks on rejected moves: both the controller and Livewire rejection tests assert the other owner's folder page does not expose the media file.
- Pint and focused/full test results recorded and independently reproduced above.
- No unrelated behavior changes: confirmed via diff inspection and a clean full-suite regression run.

## Security / Authorization Boundaries

- No cross-tenant data exposure found: rejected moves leave `folder_id` unchanged and the target folder's show page does not list the media file (explicitly tested).
- No mass-assignment concern: `folder_id` was already fillable and unchanged.
- The fix closes the actual vulnerability class in scope (ownership bypass on folder destination) without introducing a new one (e.g., it does not allow admins to reassign ownership).

## Test Adequacy

11 new focused tests are well-targeted: they cover controller and Livewire symmetrically, both authorization (`assertForbidden`) and validation (`assertSessionHasErrors` / `assertHasErrors`) failure modes, the admin-boundary case in both directions, and visibility leakage. No redundant or missing cases identified for this task's scope.

## Non-Blocking Notes

- The three PHPStan findings are pre-existing and outside this task's scope per its own "Known Limitations" section; they should be tracked separately (e.g., a future task) rather than folded into this one, per the repository's scope-discipline rule.
- No other findings.

## Disposition

Recommend moving TASK-001 from REVIEW to VERIFIED. This review does not authorize DONE/closure or any production action — that remains a separate step per the repository's task lifecycle and human decision gates.
