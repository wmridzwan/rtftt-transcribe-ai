# P5-002 — Independent Review: Translation Persistence / Atomic Writer

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Scope: `tasks/P5-002-translation-persistence-atomic-writer.md` only
(commit `6dbd62a`). This review does not modify implementation code or tests,
does not mark P5-002 DONE, and does not authorize P5-003 or any later task.
P5-001 is reviewed separately in `reviews/P5-001-independent-review.md`.

**Verdict: VERIFIED** — with three MEDIUM findings that are non-blocking under
the repository rubric (only BLOCKER/HIGH prevent VERIFIED) but which I
recommend be resolved **before P5-004 consumes the writer** (see §8). This is a
judgment call; the HPO may reasonably choose to require the fixes first.
VERIFIED is not DONE; HPO closure is required.

## 1. Contract Checked

`tasks/P5-002-translation-persistence-atomic-writer.md` (status at review start
`IMPLEMENTED_PENDING_REVIEW`). Frozen decisions: D5-03, D5-05, D5-06 (ADR-022).

## 2. Implementation Inspected (all read in full)

`app/Translation/TranslationResultWriter.php`, `app/Models/Translation.php`,
`app/Models/TranslationSegment.php`, the three migrations
`2026_09_21_000001..3`, `TranslationFactory`, `TranslationSegmentFactory`, and
`tests/Feature/Translation/TranslationPersistenceTest.php` (7 tests).

Live Git evidence: `git show --stat 6dbd62a` touches only new translation
files, the pre-review artifact, the task file, and the new test. `git diff HEAD`
over all P5-002 paths is empty. No Phase 3 table, model, or migration was
modified (D5-06 respected). Note the committed test depends on files that are
still untracked in the working tree; see GOV-3.

## 3. Acceptance Criteria

| AC | Result | Reviewer evidence |
|---|---|---|
| 1. Additive, reversible, SQLite-compatible migrations | PASS | Reproduced on a throwaway SQLite file: `migrate` → tables, `translations_active_target_unique`, `…_translation_id_segment_index_unique`; `migrate:rollback --step=3` → 0 leftover objects; re-`migrate` succeeds. Repo `database.sqlite` untouched (size/mtime unchanged). |
| 2. Unique target boundary at DB level | PASS (SQLite) | Partial unique index over `status IN (pending,queued,translating,completed)`; test `prevents a second active translation…` and `allows a failed and a new active…` pass. Applied for the `sqlite` driver only (LOW-3). |
| 3. Transactional writer, no partial completed | PASS (behavior), **untested** | Code uses one `DB::transaction`. Reviewer probe: a segment write forced to throw on the 2nd segment leaves 0 translations, 0 completed, 0 segments (full rollback). The repository contains **no test** for this (MEDIUM-3). |
| 4. Repeated writes don't duplicate | PASS | `is idempotent for repeated writes` (sequential). |
| 5. Completed translation never overwritten | PASS | `never overwrites a completed translation`; completed row is resolved first and returned unchanged. |
| 6. Source rows unchanged | PASS | Test compares transcript and segment rows before/after; writer only reads `transcription_id` and `detected_language`. |
| 7. Tests, Pint, PHPStan | PASS | reproduced below |

Also confirmed by probe: deleting the `Transcription` cascades to
`translations` and `translation_segments` (0 rows remain), consistent with the
ADR-005 cascade chain.

## 4. Commands Independently Reproduced by the Reviewer

- `php artisan test --compact tests/Unit/Translation tests/Feature/Translation`
  → 33 passed / 104 assertions (matches the implementer).
- PHPStan (`--memory-limit=1G`) on `app/Translation`, both models → 0 errors.
- `vendor/bin/pint --test` (read-only) on all P5-002 files → passed.
- Migration up / rollback / re-up on a throwaway SQLite file (above).
- Seven adversarial probes against the real writer, run from a temporary Pest
  file **outside the repository** (deleted afterwards; `git status` shows no
  translation-related change from the review).

**Implementer-reported, NOT reproduced:** full suite 466 total / 465 passed /
1 skipped / 0 failures. Per repo rules the full suite is run by the user; all
P5-002 changes are additive.

