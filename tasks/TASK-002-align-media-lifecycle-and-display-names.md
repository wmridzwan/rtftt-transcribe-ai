# TASK-002 - Align Media Lifecycle and Display Names

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Make media deletion behavior and display-name handling consistent across active application paths.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- architecture.md
- DECISIONS.md, especially ADR-005
- app/Models/MediaFile.php
- app/Http/Controllers/MediaActionController.php
- app/Livewire/Media/Show.php
- resources/views/livewire/media/show.blade.php
- app/Policies/MediaFilePolicy.php
- database/migrations
- tests/Feature/MediaManagementTest.php

## Scope

- Return persisted display_name values correctly, with original_filename fallback.
- Allow media deletion with or without associated transcriptions.
- Require explicit cascade confirmation when associated transcriptions will be deleted.
- Keep controller and active Livewire deletion behavior consistent and authorized.
- Cover display names, deletion, cascade behavior, ownership, and Livewire interaction.

## Out of Scope

- Media-list modal wiring, navigation, prototype upload, dashboard icon issue, duplicate folder validation, Phase 2, and unrelated refactors.

Future ideas are not authorization.

## Dependencies

- ADR-005 — Cascade Media Deletion Requires Explicit Confirmation.

## Acceptance Criteria

- [x] Persisted display_name is rendered correctly, with fallback behavior when absent.
- [x] Media without transcriptions can be deleted.
- [x] Media with transcriptions can be deleted only after explicit destructive confirmation.
- [x] Associated transcriptions are deleted by the intended database cascade.
- [x] Unauthorized users cannot delete another user's media.
- [x] Controller and active Livewire behavior are consistent.
- [x] Relevant tests and Pint pass.
- [x] No unrelated behavior changes.

## Implementation Notes

Fixed the MediaFile display-name accessor to read persisted raw attributes while preserving the original filename fallback. Controller deletion now accepts media with associated transcriptions only when confirm_cascade is accepted; deletion remains policy-authorized and the existing cascade foreign key removes associated records. The active Livewire delete modal exposes an explicit confirmation checkbox when transcriptions exist and validates it before deleting, using the same cascade behavior.

### Files Changed

- tasks/TASK-002-align-media-lifecycle-and-display-names.md
- CURRENT_STATE.md
- app/Models/MediaFile.php
- app/Http/Controllers/MediaActionController.php
- app/Livewire/Media/Show.php
- resources/views/livewire/media/show.blade.php
- tests/Feature/MediaManagementTest.php

### Important Decisions

- ADR-005 governs deletion: a confirmed media deletion cascades to related transcriptions.

### Known Limitations

- Controller and Livewire callers must provide their respective confirmation field when deleting media that has transcriptions.
- Independent review (reviews/TASK-002-review.md) found a MEDIUM, non-blocking gap: Livewire's `destroy()` checks the `transcriptions` relation loaded at `mount()` time rather than re-querying, so a transcription created after the page loads but before the delete modal is confirmed would not trigger the required cascade confirmation. The controller path is unaffected (it re-queries at destroy-time). Recommended as a fast-follow, not required for this task's acceptance criteria.

## Verification

Environment: PHP 8.4.24; Laravel 13.31.0; Livewire 4.4.4; Pest 5.1.4; Pint 1.31.1.

Commands and results:

- php artisan test --compact tests/Feature/MediaManagementTest.php: 28 passed, 89 assertions.
- php vendor/bin/pint --dirty --format agent: passed.
- php artisan test --compact: 129 passed, 1 skipped, 318 assertions, 0 failures. The skipped test is the pre-existing disabled Fortify two-factor flow.
- git diff --check: passed.

No migrations, dependency changes, commits, TASK-003, or Phase 2 work were performed. The full suite result supersedes the previous 128-pass baseline for this working tree.

## Review

Review File:

reviews/TASK-002-review.md

Review Status:

VERIFIED. No BLOCKER or HIGH findings. One MEDIUM finding (non-blocking, see Known Limitations above and the review file) and one LOW/process finding on incomplete PHPStan scope in the task's own verification. Independent re-run of the focused suite (28 passed, 89 assertions), the full suite (129 passed, 1 pre-existing skip, 0 failures), and Pint (passed) all confirmed. The display_name accessor fix was independently forensically verified against real Eloquent internals, not just read from the diff. See reviews/TASK-002-review.md for full findings.

## Completion

Independently reviewed and marked VERIFIED by Claude Code in reviews/TASK-002-review.md. Closed as DONE after confirming the review artifact's VERIFIED verdict. The MEDIUM Livewire deletion race-window finding is preserved above and tracked in READY task TASK-002A. No implementation code was modified during closure and no commits were made.

Required flow:

READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The implementation owner must not mark their own work VERIFIED.
