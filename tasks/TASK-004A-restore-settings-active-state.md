# TASK-004A - Restore Settings Active State

## Status

READY

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Restore active Settings navigation highlighting on Profile, Security, Appearance, and the canonical Settings overview pages.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- tasks/TASK-004-complete-navigation-and-action-availability.md
- reviews/TASK-004-review.md, Finding 1 (MEDIUM)
- resources/views/layouts/app/sidebar.blade.php
- routes/settings.php

## Scope

- Make the Settings sidebar item visibly active for the canonical overview and settings subpages.
- Preserve existing Settings, Profile, Security, and Appearance routes.

## Out of Scope

- Navigation redesign, new settings surfaces, Phase 2, unrelated refactors, and application behavior outside Settings highlighting.

Future ideas are not authorization.

## Dependencies

- TASK-004 — Complete Navigation and Action Availability (DONE).

## Acceptance Criteria

- [ ] Settings is active on `/settings`.
- [ ] Settings is active on Profile, Security, and Appearance pages.
- [ ] Existing settings routes remain reachable.
- [ ] Relevant tests pass.
- [ ] No unrelated behavior changes.

## Implementation Notes

READY follow-up for the MEDIUM finding in reviews/TASK-004-review.md. Not started.

### Files Changed

- None yet.

### Important Decisions

- Preserve the canonical `/settings` landing page while restoring subpage active-state visibility.

### Known Limitations

- None yet.

## Verification

PENDING

## Review

Review File: None yet. Expected: reviews/TASK-004A-review.md

Review Status: PENDING

## Completion

Not started. TASK-005 and Phase 2 remain unauthorized.
