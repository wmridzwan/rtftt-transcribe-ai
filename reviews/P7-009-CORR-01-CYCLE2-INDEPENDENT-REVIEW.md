# REVIEW - P7-009-CORR-01 Corrective Cycle 2 - Independent Re-Review

## Review Status

**VERIFIED** (reviewer verdict only - not DONE; closure is a Human Product Owner act).

Task File: `tasks/P7-009-CORR-01-upload-transcription-initiation-bridge.md`
("Corrective Cycle 2").
Binding history: `DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001`,
`DECISION-P7-009-CORR-01-CYCLE1-001`, `DECISION-P7-009-CORR-01-CLOSURE-001`;
reviews `P7-009-CORR-01-INDEPENDENT-REVIEW.md` and
`P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md` (both VERIFIED, AC14 not evidenced).

Reviewer: Claude Code - a fresh review session that did not implement Cycle 2.
Independence is by independent reconstruction of evidence per
`.ai/guidelines/ai-development-os.md`.

Date: 2026-10-02.

Evidence legend: **[R]** repository file/diff read by the reviewer; **[E]**
command executed by the reviewer in this session; **[PG]** reasoning against
PostgreSQL behaviour - **not executed** (no PostgreSQL exists in this
environment); **[I]** reported by the implementer and not relied on unless
re-executed.

### Reviewer boundary

- No application code, test, governance record or task state was modified.
  Nothing was committed or deployed. HEAD is `17a2e59`; `git status --short`
  listed the same 4 modified paths before and after the review
  (`CURRENT_STATE.md`, `app/Actions/TranscriptionResultWriter.php`, the task
  file, `tests/Feature/Transcription/TranscriptPersistenceTest.php`). The only
  addition is this file.
- Mutation experiments ran in a **scratch copy outside the repository**
  (session scratchpad), never in the working tree. Every PHP command in the repo
  used `phpunit.xml` (`APP_ENV=testing`, sqlite `:memory:`); no
  `--no-configuration`. `database/database.sqlite` mtime was unchanged
  (`2026-10-02 03:56:00`) before and after.
