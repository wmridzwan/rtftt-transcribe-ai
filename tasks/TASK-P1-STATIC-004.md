# TASK-P1-STATIC-004 — Repair demo factory and seeder contracts

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorization and dependencies

Phase 1. Current user explicitly requests continuous autonomous Phase 1 execution until a genuine human gate. Origin: reviews/TASK-P1-STATIC-001-triage.md. Triage inventory completed; repairs are independently runnable from each other, each must receive independent review before VERIFIED.

## Scope

database/factories; database/seeders/DatabaseSeeder.php; tests

Implement the empty withTwoFactor factory helper without enabling the disabled feature; correct Faker filename construction; repair per-job timestamp initialization and ordered chronology/logs; remove provably unreachable branches for the actual fixed demo records. Preserve demo content, roles and statuses. Add behavioral tests and run full PHPStan without suppressions.

## Acceptance criteria

- [x] Scoped behavior/declarations corrected with appropriate tests.
- [x] Relevant tests, Pint and relevant PHPStan pass.
- [x] No unauthorized product or phase change.
- [x] Independent Claude review before VERIFIED.

## Verification

Independent Claude review VERIFIED in reviews/TASK-P1-STATIC-004-review.md. Codex-reported full PHPStan is 0 errors and focused tests pass.

## Implementation verification

Pint passed. Full PHPStan passed with 0 errors. Full suite: 157 passed / 1 skipped / 436 assertions. Independent Claude review VERIFIED in reviews/TASK-P1-STATIC-004-review.md.

Implementation choice: separate fixture arrays from persistence using typed private helper parameters; this retains existing match branches for their general input contracts. No analyzer suppression or artificial inline type override. 2FA is unsupported in schema/config, so its factory helper now throws an explicit LogicException (never return type), without enabling 2FA. Timestamps use Carbon instances scoped per record.
