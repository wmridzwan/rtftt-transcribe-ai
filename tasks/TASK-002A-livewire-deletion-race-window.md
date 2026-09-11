# TASK-002A - Refresh Livewire Deletion State Before Cascade Confirmation

## Status

READY

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code

## Authorized Phase

Phase 1 — Application Foundation + Full Clickable Prototype

## Objective

Close the Livewire deletion race window identified in the TASK-002 independent review so cascade confirmation reflects current transcription relationships at deletion time.

## Context

- AGENTS.md
- plan.md
- CURRENT_STATE.md
- DECISIONS.md, especially ADR-005
- tasks/TASK-002-align-media-lifecycle-and-display-names.md
- reviews/TASK-002-review.md
- app/Livewire/Media/Show.php
- resources/views/livewire/media/show.blade.php
- tests/Feature/MediaManagementTest.php

## Scope

- Re-query or refresh the Livewire media transcription relationship immediately before deciding whether cascade confirmation is required.
- Preserve existing explicit confirmation and database cascade behavior from ADR-005.
- Add focused coverage for a transcription created after component mount but before deletion.

## Out of Scope

- Media-list modal wiring, navigation, display-name behavior, controller deletion behavior, TASK-003, Phase 2, and unrelated refactors.

Future ideas are not authorization.

## Dependencies

- TASK-002 — Align Media Lifecycle and Display Names (DONE).
- ADR-005 — Cascade Media Deletion Requires Explicit Confirmation.

## Acceptance Criteria

- [ ] Livewire deletion checks current transcription state at action time.
- [ ] A transcription created after mount requires explicit cascade confirmation before deletion.
- [ ] Confirmed deletion still cascades associated records.
- [ ] Media without transcriptions remains deletable without cascade confirmation.
- [ ] Relevant tests pass.
- [ ] Required formatting and static analysis pass.
- [ ] No unrelated behavior changes.

## Implementation Notes

This task is READY and has not been started. It is the follow-up for Finding 1 (MEDIUM) in reviews/TASK-002-review.md.

### Files Changed

- None yet.

### Important Decisions

- Keep ADR-005 confirmation semantics unchanged; only make the Livewire state check current at deletion time.

### Known Limitations

- None yet.

## Verification

PENDING

## Review

Review File: None yet. Expected: reviews/TASK-002A-review.md

Review Status: PENDING

## Completion

Not started. TASK-003 and Phase 2 remain unauthorized.

Required flow: READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

The implementation owner must not mark their own work VERIFIED.
