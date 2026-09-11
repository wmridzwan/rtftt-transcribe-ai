# TASK-004B - Restore Internal Livewire Navigation

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Restore Livewire navigation on the two internal page links that lost it during TASK-004 role-aware link restructuring.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- tasks/TASK-004-complete-navigation-and-action-availability.md
- reviews/TASK-004-review.md, Finding 3 (LOW)
- resources/views/jobs/show.blade.php
- resources/views/transcriptions/show.blade.php

## Scope

- Restore `wire:navigate` on the internal transcription link in the job detail page.
- Restore `wire:navigate` on the admin-only job detail link in the transcription detail page.
- Add focused coverage that preserves these internal navigation attributes.

## Out of Scope

- File download links, navigation redesign, Phase 2, authorization changes, and unrelated refactors.

Future ideas are not authorization.

## Dependencies

- TASK-004 — Complete Navigation and Action Availability (DONE).

## Acceptance Criteria

- [x] The job detail transcription link uses Livewire navigation.
- [x] The admin transcription detail job link uses Livewire navigation.
- [x] File/export response links remain normal browser links without Livewire navigation.
- [x] Relevant tests pass.
- [x] No unrelated behavior changes.

## Implementation Notes

Restored wire:navigate on the job detail transcription link and the admin-only transcription detail job link. Added DOM-scoped rendered-link assertions. Existing export tests protect normal browser downloads.

### Files Changed

- resources/views/jobs/show.blade.php; resources/views/transcriptions/show.blade.php; tests/Feature/PageRenderTest.php

### Important Decisions

- Keep file-response links free of `wire:navigate`; restore it only for ordinary internal page links.

### Known Limitations

Full PHPStan reports 59 existing errors in unchanged application/configuration paths. Initial run exhausted 128 MB; rerun with --memory-limit=512M completed. No application PHP classes, analyzed configuration, dependencies or routes were changed by these tasks. Static analysis is not globally clean; this is a Phase 1 completion concern, not a claimed pass.

## Verification

Focused verification: PageRenderTest, MediaManagementTest, TranscriptExportTest — 69 passed / 214 assertions. Pint --dirty --format agent passed. Independent Claude Code review: VERIFIED. No application PHP classes changed; full PHPStan: 59 errors in unchanged paths; see Known Limitations.

## Review

Review File: reviews/TASK-004B-review.md (original combined Claude review: reviews/PHASE1-FOLLOWUPS-review.md)

Review Status: VERIFIED by independent Claude Code review on 2026-09-11. No findings. Source inspection only; test execution performed by Codex.

## Completion

REVIEW -> VERIFIED by independent Claude Code -> DONE by Codex orchestration under the user's explicit instruction to close VERIFIED work. No findings from this review. Full suite: 149 passed / 1 pre-existing skip / 374 assertions; focused suite: 69 passed / 214 assertions; Pint passed. Full PHPStan remains non-green (59 errors in unchanged paths), tracked in TASK-P1-STATIC-001. No claim of Phase 1 acceptance, Phase 2 authorization, production deployment or commit.
