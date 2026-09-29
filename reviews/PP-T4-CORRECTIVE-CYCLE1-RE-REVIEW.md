# PP-T4 — Corrective Cycle-1 Re-review

Date: 2026-09-28. Basis: `reviews/PP-T4-INDEPENDENT-REVIEW.md`
(CHANGES_REQUESTED, cycle 1: PP-T4-REV-01 MEDIUM) plus the corrective diff.
Reviewer pass logically separate from the corrective implementation pass;
findings re-verified on independently reproduced evidence.

## 1. Corrective diff inspected

- `app/Translation/ReferenceExternalTranslationProvider.php`: transport
  `TranslationException` passthrough now logs a `failed` outcome with the
  exception's mapped `failure_category` before rethrowing; `validatedResult()`
  is wrapped so frozen-validator rejections (`MissingSegments` /
  `MalformedOutput` / `UnsupportedSource`) likewise log `failed` with the
  mapped category before rethrowing. No mapping, retry, identity, or
  fail-closed semantic changed (log-then-rethrow only).
- `tests/Feature/Translation/ReferenceExternalTranslationProviderTest.php`:
  new test `AC6: validator rejection leaves a failed audit record with the
  mapped category` (index-mismatch → `MissingSegments` failed-log with
  request identity, exactly one dispatch). The pre-existing failure-log test
  header (accidentally consumed during test authoring) was restored; both
  tests pass.

## 2. Finding disposition

| Finding | Severity | Disposition |
|---|---|---|
| PP-T4-REV-01 unlogged validator-rejection runs | MEDIUM | RESOLVED — every terminal outcome (ceiling, transport throw, shaped failure, validator rejection, success) now emits exactly one adapter audit record carrying outcome + failure category; new test asserts the validator-rejection path |
| PP-T4-REV-02 HTTP rows seam-inapplicable | INFO | NOTED, no action (unchanged) |
| PP-T4-REV-03 nullable kind arm defensive | INFO | NOTED, no action (unchanged) |

## 3. Independently rerun evidence (cycle-2)

- Focused PP-T4 suites: 41/41 pass, 155 assertions (reproduced — includes
  the new REV-01 regression test).
- PP-T2/PP-T3 regression slice: 42/42 pass (reproduced).
- Pint: clean (reproduced). PHPStan: 0 errors (reproduced).
- Cycle-1 evidence (full suite 1213/1208/5/0; staleness 15/15; Revision
  143/143 on rerun; Export 45/45) stands: the corrective diff is
  log-then-rethrow only and cannot alter mapping/persist/select behavior;
  focused + regression reruns above confirm no drift.

## 4. AC matrix delta

AC6 now fully PASS (success + shaped-failure + validator-rejection audit
records asserted). All other AC1–AC12 verdicts from cycle 1 stand
unchanged. Scope audit remains clean (corrective diff touches one adapter
method flow + one test file; no new surface).

## 5. Verdict

```text
PP-T4 (corrective cycle 1) = VERIFIED
```

AC1–AC12 PASS on independently reproduced evidence; scope audit clean; no
remaining BLOCKER/HIGH/MEDIUM. Lifecycle `REVIEW → VERIFIED` evidenced by
this re-review artifact.
