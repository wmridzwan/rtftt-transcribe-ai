# TASK-003A - Expand Query-Parameter Modal Coverage

## Status

READY

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Add direct Livewire query-parameter coverage for the move and delete modal branches introduced in TASK-003.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- architecture.md
- DECISIONS.md
- reviews/TASK-003-review.md, LOW Finding 1
- tasks/TASK-003-complete-prototype-action-wiring.md
- app/Livewire/Media/Show.php
- tests/Feature/MediaManagementTest.php

## Scope

- Add direct Livewire query-parameter test coverage for the move modal branch.
- Add direct Livewire query-parameter test coverage for the delete modal branch.
- Preserve current behavior.
- Do not redesign the UI.

## Out of Scope

- Implementation changes unless tests expose a real defect.
- Navigation changes.
- Phase 2.
- Unrelated refactors.

Future ideas are not authorization.

## Dependencies

- TASK-003 — Complete Prototype Action Wiring (DONE).

## Acceptance Criteria

- [ ] A direct Livewire query-parameter test asserts `action=move` opens the move modal.
- [ ] A direct Livewire query-parameter test asserts `action=delete` opens the delete modal.
- [ ] Current behavior remains unchanged.
- [ ] Relevant tests pass.
- [ ] No application implementation changes are needed unless a test exposes a real defect.
- [ ] No unrelated behavior changes.

## Implementation Notes

This task is READY and has not been started. It originates from the LOW, non-blocking test-coverage finding in reviews/TASK-003-review.md.

### Files Changed

- None yet.

### Important Decisions

- Extend test coverage only; preserve the existing query-parameter modal implementation and UI.

### Known Limitations

- None yet.

## Verification

PENDING

## Review

Review File: None yet. Expected: reviews/TASK-003A-review.md

Review Status: PENDING

## Completion

Not started. TASK-004 and Phase 2 remain unauthorized.

Required flow: READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

The implementation owner must not mark their own work VERIFIED.
