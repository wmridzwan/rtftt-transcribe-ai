# P2-001A — Clarify Upload Size Unit

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 2 checkpoint follow-up; prerequisite for any future upload workflow. This follow-up does not authorize P2-003.

## Objective

Make the approved 500 MiB (524,288,000-byte) upload boundary explicit as a byte/unit contract and preserve the exact boundary in configuration, documentation, and tests.

## Context

Originating independent review: `reviews/P2-001-P2-002-independent-review.md` (MEDIUM: the upload-size unit was not explicit).

The Product Owner approved the canonical limit as exactly 500 MiB = 524,288,000 bytes. The configuration value must remain `524_288_000`; decimal MB is not the contract.

## Scope

- Resolve or record the accepted unit semantics.
- Align the centralized config, ADR/architecture wording, task record, and boundary tests.

## Out of Scope

- Upload UI, route, controller, or workflow.
- P2-003 or later work.
- Changing the byte value without the required product decision.

## Dependencies

- Human Product Owner decision only if the intended contract differs from the current 500 MiB value.

## Acceptance Criteria

- [x] Exact unit and byte boundary are explicit and consistent.
- [x] No upload implementation is added.
- [x] Relevant static verification and independent review pass; runtime PHP tests were unavailable in the review environment.

## Review Reference

`reviews/P2-001-P2-002-independent-review.md`

## Implementation Notes

### Files Changed

- `config/media.php`
- `DECISIONS.md`
- `architecture.md`
- `CURRENT_STATE.md`
- `tasks/P2-001-confirm-upload-product-contract.md`
- `tasks/P2-002-define-ingestion-lifecycle-contract.md`
- `tests/Feature/MediaUploadContractTest.php`
- `tasks/P2-001A-clarify-upload-size-unit.md`

### Important Decisions

- The canonical boundary is 500 MiB, exactly 524,288,000 bytes.
- The numeric configuration value remains unchanged; only unit labeling and contract assertions were clarified.
- No upload workflow, receiving configuration, or P2-003 work was added.

### Known Limitations

- PHP/web-server/Livewire receiving limits remain a separate P2-003 prerequisite and were not changed.

## Verification

Implementation owner must record the commands executed and results.

Commands and results:

- Static active-contract search: PASS; no ambiguous legacy size-unit wording remains in the active config, documentation, tests, or task records.
- Exact-boundary search: PASS; active records retain `524_288_000` and assert equivalence to `500 * 1024 * 1024`.
- `git diff --check`: PASS; no whitespace errors.
- `php artisan test --compact tests/Feature/MediaUploadContractTest.php`: NOT RUN; the configured PHP 8.4 executable was unavailable to launch in this environment.
- `vendor/bin/pint --dirty --format agent`: NOT RUN; PHP is not available on PATH, so the formatter could not launch.

Implementation verification is complete to the extent supported by the available environment. Independent review VERIFIED this task in `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md`.

## Completion

Work closed the independently VERIFIED task as DONE under ADR-010. P2-003 remains closed.
