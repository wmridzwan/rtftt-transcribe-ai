# P5-003 — Cycle 2 Internal Adversarial Pre-Review

Task: P5-003 — Translation Provider Boundary (corrective cycle 2)
Date: 2026-09-21
Origin: `reviews/P5-001A-P5-002A-P5-003-P5-004-P5-005-P5-007-independent-review.md`
§2 (P5-003 H-1, M-1, M-2, L-1..L-3, INFO)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Changes

- `TranslationResponseValidator::fromArray()` now takes the `TranslationInvocation`
  and validates strictly against it: target, segment count, index set,
  timestamps (tolerance), and source-language echo. It persists authoritative
  alignment copied from the invocation, never provider values.
- Strict type validation: non-string target/text, non-integer index, and
  non-numeric timestamps raise `TranslationException(MalformedOutput)` instead
  of PHP warnings or silent coercion (no array-to-string warning).
- `HttpTranslationProvider` maps transport `ConnectionException` to
  `ProviderTimeout`, other transport errors to `ProviderUnavailable`, and
  non-envelope HTTP statuses by class (401/403/404/405 → `ConfigurationError`,
  422 → `MalformedOutput`, 429/5xx → `ProviderUnavailable`, 504 →
  `ProviderTimeout`).
- `worker/translation.py`: corrected Standard Malay to `zsm_Latn`; set
  `tokenizer.src_lang` per segment (NLLB defaulted to English).
- `worker/requirements.txt`: declared the self-hosted model runtime
  (`transformers`, `torch`, `sentencepiece`).
- Tests: empty/partial/foreign/duplicate/misaligned/type/echo cases, transport
  and status mapping, and an NLLB mapping test.

## Findings Addressed

| Finding | Status |
|---|---|
| H-1 empty/partial/foreign responses accepted | FIXED (invocation-aligned validation + tests) |
| M-1 loose coercion / raw exception | FIXED (strict type checks; no raw warnings) |
| M-2 `msa_Latn` | FIXED (`zsm_Latn` + mapping test) |
| L-1 non-envelope errors retryable | FIXED (status classification) |
| L-2 transport exceptions unmapped | FIXED (ConnectionException mapping) |
| L-3 persisted identity from config | Retained by design (D5-04 provider identity); documented |
| INFO tokenizer src_lang | FIXED |
| INFO worker deps undeclared | FIXED (requirements.txt) |

## Adversarial Checks

- **No raw PHP warnings:** array text and non-numeric fields now throw typed
  exceptions; the test suite would surface warnings if emitted.
- **Authoritative alignment:** even a within-tolerance provider drift persists
  the invocation's timestamps and source language (test).
- **No mock-as-real claim:** contract tests use `Http::fake`; the real-model
  gate remains P5-008 (D5-09).

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  104 passed, 274 assertions.
- Full suite → 537 tests, 536 passed, 1 skipped, 2 warnings, 0 failures.
- Pint clean; PHPStan 0.
- `worker/.venv pytest worker/tests/test_translation.py` → 5 passed.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required. P5-003 remains CHANGES_REQUESTED at the review level until a
fresh independent re-review returns VERIFIED.