Probe 6 (below) is a **simulation** of a concurrent winner via a model event,
not a true multi-process race; it demonstrates the writer's reaction to a
unique-index collision, not real-world timing.

## 5. Findings

### BLOCKER / HIGH

None.

### MEDIUM-1 — `translationId` path is not bound to the result's target or to a legal lifecycle source state

`resolveTarget()` fetches `whereKey($translationId)` scoped only to
`transcription_id`; it does not require `target_language` to equal the result's
target, nor a status from which `Completed` is a legal transition
(`TranslationLifecycle` permits `translating→completed` only). Reproduced:

- **Probe 1:** a `queued` row with `target_language = en`, written with a
  Chinese result and `translationId = that row` → the row becomes `completed`
  with `target_language = en`, `full_text = 'ZH-TEXT'`, 2 segments. An
  English-labelled translation now contains Chinese content, and the DB index
  cannot catch it because the row's own key is unchanged.
- **Probe 2:** a `failed` row written via `translationId` becomes `completed`
  and `failure_code` is cleared — an edge the lifecycle forbids
  (`failed→queued` is the only exit).
- **Probe 3:** a `failed` row plus an active row for the same target, written
  via the failed row's id → raw `UniqueConstraintViolationException` (index
  catches it, but only as an unhandled DB error).
- Also: if `translationId` points at a *completed* row of a different target,
  the method silently returns that row as if the write had succeeded.

Impact is latent because no caller exists yet; P5-004 is the intended first
caller (a queued job would pass its claimed row id). This is a data-integrity
defect in the boundary this task exists to provide, so I rate it MEDIUM rather
than LOW. Suggested fix: constrain the lookup by `target_language`, require
status ∈ {`queued`,`translating`} (or assert via `TranslationLifecycle`), and
raise `TranslationException(InvalidRequest)` otherwise.

### MEDIUM-2 — Source alignment is not enforced at the persistence boundary, and no task owns it

D5-01 / PHASE5-PLANNING §4.7 call segment alignment "a hard rule". The writer
accepts any `TranslationResult` and marks it `completed`:

- **Probe 4a:** a result with **zero** segments against a transcript with 2
  source segments → `completed`, 0 segments persisted.
- **Probe 4b:** a result with one segment at index 77 / start 123.0 against a
  transcript whose segments are 0 and 1 → `completed`, persisted as-is.

`TranslationResult` only checks duplicate indices, so nothing verifies count,
index set, or inherited timestamps against the source. P5-003 AC 4 rejects
"malformed/partial responses" provider-side, but no task states that the
persisted result equals the source segment set. A completed translation with
missing or foreign segments would export as truncated or wrong text. The
writer already receives `$transcription`, so a check is inexpensive.
Recommendation: either add a writer-side guard (segment set and timestamps
equal the source's) or add an explicit acceptance criterion to P5-003/P5-004.

### MEDIUM-3 — Stated verification requirements are not met by tests

The task's Verification Requirements name "concurrency/duplicate tests".
The repository has direct-insert duplicate tests but:

- no test that a mid-write failure rolls back (AC 3). The internal pre-review
  marks this "a thrown write rolls back both. PASS" with no such test in the
  file. I confirmed by probe that the behavior is **correct** (§3 AC 3), so
  this is an evidence gap, not a defect — but an unevidenced PASS in the
  pre-review should not be relied upon;
- no writer-level concurrent/duplicate-race test;
- no test for the `translationId` path at all (which is where MEDIUM-1 lives).

### LOW-1 — Unique-index collision surfaces as a raw exception

**Probe 6 (simulated race):** when a competing completed row appears between
the writer's lookup and insert, the writer throws
`Illuminate\Database\UniqueConstraintViolationException`. It neither converges
on the winning row (idempotent outcome) nor wraps the error as
`TranslationException(PersistenceFailed)`. Row-level `lockForUpdate()` is a
no-op on SQLite. No duplicate can be persisted (index holds), so this is
robustness only; P5-004's CAS claim should serialize writers, which should be
confirmed when P5-004 is reviewed.

### LOW-2 — `translations.source_language` is taken from the transcript-level `detected_language`

PHASE5-PLANNING §4.2 says the transcript-level value is informational and
per-segment language is authoritative. The column is nullable and
informational, and the segments carry the authoritative marker, so this is
acceptable; it can misrepresent a mixed-language transcript if a consumer reads
it as authoritative. Note for P5-006/P5-007.

### LOW-3 — Uniqueness guard is SQLite-only

Accepted by the implementer's pre-review (P3-007 precedent; D7-01 owns the
production data-store decision). I agree it is not a defect for the canonical
environment, but AC 2 ("enforced at the database level") is satisfied only on
SQLite and must be revisited under D7-01.

