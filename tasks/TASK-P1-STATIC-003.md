# TASK-P1-STATIC-003 — Correct domain type declarations

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorization and dependencies

Phase 1. Current user explicitly requests continuous autonomous Phase 1 execution until a genuine human gate. Origin: reviews/TASK-P1-STATIC-001-triage.md. Triage inventory completed; repairs are independently runnable from each other, each must receive independent review before VERIFIED.

## Scope

app/Models; app/Livewire/Media/Show.php; app/Livewire/Folders/Index.php; app/Http/Controllers/MediaActionController.php

Correct model factory and relation generics, role enum documentation, iterable value types and concrete response/view return types. No behavior changes, suppressions, broad mixed workarounds or config changes. Verify domain relationships, role casts, ownership and rendered pages.

## Acceptance criteria

- [ ] Scoped behavior/declarations corrected with appropriate tests.
- [ ] Relevant tests, Pint and relevant PHPStan pass.
- [ ] No unauthorized product or phase change.
- [x] Independent Claude review before VERIFIED.

## Verification

Independent Claude review VERIFIED in reviews/TASK-P1-STATIC-003-review.md. Codex-reported PHPStan: 0 errors; relationship/render focused tests passed.

## Implementation verification

Pint passed. Full PHPStan passed with 0 errors after source-level fixes. Full suite before final export-directory guard: 155 passed / 1 skipped / 428 assertions. Final export guard focused suite: 11 passed / 59 assertions. Final full-suite and build verification pending. Independent Claude review pending.

Folder modal now explicitly serializes id, name and media_files_count used by the view; unrelated fields are no longer sent. All affected model and controller declarations are typed at the source.
