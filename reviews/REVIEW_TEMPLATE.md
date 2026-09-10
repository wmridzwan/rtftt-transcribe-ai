# REVIEW - TASK-XXX - Task Title

## Review Status

PENDING

## Task

Task File: tasks/TASK-XXX.md

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Review Scope

Review the implementation against:

- task objective
- task scope
- acceptance criteria
- architecture.md
- DECISIONS.md
- CURRENT_STATE.md
- applicable project rules
- relevant tests

The reviewer must inspect the actual implementation and Git changes.

Do not rely only on the implementation owner's summary.

## Reviewer Rules

The reviewer must:

- remain independent from the implementation owner
- not modify implementation code unless explicitly reassigned
- identify correctness issues
- identify architecture violations
- identify regression risks
- check authorization and ownership boundaries
- check relevant security concerns
- check important edge cases
- check test adequacy
- verify acceptance criteria

## Findings

### BLOCKER

None.

### HIGH

None.

### MEDIUM

None.

### LOW

None.

## Acceptance Criteria Verification

Record each acceptance criterion from the task and its review result.

Example:

- [ ] Criterion 1 - PENDING
- [ ] Criterion 2 - PENDING
- [ ] Criterion 3 - PENDING

## Test Verification

Tests reviewed:

- None yet.

Commands independently executed by reviewer:

- None yet.

Result:

PENDING

## Architecture Review

Status:

PENDING

Notes:

None yet.

## Security and Authorization Review

Status:

PENDING

Notes:

None yet.

## Regression Risk

Status:

PENDING

Notes:

None yet.

## Required Changes

None yet.

## Reviewer Conclusion

Choose one:

- CHANGES_REQUESTED
- VERIFIED

Current Conclusion:

PENDING

## Verification Rule

The reviewer may mark the task VERIFIED only when:

- acceptance criteria are satisfied
- relevant tests pass
- no unresolved BLOCKER findings remain
- no unresolved HIGH findings remain
- architecture requirements are respected

MEDIUM and LOW findings may remain only when they are explicitly documented
and do not prevent safe completion of the task.

## Handoff

If CHANGES_REQUESTED:

1. Record all required changes in this review.
2. Change the task status to CHANGES_REQUESTED.
3. Return implementation ownership to the assigned implementation agent.

If VERIFIED:

1. Change the task Review Status to VERIFIED.
2. Change the task status to VERIFIED.
3. Update CURRENT_STATE.md when appropriate.

VERIFIED does not authorize production deployment.
