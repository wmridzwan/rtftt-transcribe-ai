# P5-002 — Internal Adversarial Pre-Review

Task: P5-002 — Translation Persistence / Atomic Writer
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification; confers no VERIFIED/DONE.

## Scope Inspected

- `database/migrations/2026_09_21_000001_create_translations_table.php`
- `database/migrations/2026_09_21_000002_create_translation_segments_table.php`
- `database/migrations/2026_09_21_000003_add_active_target_unique_index_to_translations_table.php`
- `app/Models/Translation.php`, `app/Models/TranslationSegment.php`
- `app/Translation/TranslationResultWriter.php`
- `database/factories/TranslationFactory.php`, `TranslationSegmentFactory.php`
- `tests/Feature/Translation/TranslationPersistenceTest.php`

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Additive, reversible, SQLite-compatible migrations | PASS | new tables only; `down()` drops tables/index; no historical migration touched |
| 2. Unique target boundary at DB level | PASS | partial unique index `translations_active_target_unique` (sqlite) + test |
| 3. Transactional atomic writer; no partial completed | PASS | `DB::transaction`; single save + segment replace |
| 4. Idempotent repeated writes | PASS | `is idempotent for repeated writes` |
| 5. Completed translation not overwritten | PASS | `never overwrites a completed translation` |
| 6. Source transcript/segments unchanged | PASS | `writes an aligned translation without mutating the source transcript` |
| 7. Tests + Pint + PHPStan | PASS | see Evidence |

## Adversarial Checks

- **Source immutability (D5-05):** writer only reads `transcription_id`/`detected_language`; never writes `Transcription`/`TranscriptionSegment`. Test asserts byte-for-byte unchanged source rows. PASS.
- **Dedicated persistence (D5-06):** translation content lives only in `translations`/`translation_segments`; no translation column added to Phase 3 tables. PASS.
- **Multiple targets (D5-03):** target is part of the row key and index predicate. PASS.
- **Partial-failure atomicity:** segment replacement and status update occur in one transaction; a thrown write rolls back both. PASS.
- **Weakened assertions / skipped tests:** none; 7 tests assert concrete values/throws. PASS.
- **Test-only production branching:** none. PASS.
- **Secrets leakage:** none introduced. PASS.
- **Migration reversibility:** `down()` drops index/tables; no destructive change to existing data. PASS.
- **Unrelated changes:** none; existing app/tests untouched. PASS.

## Findings

- LOW-1 (non-blocking): the partial unique index is applied only for the
  `sqlite` driver, mirroring the P3-007 precedent. Non-SQLite portability is
  deferred to D7-01 (production data store). Not a defect for the current
  canonical environment; independent reviewer may confirm.
- INFO-1 (non-blocking): `TranslationResultWriter` resolves an existing active
  row or creates a new one; explicit attempt identity (translation attempt
  model) is P5-004/P5-005 scope.

## Evidence

- `php vendor/bin/pest tests/Feature/Translation tests/Unit/Translation` →
  33 passed, 104 assertions.
- `php artisan test --compact` (full suite) → 466 tests, 465 passed, 1 skipped
  (pre-existing 2FA), 2 warnings (pre-existing baseline), 0 failures.
- `php vendor/bin/pint ... --format agent` → passed.
- `php -d memory_limit=1G vendor/bin/phpstan analyse app/Translation
  app/Models/Translation.php app/Models/TranslationSegment.php --no-progress`
  → 0 errors.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.