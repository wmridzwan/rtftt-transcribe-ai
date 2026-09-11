# TASK-P1-CREATE-001 — Reconcile prototype creation title contract

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase and origin

Phase 1. Origin: final source-based Phase 1 audit during autonomous execution, 2026-09-11. Decision gate resolved by DECISION-P1-001 and ADR-006.

## Evidence

resources/views/transcriptions/create.blade.php labels Title optional and says an empty title uses the filename. app/Http/Controllers/DemoTranscriptionController.php validates title as required|string|max:255. Phase 1 accepts no physical file; demo_recording.mp3 is synthetic server data, not a user-selected filename. The existing controller explicitly enforces required-title validation; this audit has not added a blank-title regression test while the product contract is undecided.

## Scope after decision

Align form copy/validation and, only if the Product Owner selects it, the blank-title fallback with DECISION-P1-001. Add regression coverage for the selected product contract. Keep demo-only behavior and do not introduce binary uploads, a worker, 2FA, registration or Phase 2.

## Acceptance criteria

- [x] Product Owner resolves DECISION-P1-001.
- [x] Form promise and server behavior agree with the selected contract.
- [x] Relevant tests and formatting pass.
- [x] Independent review before VERIFIED.

## Does not block

TASK-004C and TASK-P1-STATIC-001/002/003/004 reviews, repairs and closure. It blocks only creation-contract alignment and claiming the entire Phase 1 prototype is ready for acceptance.

## Implementation Notes

Updated the form to describe Title as required and removed the filename-fallback promise. Existing `required|string|max:255` server validation was preserved. Added a regression test for the user-facing contract.

## Verification

Focused Phase 1 suite: 94 passed / 308 assertions. Pint passed. PHPStan passed with 0 errors. Independent Claude review VERIFIED in reviews/TASK-P1-CREATE-001-review.md.
