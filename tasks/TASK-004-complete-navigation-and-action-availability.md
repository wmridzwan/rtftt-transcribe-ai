# TASK-004 - Complete Navigation and Action Availability

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Complete Phase 1 navigation and role-aware action availability using the approved product decisions and the OpenCode findings recorded in current project context.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- architecture.md
- DECISIONS.md
- PROJECT_CONTEXT.md
- resources/views/layouts/app/sidebar.blade.php
- resources/views/components/desktop-user-menu.blade.php
- resources/views/dashboard.blade.php
- resources/views/livewire/media/show.blade.php
- resources/views/transcriptions/show.blade.php
- resources/views/jobs/index.blade.php
- resources/views/jobs/show.blade.php
- resources/views/components/desktop-user-menu.blade.php
- resources/views/components/desktop-user-menu.blade.php
- resources/views/livewire/folders/index.blade.php
- resources/views/settings/index.blade.php
- relevant feature tests

## Scope

- Make processing job detail links role-aware for normal users and admins.
- Make export visibility depend on `TranscriptionStatus::Completed` and remove inappropriate `wire:navigate` from file responses.
- Hide or disable media Download when the physical file does not exist.
- Add Folders to Files navigation and provide a clear Create Folder path in empty states.
- Make Settings navigation lead to the canonical `/settings` overview while preserving settings subpages.
- Improve Phase 1 back-link treatment where needed.
- Remove stale duplicate media/folder views and the unused header layout only where repository evidence proves they are unreachable and removal is safe.
- Add focused tests for changed behavior.

## Out of Scope

- Real uploads, Phase 2 storage behavior, navigation redesign, new RBAC, billing, unrelated UI refactors, or unrelated controller changes.

Future ideas are not authorization.

## Dependencies

- TASK-003 — Complete Prototype Action Wiring (DONE).
- TASK-003A — Expand Query-Parameter Modal Coverage (READY; not required for this task).

## Acceptance Criteria

- [x] Non-admin users never get clickable admin-only job-detail links.
- [x] Admin users retain valid job-detail access.
- [x] Export controls appear only when transcription is Completed.
- [x] Export links behave as normal downloads, not Livewire navigation.
- [x] Media Download is hidden or disabled when no physical file exists.
- [x] Folders is discoverable from application navigation.
- [x] A user with no folders has a clear Create Folder path.
- [x] Settings navigation reaches `/settings`.
- [x] Profile, Security, and Appearance remain reachable.
- [x] No proven-unused duplicate Phase 1 surface remains.
- [x] Relevant tests and Pint pass.
- [x] No unrelated behavior changes.

## Implementation Notes

Implemented the approved role-aware processing links, completed-status export controls, normal file response links, missing-file media download availability, Folders navigation and empty-state CTA, canonical Settings navigation, and preservation of settings subpages. Removed the proven-unused duplicate media/folder views, unused header layout, and unreachable duplicate controller index/show methods. No real uploads or Phase 2 behavior was implemented.

### Files Changed

- app/Http/Controllers/FolderController.php
- app/Http/Controllers/MediaController.php
- resources/views/components/desktop-user-menu.blade.php
- resources/views/dashboard.blade.php
- resources/views/jobs/show.blade.php
- resources/views/layouts/app/sidebar.blade.php
- resources/views/livewire/folders/index.blade.php
- resources/views/livewire/media/show.blade.php
- resources/views/media/index.blade.php
- resources/views/transcriptions/index.blade.php
- resources/views/transcriptions/show.blade.php
- resources/views/folders/index.blade.php (removed)
- resources/views/media/show.blade.php (removed)
- resources/views/layouts/app/header.blade.php (removed)
- tests/Feature/MediaManagementTest.php
- tests/Feature/PageRenderTest.php
- tests/Feature/TranscriptExportTest.php
- tasks/TASK-004-complete-navigation-and-action-availability.md
- CURRENT_STATE.md

### Important Decisions

- Approved product-owner decisions in the task request govern download availability, processing visibility, export eligibility, folder discoverability, and canonical settings navigation.

### Known Limitations

- MediaActionController mutation routes remain available because they are still explicitly routed and covered by direct HTTP tests; only the proven-unreachable duplicate index/show controller methods were removed.
- Independent review (reviews/TASK-004-review.md) found one MEDIUM, non-blocking finding: the sidebar Settings item's `:current` check narrowed from matching any `/settings/*` URL to matching only the exact `settings.index` route, so Profile/Security/Appearance no longer highlight "Settings" as active. Reachability is unaffected; recommended as a small follow-up.
- Two LOW, non-blocking findings were recorded by review. The desktop user-menu documentation omission is resolved by listing `resources/views/components/desktop-user-menu.blade.php` above. The two `wire:navigate` removals in `jobs/show.blade.php` and `transcriptions/show.blade.php` affect plain internal links, not file responses, and are tracked in READY follow-up TASK-004B.

## Verification

Environment: PHP 8.4.24; Laravel 13.31.0; Livewire 4.4.4; Pest 5.1.4; Pint 1.31.1.

Commands and results:

- `php artisan test --compact tests/Feature/PageRenderTest.php tests/Feature/TranscriptExportTest.php tests/Feature/MediaManagementTest.php tests/Feature/FolderManagementTest.php`: 69 passed, 208 assertions.
- `php vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test --compact`: 140 passed, 1 skipped, 347 assertions, 0 failures. The skipped test is the pre-existing disabled Fortify two-factor flow.
- `npm.cmd run build`: passed; Vite production assets built successfully. The optional `fontaine` package warning is pre-existing and non-blocking.
- `git diff --check`: passed.

## Review

Review File: reviews/TASK-004-review.md

Review Status: VERIFIED. No BLOCKER or HIGH findings. One MEDIUM finding and two LOW findings, all non-blocking (see Known Limitations above and the review file). Independent re-run of the focused suite (69 passed, 208 assertions), the full suite (140 passed, 1 pre-existing skip, 0 failures), Pint (passed), and PHPStan on both changed controllers (0 errors) all confirmed. The export-gating and download-gating fixes were independently verified against the actual server-side rules (TranscriptionExportController's Completed-only check; the seeder's never-written physical files), not just read from the diff, confirming both fix real, currently-live bugs. Dead-code removal was independently proven safe via unchanged routes/web.php and a full-tree reference grep. See reviews/TASK-004-review.md for full findings.

## Completion

Independently reviewed and marked VERIFIED by Claude Code in reviews/TASK-004-review.md. Closed as DONE after confirming the VERIFIED review. The MEDIUM Settings active-state finding is preserved and tracked in READY TASK-004A; the LOW internal-link navigation finding is preserved and tracked in READY TASK-004B. The documentation finding is resolved by the complete Files Changed list. No implementation code was modified during review and no commits were made. TASK-005 and Phase 2 remain unauthorized.