### INFO-1

`TranslationFactory::failed()` sets `completed_at`; harmless test-data
semantics. `Transcription` gained no `translations()` relation (out of scope).

## 6. Architecture / Security Review

- D5-05: writer never writes `Transcription`/`TranscriptionSegment` — confirmed
  by code and the immutability test. D5-06: content lives only in the new
  tables. D5-03: target is part of the row key and index predicate, so multiple
  targets per source coexist (also probe-confirmed by the multi-row tests).
- No secrets, no I/O, no authorization surface introduced (no routes or
  controllers). Ownership enforcement is P5-006/P5-007 scope.
- `forceFill` is used only with server-derived values; `$fillable` is explicit.

## 7. Governance Observations

- **GOV-1 (dependency order):** the contract states `Dependencies: P5-001
  DONE`; `PHASE5-7-EXECUTION-CLASSIFICATION.md` lists "Depends (DONE)" and the
  dependency graph defines "requires DONE" as HPO-closed. P5-002 was implemented
  and committed while P5-001 was only `IMPLEMENTED_PENDING_REVIEW`. The Phase 3
  batch exception (ADR-017) expressly does not apply to other phases, and the
  "Controlled Parallel Execution Authorization" text is referenced in the
  repository but not itself present, so I could not find a rule permitting this.
  Impact is contained now that P5-001 is independently VERIFIED, but the HPO
  should ratify or note the deviation, and P5-001 should be closed DONE before
  P5-002's dependency gate is considered satisfied.
- **GOV-2 (documentation drift):** see `reviews/P5-001-independent-review.md`
  §8. `AGENTS.md` / `CURRENT_STATE.md` contradict ADR-022.
- **GOV-3 (traceability):** the committed P5-002 feature test creates
  `TranscriptionSegment` rows with a `language` attribute, which requires the
  untracked migration
  `2026_09_18_000001_add_language_and_widen_timestamps_to_transcription_segments_table.php`
  (and `TranscriptionSegment.php` in HEAD does not mention `language`). A clean
  checkout of `6dbd62a` is therefore not self-sufficient. This is the
  already-recorded `BLOCKERS.md` B-001 (uncommitted Phase 3/4 baseline), not a
  new defect, but it means committed-state reproduction depends on the dirty
  working tree.

## 8. Required Changes / Follow-ups

None required for VERIFIED. Per the orchestration policy, MEDIUM/LOW findings
become separate READY follow-up tasks referencing this review (they do not
start automatically). Recommended ordering:

1. Follow-up A (MEDIUM-1, MEDIUM-2, MEDIUM-3, LOW-1): tighten the writer's
   `translationId` path, decide and enforce source-alignment ownership, wrap or
   converge on unique-index collisions, and add the missing rollback,
   `translationId`, and race tests. **Recommended to complete before P5-004 is
   promoted/started**, since P5-004 is the first consumer of this path.
2. LOW-2 / LOW-3: carry into P5-006/P5-007 and D7-01 respectively.

## 9. Reviewer Conclusion

**VERIFIED.** All seven acceptance criteria are met on reviewer reproduction:
migrations reversible, unique boundary enforced (SQLite), transactional
rollback confirmed by probe, sequential idempotency and completed-protection
tested, source immutability tested; PHPStan and Pint clean; no BLOCKER or HIGH
findings. Three documented MEDIUM findings are latent (no caller yet) and are
recorded for follow-up. Not DONE until closed by the Human Product Owner.
VERIFIED does not authorize production deployment.
