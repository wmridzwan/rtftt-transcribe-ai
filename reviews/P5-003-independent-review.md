# P5-003 — Independent Review: Translation Provider Boundary

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Scope: `tasks/P5-003-translation-provider-boundary.md` only (commit `41ec5b3`,
plus the uncommitted `/translate` route in `worker/main.py`, B-002). This review
does not modify implementation code or tests, does not mark the task DONE, and
does not authorize any later task.

**Verdict: CHANGES_REQUESTED** (review cycle 1 of 3) — one HIGH, two MEDIUM,
four LOW, several INFO. The HIGH is an unmet acceptance criterion (AC 4)
reproduced against the real provider. Everything else in the boundary is sound
(text-only payload, bearer auth, taxonomy authority, neutral binding).

## 1. Contract Checked

`tasks/P5-003-translation-provider-boundary.md` (status at review start
`IMPLEMENTED_PENDING_REVIEW`). Frozen decisions D5-01, D5-04, D5-09 (ADR-022).
Dependency "P5-001 DONE": P5-001 is VERIFIED but not yet DONE; this is the
pattern ratified by ADR-023 §4 (predecessor implemented, committed, and later
VERIFIED), so I treat it as satisfied, not as a finding.

## 2. Implementation Inspected (all read in full)

PHP: `HttpTranslationProvider`, `TranslationRequest`,
`TranslationResponseValidator`, `TranslationWorkerError`, `config/translation.php`,
the `AppServiceProvider` binding, plus the P5-001 types they use
(`TranslationSegmentData`, `TranslationFailure`, `TranslationException`,
`TranslationAlignment`, `TranslationTarget`). Tests: `HttpTranslationProviderTest`,
`TranslationRequestTest`, `TranslationResponseValidatorTest`. Worker:
`worker/translation.py`, `worker/auth.py`, the `/translate` route and models in
`worker/main.py`, and `worker/tests/test_translation.py`. Precedent compared:
`app/Transcription/HttpTranscriptionProvider.php`.

Live Git evidence: `git diff HEAD --stat` over `app/Translation`, `config/translation.php`,
`AppServiceProvider.php`, and the three PHP test directories is empty (working
tree equals `41ec5b3`). The commit touches only the new translation files, the
binding, `BLOCKERS.md`, the task, and the pre-review. **`worker/main.py` is
modified-but-uncommitted** and mixes the `/translate` route with the previously
closed Phase 3/4 worker changes (`git diff HEAD -- worker/main.py` shows the
translation additions inside the larger baseline diff). I reviewed the route from
the working tree; see LOW-4.

## 3. Acceptance Criteria

| AC | Result | Reviewer evidence |
|---|---|---|
| 1. `TranslationProvider` resolved without hardcoding a vendor | PASS (with LOW-3) | Interface bound in `AppServiceProvider` from `translation.*` config; resolved by container in a test. The single adapter is the self-hosted HTTP one; the `provider` config value is a label, not a selector. |
| 2. Self-hosted provider sends text only; no media path/binary | PASS | `TranslationRequest` carries only ids, target, contract version, and per-segment index/timestamps/text/source language; unit and feature tests assert no `media_reference`/`storage_key`. The worker's Pydantic model has no media field. |
| 3. Valid responses map to `TranslationResult` preserving segment alignment | PASS for a well-formed, complete response | index, timestamps, text, source language preserved (feature test). |
| 4. Malformed/partial responses rejected with a taxonomy failure, not silently accepted | **FAIL — HIGH-1** (and MEDIUM-1) | Probes B1/B1z/B2/B3 below. |
| 5. Bearer secret server/config controlled; no secrets committed | PASS | token from `RTFTT_TRANSLATION_WORKER_TOKEN` with fallback to the transcription token, empty default; header asserted in test; worker `verify_token` guards `/translate`. No secret in the diff. |
| 6. Tests, Pint, PHPStan pass | PASS | see §4 |

## 4. Commands Independently Reproduced by the Reviewer

