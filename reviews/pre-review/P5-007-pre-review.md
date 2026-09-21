# P5-007 — Internal Adversarial Pre-Review

Task: P5-007 — Translation Export Foundation
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Scope Inspected

- `app/Http/Controllers/TranslationExportController.php`
- `routes/translation.php` (registered from `bootstrap/app.php`)
- `tests/Feature/Translation/TranslationExportTest.php`

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Four formats render translated segments with inherited timestamps | PASS | TXT/SRT/VTT/DOCX tests; SRT/VTT inherit `decimal(12,3)` via `SegmentTimestamp` |
| 2. Filenames do not collide with source exports | PASS | `{slug(title)}-{target}.{ext}`; test asserts `weekly-meeting-ms.txt` |
| 3. Cross-user denied; non-completed blocked | PASS | 403 owner and 403 non-completed tests; admin allowed |
| 4. No provider call during export | PASS | recording provider bound; `calls === 0` after export |
| 5. UTF-8/multilingual round-trip | PASS (Malay verified; UTF-8 is byte-preserving) | content assertions; no transcoding |
| 6. Tests, Pint, PHPStan | PASS | see Evidence |

## Adversarial Checks

- **Authorization (D5-05 ownership):** derived from `TranscriptionPolicy::view`
  via the translation's transcription; no separate ownership surface.
- **Read-only (D5-05):** export reads persisted `translation_segments`; no writes
  and no provider resolution.
- **Timestamp integrity:** SRT/VTT use the canonical `SegmentTimestamp` SRT/VTT
  formatters; timestamps are inherited, never recomputed.
- **No source overwrite:** target-language suffix guarantees a distinct name.
- **Route isolation (B-001):** routes are in `routes/translation.php`, loaded
  from the clean `bootstrap/app.php` `then` hook, so the dirty `routes/web.php`
  baseline is not modified. Verified via `route:list`.
- **No mock-as-real claim:** not applicable; this is a deterministic read/export
  surface.

## Findings / Notes

- INFO-1: the source-export DOCX temp-file pattern is mirrored (temp file in
  the system temp dir, deleted in `finally`).
- INFO-2: TXT/DOCX include the title; SRT/VTT contain segments only, matching
  the source export contract.
- INFO-3: an empty-segment completed translation exports `full_text` (TXT/DOCX)
  and an empty/header-only SRT/VTT, consistent with the source behavior.

## Evidence

- `php vendor/bin/pest tests/Feature/Translation/TranslationExportTest.php` →
  8 passed, 20 assertions.
- `php artisan route:list --name=translations.export` → 4 routes registered.
- Full suite → 525 tests, 524 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- Pint clean; PHPStan 0 errors.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.