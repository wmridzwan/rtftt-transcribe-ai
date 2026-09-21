# P5-003 — Internal Adversarial Pre-Review

Task: P5-003 — Translation Provider Boundary
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Scope Inspected

- `config/translation.php`
- `app/Translation/TranslationRequest.php`
- `app/Translation/TranslationResponseValidator.php`
- `app/Translation/TranslationWorkerError.php`
- `app/Translation/HttpTranslationProvider.php`
- `app/Providers/AppServiceProvider.php` (translation binding only)
- `worker/translation.py`
- `worker/tests/test_translation.py`
- `tests/Unit/Translation/TranslationRequestTest.php`,
  `TranslationResponseValidatorTest.php`,
  `tests/Feature/Translation/HttpTranslationProviderTest.php`

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Provider resolved without hardcoding a vendor | PASS | `AppServiceProvider` binds `TranslationProvider` from `translation.provider`; self-hosted default; container-resolution test |
| 2. Self-hosted provider sends text only; no media path/binary | PASS | `TranslationRequest` carries segments only; test asserts no `media_reference`/`storage_key` |
| 3. Valid response maps to aligned `TranslationResult` | PASS | `TranslationResponseValidator` + provider test preserves index/timestamps |
| 4. Malformed/partial responses rejected | PASS | validator rejects missing/mismatched target, missing segments/fields, invalid DTOs |
| 5. Bearer auth server/config controlled; no secrets committed | PASS | token from env/config; empty default; no committed secret |
| 6. Tests, Pint, PHPStan | PASS | see Evidence |

## Adversarial Checks

- **Data boundary:** no media path/binary in the request; provider is text-only (D5-04/D5-05). PASS.
- **Retryability authority:** the worker's advisory `retryable` flag is never used for domain policy; `failureFromCode()` maps to the Laravel taxonomy (ADR-018 precedent). PASS.
- **Alignment choke point:** the validator constructs `TranslationSegmentData`, so non-finite/invalid timestamps are rejected at the boundary. PASS.
- **No mock-as-real claim:** provider tests use `Http::fake` and are labelled contract tests; the real self-hosted model gate remains P5-008 (D5-09). PASS.
- **No new vendor dependency:** model runtime is optional; absence yields a `CONFIGURATION_ERROR` envelope rather than a crash. PASS.
- **Scope:** no queue/writer/UI/export changes; no frozen contract touched. PASS.

## Findings / Notes

- INFO-1 (blocked by B-001): the worker `/translate` route wiring lives in the
  dirty baseline file `worker/main.py`, which also carries uncommitted Phase 3
  changes. The route is implemented and worker-tested in the working tree but
  cannot be committed without mixing the Phase 3/4 baseline (B-001).
  `worker/translation.py` and `worker/tests/test_translation.py` are committed.
- INFO-2: the real model runtime (`transformers`, NLLB default) is an optional
  dependency; the endpoint returns `CONFIGURATION_ERROR` when it is absent. The
  mandatory real-provider gate is P5-008 (D5-09); model capacity is Phase 7.
- INFO-3: `provider`/`model` identity is recorded from config on the persisted
  result, satisfying D5-04 identity.
- INFO-4: already-target / `und` passthrough policy is applied in
  `worker/translation.py`; consumption in the orchestration path is P5-004.

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  57 passed, 165 assertions.
- Full suite → 490 tests, 489 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- `php vendor/bin/pint ... --format agent` → passed.
- `php -d memory_limit=1G vendor/bin/phpstan analyse app/Translation
  app/Providers/AppServiceProvider.php --no-progress` → 0 errors.
- `worker/.venv/Scripts/python.exe -m pytest worker/tests/test_translation.py`
  → 4 passed.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.