- `vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  57 passed / 165 assertions (matches the implementer).
- `vendor/bin/pint --test` (read-only) on all P5-003 PHP files and tests → passed.
- `phpstan analyse app/Translation app/Providers/AppServiceProvider.php
  --memory-limit=1G` → 0 errors.
- `worker/.venv/Scripts/python.exe -m pytest worker/tests/test_translation.py` →
  4 passed (against the **working tree** `worker/main.py`; 3 of the 4 import the
  uncommitted route).
- Adversarial probes (B1–B6, including one case per worker HTTP status) against
  the real provider/validator from temporary Pest files **outside the repository** (deleted afterwards; `git status` shows no
  probe or translation-related change from this review). `Http::fake` only; no
  network, no model.

**Implementer-reported, NOT reproduced:** full PHP suite (490/489/1 skipped) and
the full worker suite. **Not reproducible here:** the real translation model —
`transformers`/`torch` are not installed in `worker/.venv` and are not declared
in `worker/requirements.txt`, so the model path was reviewed by reading only.

## 5. Findings

### HIGH-1 — The provider boundary accepts partial and misaligned responses (AC 4)

`TranslationResponseValidator::fromArray()` receives only the parsed body and
the expected *target*; it never sees the request's segments, and
`HttpTranslationProvider` does not compare the result to `$invocation->segments`.
Reproduced against the real provider for a 2-segment invocation (indices 0,1;
0–4.999, 4.999–9.5):

- **B1:** worker returns 1 of 2 segments → **accepted**, 1-segment `TranslationResult`.
- **B1z:** worker returns `segments: []` with a valid target → **accepted**, 0 segments.
- **B2:** worker returns indices 77/78 at 100–102 s → **accepted** as-is.

AC 4 requires malformed/partial responses to be rejected with a taxonomy failure
"not silently accepted", and Scope 3 requires "segment-aligned segments". The
taxonomy already has `MissingSegments`/`MalformedOutput` for exactly this. A
zero-segment "success" for a non-empty request is the canonical partial
response. The pre-review's PASS on AC 4 cites "missing segments" (a missing
`segments` key) and target mismatch, neither of which covers a truncated list.

Mitigating context, stated so the HPO can weigh the rating: the P5-002A writer
independently rejects a non-aligned result before persistence, so no bad data
can be stored today and the impact is limited to the wrong layer and failure
attribution. I still rate it HIGH because an explicit acceptance criterion of the
task whose purpose is this boundary is unmet and reproduced. If the HPO reads
"partial" as "missing keys only", this reduces to MEDIUM and the verdict would
change; I do not think that is the plain reading.

Required fix: give the validator/provider the invocation's source segments and
reject, with `MissingSegments`/`MalformedOutput`, any result whose count, index
set, or timestamps (same tolerance as the writer) differ; add unit and feature
tests for B1, B1z, B2.

### MEDIUM-1 — Validator coerces types loosely and can leak a raw exception

`(int)`, `(float)`, `(string)` casts replace type validation. Reproduced:

- **B3:** `segment_index: "abc"` → accepted as `0`; `start/end_seconds: "x","y"`
  → accepted as `0.0/0.0`; `segment_index: 1.9` → truncated to `1`.
- **B3:** `text: ["a"]` → raw `ErrorException: Array to string conversion`, not a
  `TranslationException`, so the caller cannot map it to the taxonomy.
- Empty segment text is accepted (may be legitimate for a blank source).

Most coerced values would be caught downstream by the writer's alignment check,
but AC 4's "malformed … rejected with a taxonomy failure" is not met for the
non-numeric and array cases. Fix: `is_int`/`is_numeric`/`is_string` checks before
constructing DTOs and add tests. Non-blocking under the rubric but should ride
along with the HIGH-1 fix since it is the same function.

### MEDIUM-2 — Malay target maps to an invalid NLLB language code

`worker/translation.py` maps `"ms": "msa_Latn"`. The NLLB-200 code for Standard
Malay is `zsm_Latn`; `msa_Latn` is not in the tokenizer's
`FAIRSEQ_LANGUAGE_CODES` (verified against upstream
`transformers/models/nllb/tokenization_nllb.py`; `eng_Latn`, `zho_Hans`,
`tam_Taml` are present and correct). `convert_tokens_to_ids("msa_Latn")` would
return the unknown-token id, so `forced_bos_token_id` is wrong for the primary
product target and output would be silently wrong rather than an error.
**Not reproduced by execution** (model runtime unavailable, above); the evidence
is the upstream code table plus reading. Nothing in the worker tests touches
`NLLB_CODES`, `_translate_text`, or the missing-runtime path — the route test
patches `translate_segments`. The mandatory real-model gate (P5-008, D5-09) would
catch this, but the shipped mapping is in P5-003's scope. Fix: correct the code
and add a test asserting every mapped code exists in the tokenizer vocabulary
(skipped when the runtime is absent) plus a mapping-table unit test.

### LOW-1 — Non-envelope HTTP errors are all "ProviderUnavailable" and retryable

Probes B4: worker `401`, `403`, Pydantic `422`, and `500 "Worker token not
configured"` all map to `PROVIDER_UNAVAILABLE`, `isRetryable() = true`. Bad
credentials or an invalid request are deterministic, not transient. This mirrors
the Phase 3 `HttpTranscriptionProvider` precedent, so it is not a regression,
and Phase 5 is manual-retry only; consider `ConfigurationError` for 401/403/500
and `InvalidRequest` for 422.