- `.ai/rules/` does not exist in this repository (CLAUDE.md: "continue without
  it").
- P7-009 Phase B = NOT AUTHORIZED. P7-007 = NOT AUTHORIZED. P7-012 = NOT
  AUTHORIZED. This review authorizes no deployment and does not claim AC14 PASS.

---

## A. Independent root-cause confirmation - CONFIRMED

Chain verified link by link against source, not against the implementation report:

| Link | Evidence |
|---|---|
| Carbon 3 `diffInSeconds()` returns `float` | [R] `composer.lock` nesbot/carbon 3.13.2; `vendor/nesbot/carbon/src/Carbon/Traits/Difference.php:404` `public function diffInSeconds($date = null, bool $absolute = false): float { return $this->diffInMilliseconds(...) / MILLISECONDS_PER_SECOND; }` |
| Writer used it unchanged | [R] `HEAD:app/Actions/TranscriptionResultWriter.php:89` `max(0, $completedAt->diffInSeconds($startedAt, true))` - `max(0, float)` stays a float. [E] with the original expression the production-value test reports `Expecting 185.832677 not to be float` - the pre-normalization value really is fractional. |
| Fractional by construction | [R] `started_at` is stamped by `ProcessTranscription` (`now()`) and persisted by Eloquent in `Y-m-d H:i:s` (`Grammar::getDateFormat()` = whole seconds; `Blueprint::timestamp` default precision); the writer re-reads it from the DB (`lockForUpdate()->first()`) while `completed_at = now()` carries microseconds. `.832677` in the production error is that completion-instant fraction. |
| Eloquent does not coerce on write | [R] model casts `processing_seconds => integer` (`Transcription.php:61`, `ProcessingJob.php:60`) apply on read. [E] proof: with the original writer the raw stored values read back through the query builder are `185.1`, `185.499999`, `0.4` - the float reached the driver and storage uncast. |
| Laravel binds a float as a string | [R] `Connection::bindValues()` (`Connection.php:749-763`): `is_int` -> `PARAM_INT`, `is_resource` -> `PARAM_LOB`, everything else -> `PARAM_STR`. `prepareBindings()` only rewrites `DateTimeInterface` and `bool`. |
| Both columns are integer storage | [R] `2026_09_09_000003_create_transcriptions_table.php:23` and `2026_09_09_000005_create_processing_jobs_table.php:21`: `unsignedInteger('processing_seconds')->nullable()`; no later migration alters either column (migration list grep); `PostgresGrammar::typeInteger` emits `integer` (`PostgresGrammar.php:858`). |
| PostgreSQL rejects it | [PG] a text parameter `185.832677` against an `integer` target is rejected with SQLSTATE 22P02 `invalid input syntax for type integer: "185.832677"` - identical to the production message. A whole float (`185.0`) binds as `"185"` and is accepted (this is why the `185.000000` boundary row passes even against the unfixed writer, [E]). Not executed. |
| Position in the transaction | [R] inside `persist()`'s transaction the order is: save #1 (text/language/model/started_at) -> `replaceSegments()` (inserts; `start_seconds/end_seconds` are `decimal(12,3)` since `2026_09_18_000001`) -> save #2 (status, `completed_at`, **`processing_seconds`**) -> attempt save (**`processing_seconds`**). In production the failure surfaced on the `processing_seconds` value, i.e. after the segment inserts had been accepted by PostgreSQL; the segment path is therefore not implicated. The transaction rolled back, then `ProcessTranscription::fail()` recorded `PERSISTENCE_FAILED` ([R] `ProcessTranscription.php:205-216`). |

Both affected columns are confirmed: `transcriptions.processing_seconds` and
`processing_jobs.processing_seconds`, fed by the single `$processingSeconds`
local (`TranscriptionResultWriter.php:106,114`).

Root cause: **independently confirmed.**

---

## B. Implementation review

Diff vs HEAD [R, E `git diff --stat`]: exactly four files. Application code is one
expression:

```php
// before
$processingSeconds = max(0, $completedAt->diffInSeconds($startedAt, true));
// after (plus a 2-line comment)
$processingSeconds = max(0, (int) round($completedAt->diffInSeconds($startedAt, true)));
```

- **Smallest correct change: yes.** One computed value feeds both rows, so a single
  normalization fixes both columns. No migration, model, cast, dependency,
  config, job, orchestrator, provider, worker or queue file changed
  (`git diff HEAD --stat` for `database/migrations composer.* config app/Models
  app/Jobs worker` is empty [E]).
- A write-side model cast (`set` mutator) or a schema change would be a wider
  change than the defect needs; the one-expression fix is preferable.
- `max(0, ...)` is now redundant (`diffInSeconds(..., true)` is absolute) but is
  the pre-existing guard and is harmless. INFO.
- Types: `round()` returns float, the `(int)` cast makes the bound value `int` ->
  `PDO::PARAM_INT`. PHPStan reports 0 errors [E].

---

## C. Integer normalization verdict

```
INTEGER NORMALIZATION VERDICT: APPROVED
```

**Chosen implementation:** round to the nearest whole second (PHP `round()`,
half away from zero), then `(int)`; `max(0, ...)` retained.

**Reviewer verdict:** APPROVED - a reasonable, deterministic interpretation; no owner
decision is required to proceed. Recommended (non-blocking): the HPO records the
rule at reconciliation so it becomes canonical.

**Contract evidence** (searched `DECISIONS.md`, `DECISION_QUEUE.md`, `plan.md`,
`architecture.md`, `PROJECT_CONTEXT.md`, `tasks/`, `reviews/`, `tests/`):

- **No explicit canonical rule exists** for how `processing_seconds` is derived
  from a sub-second interval. The only normative statements are the column
  contract ("integer seconds", unchanged) and `PROJECT_CONTEXT.md` section 15
  (`RTF = processing_seconds / audio_duration_seconds`). [R]
- The `media_files.duration_seconds` precedent is **an analogy, not a binding
  contract**: it quantizes a *measured media property* (`(int) round((float)
  $formatDuration)`, `MediaMetadataProbeService.php:166`) whereas
  `processing_seconds` quantizes an *elapsed interval*. It is nonetheless the
  closest in-repo convention for float seconds -> integer-seconds storage and it
  is the RTF denominator, so numerator and denominator now use the same
  quantization. The other in-repo conversions (`duration_ms` = `(int) round(...)`
  in four places; `SegmentTimestamp` rounds to ms) also round; `floor` appears
  only for *display* splitting (`TranscriptionSegment`, `TranscriptionController`
  minute/second formatting), not for stored quantities. [R]
- **Downstream use is insensitive to +/-1 s.** Consumers: `formatted_processing_time`
  (`intdiv` -> `mm:ss`/`h:mm:ss`) and `real_time_factor` (`round(x / duration, 4)`,
  shown to 2 decimals in `transcriptions/show.blade.php:349`). The jobs page
  shows Started/Completed at minute resolution (`g:i A`). No acceptance
  criterion, test, runbook or reconciliation job asserts exactness or compares
  `processing_seconds` with `completed_at - started_at`. [R]
- No migration of existing data is involved: persistence never succeeded in
  production for a real result, so there is no population with mixed semantics.

**Why - `round` vs `floor` vs truncation:**

- `floor` and truncation toward zero (`(int)` cast) are **identical here**:
  `diffInSeconds(..., true)` is absolute, so the value is never negative. There
  are only two distinct candidates: **round** and **floor/truncate** (`ceil` is
  not natural for an elapsed time).
- **Case for floor/truncate.** `started_at` is stored at whole-second precision, so
  `floor(completed - started)` equals the persisted `completed_at - started_at`
  *exactly*. Under `round`, a fraction >= .5 yields a value 1 s larger than the
  stored timestamp difference. [E, from the new test: for the production-shaped
  case `completed_at` is stored as `08:03:05`, `started_at` `08:00:00` (stored
  difference 185 s) while `processing_seconds` is 186.] Because the stored
  `started_at` truncates the true start instant, the computed interval
  over-states true elapsed time by 0.5 s on average; `floor` removes that bias,
  `round` does not. [R, analysis]
- **Case for round.** Consistent with `duration_seconds` and the RTF numerator;
  symmetric (no systematic truncation); the interval is already +/-1 s
  uncertain, so neither rule is more "true"; sub-second runs map to 0 or 1.
- **Conclusion.** Both are defensible, the difference is at most 1 s on jobs that
  run minutes to hours, nothing in the product observes it, and the choice is
  trivially reversible (one token, no schema, no stored data). Choosing `round`
  because it fixes PostgreSQL would be wrong - every integer normalization
  (`round`, `floor`, `ceil`) fixes PostgreSQL; the rule was judged on the
  contract, domain and downstream-use evidence above. That evidence does not
  contradict `round`, so it is approved. The one real trade-off (round vs the
  stored timestamp difference) is recorded as finding F-1 (LOW).

---

## D. Regression-test integrity

Tests [R]: `tests/Feature/Transcription/TranscriptPersistenceTest.php` - 1
production-value test + a 6-row dataset (7 new cases; 9 existing unchanged).

**Do they exercise the real path? Yes.** `p7009Cycle2Persist()` builds state via
`TranscriptionFixtures::scenario(Transcribing, Running)`, writes `started_at` to
the DB, freezes the clock with `travelTo(Carbon::parse('...832677'))`, attaches
`DB::listen`, and calls `app(TranscriptionResultWriter::class)->persist(...)` -
the real writer, which re-reads the row with `lockForUpdate()` exactly as in
production. No normalization happens in the test.

**Does the fractional value exist before normalization?** Yes. [E] with the
original expression the failure message is `Expecting 185.832677 not to be float`
- the writer itself produced 185.832677. The extra precondition
`now()->diffInSeconds(...)->toBeBetween(185.83, 185.84)` is re-derived in the test
rather than captured from the writer (weakness F-4, INFO), but the unfixed-writer
run proves the writer's actual value.

**False-PASS vectors checked:**

| Vector | Result |
|---|---|
| Eloquent `integer` cast on read hides defect | Not relied on. Assertions read `DB::table(...)->value('processing_seconds')` (query builder, no casts) and `toBe(186)` (strict `int`). |
| SQLite coercion hides defect | SQLite keeps non-integral values as REAL in an INTEGER-affinity column [E: stored `185.1`, `185.499999`, `0.4` with the original writer], so the raw-value assertions fail pre-fix; independently the `DB::listen` assertion checks the PHP-level bound type, which is the exact determinant of `PARAM_INT` vs `PARAM_STR` (`Connection::bindValues`). |
| Explicit casting inside the test | None; no `(int)`/`intval` on observed values. |
| Fixtures bypass the writer | No; fixtures only create rows, the writer persists. |
| Assertions after normalization elsewhere | No other normalization exists on the path (`forceFill` -> `save()`; no mutator on `processing_seconds`). |

**Fail-before / pass-after [E, reproduced in a scratch copy, writer swapped only]:**

| Writer variant | `TranscriptPersistenceTest.php` |
|---|---|
| Original (`max(0, float)`) | 16 tests: **10 passed, 6 failed** (production-value test `Expecting 185.832677 not to be float`; boundary rows stored `185.1`, `185.499999`, `185.5`, `185.999999`, `0.4`) - matches the implementer's report exactly. The `185.000000` row passes pre-fix because `185.0` binds as `"185"`. |
| `(int)` cast only (truncation) | 13 passed, 3 failed (`toContain(186)` and the .5/.999999 rows) - the tests pin the chosen rounding, as intended. |
| `floor` | identical to truncation: 13 passed, 3 failed. |
| Current (`(int) round`) | **16 passed, 69 assertions** [E, real repo and copy]. |

The copy's writer was restored and verified byte-identical to the repo's.

**Test weaknesses (none blocking):**
- SQLite only: PostgreSQL's integer enforcement is not exercised; compensated by
  the bound-type assertion (F-4).
- `toHaveCount(2)` on the captured `UPDATE ... processing_seconds` statements
  couples the test to exactly two writes. That is deliberate (it detects a third
  write path) but means a legitimate future split of the update needs a test edit.
- The suite pins `round` (a switch to `floor` requires changing the dataset), as
  the implementer disclosed.

---

## E. Sibling-path audit

Independent search (`app routes database config resources tests scripts
verification deploy bootstrap`) for `diffIn*`, `floatDiffIn*`, `secondsSince/Until`,
`microtime`, `hrtime`, `->diff(`, `diffForHumans`, `(int)`/`round`/`floor` and
every write of `processing_seconds`:

| Path | Classification | Evidence |
|---|---|---|
| `TranscriptionResultWriter` -> `transcriptions.processing_seconds`, `processing_jobs.processing_seconds` | **DEMONSTRATED SAME DEFECT** (fixed) | only app code that computes or writes `processing_seconds`; the only `diffInSeconds` in `app/ routes/ database/ config/` |
| `MediaMetadataProbeService` -> `media_files.duration_seconds` | NOT AFFECTED | `(int) round(...)` |
| `duration_ms` in `ProcessTranscription`, `ProcessTranslation`, `Reference*Provider` | NOT AFFECTED | `(int) round(...)`, log context only, no column |
| `worker/main.py` `processing_seconds` (`round(..., 3)`) | NOT AFFECTED | no PHP consumer reads or stores it (grep of `app/`) |
| `transcription_segments.start/end_seconds`, translation/revision segments | NOT AFFECTED | `decimal(12,3)`; PostgreSQL already accepted the segment inserts before the failing statement in production |
| `app/Capacity/*` (`hrtime` timer, `WorkloadRunner` DB insert) | NOT AFFECTED | timer output goes to evidence JSON; DB use is a rolled-back segment insert |
| `DemoTranscriptionController`, seeders, factories | NOT AFFECTED | integer literals / `numberBetween` |
| `progress_percentage` (0 / 100 literals), `sample_rate`, `channels`, `file_size_bytes`, `segment_index`, revision `version`/`position` | NOT AFFECTED | literals, explicit `(int)` or counts; no Carbon-derived float |
| `CleanupStaging` `diffForHumans()`, `StagingClaimCasProtocolTest` `diffInMinutes` | NOT AFFECTED | display / assertion only, nothing persisted |
| `TranslationResultWriter` | NOT AFFECTED | no elapsed-seconds column on translations |

I **confirm** the implementer's classifications and found **no second
production-equivalent defect**. **POTENTIAL PATTERN ONLY:** none found. One
pre-existing semantic note (not a defect, not changed) is recorded as F-5.

No second write path can still let a fractional value through: all writes of
`processing_seconds` outside tests/seeders/factories are the two lines of the
writer (`:106`, `:114`), both fed by the same normalized local; the new
`toHaveCount(2)` assertion would also catch an added write.

## Persistence targets and atomicity (review points 4 and 5)

- Both targets receive the same normalized integer [R `:106,:114`; E bound-value
  and raw-read assertions in the production-value test, both `186`].
- **Atomicity preserved.** The change replaces a pure expression evaluated
  *before* the first `forceFill`; no statement was added, removed or reordered.
  Provider result -> transcript fields -> segments -> `Transcription` Completed
  -> `ProcessingJob` Completed all remain inside the one
  `$this->database->transaction(...)` boundary. A failure at any statement still
  rolls back the whole boundary (and, in production, the pre-fix failure left the
  rows unchanged and `fail()` recorded `PERSISTENCE_FAILED`). The new test
  asserts the success outcome (Completed, text, `completed_at`, one segment,
  attempt Completed, progress 100, `failure_code` null); the existing
  rollback/idempotence/duplicate-index/cross-user tests in the same file still
  pass [E].

---

## F. Verification assessment

| Check | Result |
|---|---|
| `php artisan test --compact tests/Feature/Transcription/TranscriptPersistenceTest.php` | **[E] 16/16 passed, 69 assertions** (matches [I]) |
| Same file, original writer (scratch copy) | **[E] 10 passed / 6 failed** - defect reproduced (matches [I]) |
| `php artisan test --compact tests/Feature/Transcription` | **[E] 169/169 passed, 737 assertions** (matches [I]) |
| Full suite `php -d memory_limit=2G vendor\bin\pest --compact` (one run) | **[E] 1302 tests: 1296 passed, 1 error, 5 skipped, 4 warnings** (5192 assertions). The single error is `LogContextTest::it_counts_attempt_ordinals_across_attempts_for_the_same_transcription` (UNIQUE `processing_jobs.transcription_id`) - see below. |
| Full suite, implementer run A | [I] 1302 / 1297 passed / 5 skipped / 0 failed - consistent with my run once the flake does not trigger (1297 + 5 skipped = 1302). Not independently reproduced as a clean run; my one run hit the flake. |
| Test-count baseline | Cycle 1 review recorded 1295 tests; +7 new cases = 1302. [R, E] consistent. |
| Warnings | **4 warnings with empty detail** - identical to the pre-change baseline recorded in `P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md:364` (1295 tests, "4 PHPUnit warnings with empty detail"). The reporter emits no detail; not attributable to the touched files (no new warning count). |
| Skips (5) | [R] same environment-gated skips as the Cycle 1 review (3 Redis queue integration, 1 RedisPosture, 1 FFprobe). |
| Pint | **[E] pass** on the two touched PHP files (run in the scratch copy; no change produced; copy files byte-identical to the repo's). Not run with `--dirty` in the repo to avoid any write. |
| PHPStan `phpstan analyse --memory-limit=1G --no-progress` | **[E] 0 errors** |
| PostgreSQL run | **NOT RUN - blocked** (no PostgreSQL/psql/Docker locally). The rejection is established from source/driver evidence, not reproduced. |
| `Phase3IntegrationVerification`, real faster-whisper, AC14 on target host | NOT RUN - need live Redis/real inference/target host |

**`LogContextTest` flake (full-suite run B).** Convincingly the pre-existing
TD-008 random-factory flake and unrelated to this change:

1. [R] The test creates two `ProcessingJob::factory()` rows for one transcription
   with factory-random statuses; when both land on an active status the P3-007
   partial unique index rejects the second insert. It never touches
   `TranscriptionResultWriter`, `processing_seconds` or `started_at`.
2. [E] **Reproduced on the unfixed writer**: with the original expression in the
   scratch copy, `LogContextTest.php` failed **2 of 25** isolated runs with the
   identical `UNIQUE constraint failed: processing_jobs.transcription_id` error
   (23 passed). With the fix it passes in isolation in the same way (implementer
   6/6 [I]).
3. [R] Documented before this change: `reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`
   (run 2, identical failure), `reviews/P7-009-CORR-01-INDEPENDENT-REVIEW.md:257,345`
   (reproduced 3/14 at the Cycle 1 baseline; TD-008 OPEN/MEDIUM),
   `reviews/P6-001-corrective-independent-re-review.md`.
4. The failing SQL in my run shows status `queued` with a prior active row, the
   same mechanism.

---

## G. Governance assessment

[R] `CURRENT_STATE.md` (Phase 7 table row and "Active Task" section) and the task
file ("Status", AC14 row, "Corrective Cycle 2") represent:

```
P7-009-CORR-01 = REVIEW - CORRECTIVE IMPLEMENTATION COMPLETE;
AWAITING INDEPENDENT RE-REVIEW. Not VERIFIED. Not DONE.
```

- The production AC14 failure is preserved (task AC14 row, lifecycle trail,
  CURRENT_STATE; `invalid input syntax for type integer: "185.832677"`,
  `PERSISTENCE_FAILED`). AC14 = NOT PASS.
- The earlier VERIFIED / ACCEPTED FOR CLOSURE history is **not rewritten**: the
  prior state is retained verbatim under "Prior state ... preserved as history"
  (CURRENT_STATE) and "Prior state (historical, preserved)" (task file);
  `DECISIONS.md` and `DECISION_QUEUE.md` are unmodified (`git status`) and still
  carry `DECISION-P7-009-CORR-01-CLOSURE-001`; the commit `42c179f` is untouched.
  Edits to state-bearing lines (Phase 7 row, section lead-in, task Status) add the
  current state and label the old text as history; no historical record was
  removed.
- Cycle 2 is not falsely recorded as VERIFIED or DONE.
- Phase B, P7-007 and P7-012 remain NOT AUTHORIZED
  (`CURRENT_STATE.md:29-31, 347`; task file "Governance state after this cycle").
- `TARGET_HOST_NOT_READY` stands.

**Reconciliation of `DECISIONS.md` / `DECISION_QUEUE.md`: after the verdict, not
now.** The Cycle 2 authority is an HPO chat instruction recorded in the task file
and `CURRENT_STATE.md` (repo artifacts, not chat-only), the implementer correctly
did not invent a decision ID, and the Cycle 1 closure sequence recorded the
closure decision *after* the VERIFIED verdict. After this verdict the HPO should
record (IDs assigned by the HPO / the repository's decision process; none invented
here): (a) the Cycle 2 corrective authorization and the AC14 first-attempt
failure; (b) the integer-normalization rule (round-to-nearest, F-1) if adopted;
(c) the closure/commit/deploy authorization for the corrected release; and (d)
a note that `DECISION-P7-009-CORR-01-CLOSURE-001`'s "VERIFIED / ACCEPTED FOR
CLOSURE" no longer describes the current lifecycle state. Until then
`DECISIONS.md` (rank 3) still reads VERIFIED while `CURRENT_STATE.md` (rank 5)
reads REVIEW; the task file and CURRENT_STATE explain it, so this is a recorded
lag, not a conflict in current authority (F-2, INFO).

**Resulting lifecycle state after this review:** `P7-009-CORR-01` = **VERIFIED
(Corrective Cycle 2)** as a reviewer verdict only - **pending HPO closure /
reconciliation; not DONE; no deployment authorized.** Next governance step
belongs to the HPO.

---

## H. Findings

No BLOCKER. No HIGH. No MEDIUM.

| ID | Severity | Finding |
|---|---|---|
| F-1 | LOW | `round()` can make `processing_seconds` 1 s larger than the persisted `completed_at - started_at` (whole-second timestamps); `floor` would match exactly. No consumer, AC or test is sensitive to it; disclosed by the implementer; approved. Recommend HPO records the rule (round-to-nearest) at reconciliation; one-token switch if the HPO prefers floor (dataset values change). |
| F-2 | INFO | Governance lag: no `DECISION-...` ID/record for Cycle 2 authorization in `DECISIONS.md`/`DECISION_QUEUE.md`; `DECISIONS.md` still reads VERIFIED / ACCEPTED FOR CLOSURE with no Cycle 2 annotation. Reconcile after the verdict (HPO-assigned ID). Not edited by this review. |
| F-3 | INFO | `[PG]` unreached statements: in production, execution stopped at the `transcriptions` UPDATE; the `processing_jobs` UPDATE (status, `progress_percentage` smallint, `completed_at`, `processing_seconds`, `error_message`, `failure_code`) and the post-persist code have never run on PostgreSQL for this path. By inspection all bound values are type-correct (ints, strings, timestamps, nulls); the AC14 rerun is the evidence. |
| F-4 | INFO | Test limits: SQLite-only (PG enforcement not exercised; compensated by the bound-PHP-type assertion that decides `PARAM_INT`/`PARAM_STR`); the `toBeBetween(185.83, 185.84)` precondition is re-derived in the test, not captured from the writer (the unfixed-writer run proves the real value); the `185.000000` dataset row does not discriminate old vs new (it passes pre-fix); tests pin `round`. |
| F-5 | INFO | Pre-existing, unchanged, out of scope: `processing_seconds` for **both** rows is computed from `Transcription.started_at` (set once, `?? now()`, not reset by retry [R]) rather than the attempt's own `started_at`; for a retried transcription the attempt value would include earlier attempts' time. Not a Cycle 2 defect; no demonstrated production failure. Candidate for a separate, HPO-decided follow-up only if it matters. |
| F-6 | INFO | `LogContextTest` TD-008 flake: pre-existing (reproduced 2/25 on the unfixed writer); unrelated; not modified. |
| F-7 | INFO | `max(0, ...)` is redundant after the absolute diff; harmless, pre-existing guard. |

---

## I. Final verdict

Corrective Cycle 2 is a one-expression, root-cause-correct fix; the regression
tests exercise the real writer, detect the original defect (6 failures reproduced
against the unfixed writer) and pin the normalized, integer-bound values for both
columns; both persistence targets and the transaction/completion boundary are
unchanged otherwise; the sibling audit found no second defect; the only failing
full-suite item is the documented, reproduced-on-baseline TD-008 flake; Pint and
PHPStan pass; governance state is correctly REVIEW with history preserved.

Deployment eligibility: the code/schema/binding evidence plus the regression
coverage is sufficient to make the corrective implementation **eligible for the
next authorized governance step** (HPO reconciliation -> new immutable release ->
deploy -> AC14 rerun). The PostgreSQL rejection was established by source/driver
reasoning, not executed locally; this review proves nothing about production.

```
P7-009-CORR-01 Corrective Cycle 2 = VERIFIED
```

Production AC14 remains NOT PASSED and must be rerun on the target host after authorized deployment.

Sequence that still applies (none started by this review): independent VERIFIED
(this) -> HPO reconciliation/closure decision -> immutable release (existing
release not modified in place) -> deploy -> AC14 rerun on the target host ->
PostgreSQL concurrency checks carried forward from Cycle 1 -> re-run Target-Host
Readiness. P7-009 Phase B, P7-007 and P7-012 remain NOT AUTHORIZED.
