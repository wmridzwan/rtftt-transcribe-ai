# TASK-004B - Restore Internal Livewire Navigation

## Status

READY

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

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

- [ ] The job detail transcription link uses Livewire navigation.
- [ ] The admin transcription detail job link uses Livewire navigation.
- [ ] File/export response links remain normal browser links without Livewire navigation.
- [ ] Relevant tests pass.
- [ ] No unrelated behavior changes.

## Implementation Notes

READY follow-up for the LOW finding in reviews/TASK-004-review.md. Not started.

### Files Changed

- None yet.

### Important Decisions

- Keep file-response links free of `wire:navigate`; restore it only for ordinary internal page links.

### Known Limitations

- None yet.

## Verification

PENDING

## Review

Review File: None yet. Expected: reviews/TASK-004B-review.md

Review Status: PENDING

## Completion

Not started. TASK-005 and Phase 2 remain unauthorized.