### LOW-2 — Transport exceptions are not mapped

**B4a:** a timeout/connect failure surfaces as
`Illuminate\Http\Client\ConnectionException`, not a `TranslationException`. Same
as the Phase 3 provider (no `ConnectionException` handling anywhere in `app/`),
so precedent-consistent, but P5-004/P5-005 must catch it or the provider should
map it to `ProviderTimeout`/`ProviderUnavailable`. Confirm at the P5-004 review.

### LOW-3 — Provider/model identity is Laravel config, not what actually ran

**B5:** the persisted `provider`/`model` come from `translation.provider` /
`translation.model`, ignoring the worker's response. With defaults the record
says `self-hosted-default` while the worker runs
`facebook/nllb-200-distilled-600M`. The same env name `RTFTT_TRANSLATION_MODEL`
is read by both Laravel (default `self-hosted-default`) and the worker (default
the NLLB checkpoint) with different defaults, so identity is correct only if
both processes share the variable. Also, any `translation.provider` value builds
the same HTTP adapter (B5 recorded `provider=openai`), so the value is a label,
not a strategy selector (AC 1 remains met). The test fixtures use
`model: self-hosted-default`, which the real worker never emits. Recommend
recording the worker-reported model or a distinct env name.

### LOW-4 — Governance: the reviewed `/translate` route is uncommitted (B-001/B-002)

Already recorded in `BLOCKERS.md` and the pre-review; not a new defect. Effect
for the record: at commit `41ec5b3` alone, `worker/tests/test_translation.py`
would fail 3 of 4 tests (the route does not exist in HEAD's `worker/main.py`),
and I could review the route only from a working tree that also contains
unreviewed-in-this-cycle baseline hunks. HPO should commit the Phase 3/4
baseline so the route and its tests can land.

### INFO

- **INFO-1:** the scope names `SelfHostedTranslationProvider`; the class is
  `HttpTranslationProvider`. Cosmetic.
- **INFO-2:** `worker/requirements.txt` does not list `transformers`/`torch`/
  `sentencepiece`; disclosed as optional in the pre-review. Must be declared
  before the P5-008 real gate.
- **INFO-3:** `/translate` is an `async def` that runs blocking model inference,
  identical to `/transcribe`; acceptable now, a Phase 7 capacity item.
- **INFO-4:** empty `translation.worker_token` sends `Authorization: Bearer` (B6)
  and relies on the worker's 401/500; same as Phase 3.
- **INFO-5:** the worker's `retryable` flag is correctly not used for domain
  policy (ADR-018 precedent); `PROVIDER_FAILED` from the worker falls through to
  `ProviderFailed` as intended. Worker passthrough (`source == target`) never
  treats `und` as passthrough because targets are limited to four codes.

## 6. Architecture / Security Review

Consistent with D5-04 (neutral interface, self-hosted default, text-only) and
D5-09 (no mock claims the real gate; tests are labelled contract tests). Data
boundary is sound: no media path/binary, Pydantic ignores unknown request
fields, no ownership surface added (no routes/controllers in Laravel). The worker
endpoint reuses the existing bearer dependency. No secrets, no committed tokens.

## 7. Regression Risk

Low for Phases 3/4: new namespace, new config file, one added container binding.
The `AppServiceProvider::register()` change is additive. `worker/main.py`
already carries the baseline hunks.

## 8. Required Changes (cycle 1)

1. **Required (HIGH-1):** enforce segment count, index set, and timestamps
   against the invocation at the provider boundary with taxonomy failures; add
   tests for partial, empty, and foreign-index responses.
2. **Strongly recommended in the same cycle (MEDIUM-1, MEDIUM-2):** strict type
   validation in the validator; correct the Malay NLLB code and add a mapping
   test.
3. Optional/consider: LOW-1/LOW-2/LOW-3 (or carry into P5-004/P5-005).

Ownership stays with OpenCode (CHANGES_REQUESTED preserves ownership). This is
cycle 1 of 3.

## 9. Reviewer Conclusion

**CHANGES_REQUESTED.** AC 1, 2, 3, 5, 6 are met; AC 4 is not met at the provider
boundary and is reproduced. Reviewed tests pass; Pint and PHPStan are clean. The
fix for HIGH-1 is small and local. Not VERIFIED, not DONE. VERIFIED would not
authorize production deployment.
