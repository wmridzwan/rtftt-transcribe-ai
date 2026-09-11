# TASK-001 - Enforce Media-Folder Ownership

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Enforce ownership isolation when moving media into folders through both the controller and active Livewire mutation paths.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- architecture.md
- DECISIONS.md
- app/Http/Controllers/MediaActionController.php
- app/Livewire/Media/Show.php
- app/Models/MediaFile.php
- app/Models/Folder.php
- app/Policies/MediaFilePolicy.php
- app/Policies/FolderPolicy.php
- tests/Feature/MediaManagementTest.php

## Scope

- Require a destination folder to belong to the media owner in both move paths.
- Preserve policy-authorized admin management of another user's media within that owner's folders.
- Correct the test that expects cross-user folder moves to succeed.
- Cover allowed, forbidden, invalid and root moves through controller and Livewire actions.
- Check folder-content visibility directly affected by rejected moves.

## Out of Scope

- Media deletion behavior and display-name accessor.
- Media-list modal wiring and navigation.
- Unrelated refactors, Phase 2, TASK-002 and commits.

Future ideas are not authorization.

## Dependencies

- None.

## Acceptance Criteria

- [x] Owners can move their media to their own folders.
- [x] Owners cannot move their media to another user's folders.
- [x] Moving to root/null continues to work.
- [x] Unauthorized media updates are forbidden and invalid destinations return validation errors without changing the media.
- [x] Admins can manage other users' media within the media owner's folders.
- [x] Controller and active Livewire paths have focused tests, including related folder visibility.
- [x] Relevant tests and Pint pass; static-analysis results are recorded.
- [x] No unrelated application behavior changes.

## Implementation Notes

Both move paths now use a destination-existence rule scoped by the authorized media record's user_id. Livewire checks the media update policy before destination validation. The active folder selector already uses this owner scope and needs no change. Normal users cannot target another user's media or folder; admins retain their existing permission to manage other users' media within the media owner's folders or move it to root.

Corrected the controller regression test that previously expected a cross-user move to succeed. Added 11 focused tests covering controller and Livewire destination validation, null/root moves, admin management, and action-time media authorization. Folder page assertions confirm that allowed moves are visible and rejected moves do not expose media in another user's folder. No folder relationship or visibility code change was necessary to prevent exposure through these rejected mutations.

### Files Changed

- tasks/TASK-001-enforce-media-folder-ownership.md
- CURRENT_STATE.md
- app/Http/Controllers/MediaActionController.php
- app/Livewire/Media/Show.php
- tests/Feature/MediaManagementTest.php

### Important Decisions

- Destination ownership follows the media owner, not the acting admin. Existing admin update privileges are preserved; no cross-owner transfer capability is introduced.
- Null remains a valid root destination.

### Known Limitations

- Existing inconsistent database records will not be migrated or repaired by this task.
- Targeted PHPStan reports three pre-existing typing issues in unchanged declarations: MediaActionController::download() lacks a return type; Show::$folders lacks an iterable value type; Show::render() lacks a return type. These declarations were compared with HEAD and left unchanged to respect the task scope. Static analysis is not green; this task does not claim Phase 1 technical completion.
- The full test suite was not rerun. The previously recorded 111-pass baseline remains historical, not a count for this modified working tree.

## Verification

Environment: PHP 8.4.24; Laravel 13.31.0; Livewire 4.4.4; Pest 5.1.4; Pint 1.31.1; Larastan 3.11.0. Confirmed with composer show --direct and php --version. The installed PHP/Composer runtime required execution outside the sandbox because it was unavailable inside it. No dependency changes were made.

Commands and results:

- php artisan test --compact tests/Feature/MediaManagementTest.php before implementation: 21 tests, 17 passed, 4 failed, 52 assertions. The four failures were the expected cross-owner destination cases for controller and Livewire, as owner and admin. The runner returned the complete failure report after an interrupt request following delayed output.
- php artisan test --compact tests/Feature/MediaManagementTest.php tests/Feature/FolderAuthorizationTest.php tests/Feature/FolderManagementTest.php tests/Feature/OwnershipTest.php after implementation: 40 passed, 100 assertions.
- php vendor/bin/pint --dirty --format agent: passed; corrected import ordering in MediaManagementTest.php only.
- Repeated the same four-file test command after formatting: 40 passed, 100 assertions.
- php vendor/bin/phpstan analyse app/Http/Controllers/MediaActionController.php app/Livewire/Media/Show.php --no-progress --error-format=json: exit 1; only the three unchanged typing issues listed above, no findings in the changed move logic.
- git diff --check: passed.
- git diff, git status --short and git diff --cached --name-only: scope checked; no staged changes. The pre-existing untracked .claude/settings.local.json was left untouched.

Result: focused behavioral verification and Pint PASS. PHPStan remains non-green due to unchanged declarations outside this ownership fix. No migrations, dependency changes, commits, or Phase 2 work were performed.

## Review

Review File:

reviews/TASK-001-review.md

Review Status:

VERIFIED. No BLOCKER or HIGH findings. Independent re-run of the focused suite (40 passed, 100 assertions), the full suite (122 passed, 1 pre-existing skip, 0 failures — no regressions), Pint (passed), and PHPStan (same three pre-existing, out-of-scope typing issues on unchanged lines) all confirmed. See reviews/TASK-001-review.md for full findings.

## Completion

Independently reviewed and moved from REVIEW to VERIFIED by Claude Code. Closed as DONE by Codex under the user's explicit closure instruction after confirming the task's VERIFIED status and the VERIFIED verdict in reviews/TASK-001-review.md. Final transition: VERIFIED -> DONE. CURRENT_STATE.md records completion. The review artifact and existing application/test changes were preserved unchanged. No new task or phase was started and no commits were made. Task closure does not authorize production deployment.

Required flow:

READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The implementation owner must not mark their own work VERIFIED.
