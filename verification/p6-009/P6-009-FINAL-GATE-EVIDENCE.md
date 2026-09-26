# P6-009 Final Gate — Execution Evidence

## 1. Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d`
- Branch: `main`
- Working tree at execution start: pre-existing uncommitted residue unrelated
  to the gate (modified `AGENTS.md`, `BLOCKERS.md`, `CURRENT_STATE.md`,
  `DECISIONS.md`, `DECISION_QUEUE.md`, `app/Editing/RevisionService.php`,
  `app/Http/Controllers/TranscriptRevisionController.php`,
  `app/Http/Controllers/TranscriptionController.php`, `plan.md`,
  `resources/views/transcriptions/show.blade.php`, `routes/web.php`,
  `tasks/P6-005-split-merge-translation-invalidation.md`; untracked `docs/`,
  P6-008 review/task/test/verification artifacts, full-chain E2E evidence).
  No residue was reset, cleaned, or overwritten. Gate-owned changes are listed
  in §12.
- Preconditions verified before execution: P6-001..P6-008 DONE (HPO closures),
  P6-009 READY (`DECISION-P6-009-READY-001`; HPO-009-A/B/C DECIDED),
  `BLOCKERS.md` open blockers: none.
- Lifecycle: `READY` → `IN_PROGRESS` (2026-09-25, OpenCode gate executor).

## 2. Executor and environment

- Executor: OpenCode (Builder-equivalent gate executor; no self-review,
  no self-close).
- PHP 8.4.24 CLI. PHP tests: sqlite `:memory:`, `RefreshDatabase`
  (`phpunit.xml` hermetic env). Browser: real Chromium headless
  (`--no-sandbox --mute-audio`), Playwright `@playwright/test` from
  `node_modules`, app served via `php -S 127.0.0.1:8129 -t public
  verification/p4-003-server-router.php` against dedicated
  `database/p6-009-verification.sqlite` (seeded by
  `verification/p6-009-seed.php`; never production data).
- Front-end assets: prebuilt (`public/build` present from prior gates;
  no build step run by this gate).

## 3. AC matrix (fresh evidence unless noted)

| AC | Requirement | Result | Evidence |
|---|---|---|---|
| AC1 | Integrated revision workflow round-trips through persistence and reload | PASS | `P6009FinalGateTest` AC1: HTTP text edit (v1+v2) → timing edit (v3, end 12.0→12.5) → split `machine:2` (v4, 2 `struct:` children) → merge (v5); 5 durable versions, active = v5, machine snapshot byte-identical, workspace shows `Revision v5` + edited text + history + `data-translation-stale="ms"` |
| AC2 | Undo/redo/branch/historical activation/CAS preserve frozen semantics | PASS | AC2 test: undo v2→v1, redo v1→v2, undo + branch v3, `redoTarget()` null, sibling activation v2→(v3)→v2, stale-base activation → `history_conflict`, count unchanged, pointer unmoved |
| AC3 | Machine-source recoverable from any valid revision state | PASS | AC3 test: split + undo + branch + historical activation leave machine rows identical; revision-free transcript renders `Machine transcript` state |
| AC4 | P6-005 staleness semantics; no forbidden Phase 5 mutation; HPO-008-C preserved | PASS | AC4 test: split → `stale_at` set, reason `SEGMENT_STRUCTURE_CHANGED`, `stale_caused_by_revision_id` = split revision; `translation_segments` rows byte-identical; text + timing edits leave a second translation row `stale_at = null`; precedence `Structural > Timing > Textual` asserted on the policy |
| AC5 | Workspace/comparison reflects the active revision truthfully | PASS | AC5 test: `Revision v2`, edited text, `data-comparison-state="revision"`, `Translation: MS`, mismatch note present with translation; no-translation state renders `No translation available` with zero `Edited after the translation was produced` notes. Browser P6-009-01/09/10 corroborate |
| AC6 | TXT/SRT/VTT/DOCX reflect the active revision | FAIL | Finding F-001 (§4): all four exports render machine-source text despite active v2. Probe output retained below; committed skip in gate test references F-001 |
| AC7 | Stale/concurrent writes fail closed | PASS | AC7 test: stale edit base → `revision_conflict`; double-submit on consumed base → second rejected; count +1 only; active v3 with winner text; no partial rows |
| AC8 | Authorization/isolation intact | PASS | AC8 test: intruder GET show / POST edit / POST activate / GET export → 403; cross-transcription activation → `history_error`, pointer unmoved; unknown UUID → `revision_error`; admin view + activate allowed per owner-or-admin policy |
| AC9 | `ms/en/zh/ta/und` round-trip | PASS | AC9 test: edited `✓`-suffixed strings in all five languages round-trip through edit → active segments (languages `ms,en,zh,ta,und`) → workspace render → machine-source TXT export bytes. Observed: P6-003 trims edit input (`' und ✓ '` → `'und ✓'`); frozen behaviour, not a finding |
| AC10 | No D6-08/D6-09 surface | PASS | AC10 test: no `speaker*/diarization/waveform*/annotations/chapters/bookmarks` routes; no such tables; workspace has no `data-speaker/data-waveform/data-annotation` markers |
| AC11 | Regression/quality gates | PASS | §5: full suite 892/890/2 skipped/0 failures; Pint clean; PHPStan 0 errors; gate file 9 pass + 1 documented skip; browser 12/12 |

Historical evidence reused (component confidence only, never substituted for
the rows above): `reviews/P6-005-INDEPENDENT-REVIEW.md` (14/14 PASS),
`reviews/P6-008-INDEPENDENT-REVIEW.md` (AC1–AC10 PASS),
`tests/Feature/Editing/RevisionAppendRaceTest.php` (genuine two-process race;
concurrency beyond the sequential double-submit in AC7).

## 4. Finding F-001 (AC6 FAIL)

- AC: AC6. Severity: MAJOR (mandatory gate AC fails; user-visible: an edited
  transcript exports stale machine text in all four formats).
- Exact failure: `TranscriptionExportController::{exportTxt,exportSrt,exportVtt,exportDocx}`
  (`app/Http/Controllers/TranscriptionExportController.php:23,48,74,104`)
  read `$transcription->segments()->orderBy('segment_index')` (machine source
  only) and never consult the active revision, contradicting the frozen
  P6-001 §9 clause ("active revision is authoritative for presentation, copy,
  and export") cited by the P6-009 contract AC6.
- Reproduction (throwaway probe `P6009AC6ProbeTest`, since deleted; 4/4 FAIL):
  completed transcription with segments `Probe machine first/second` → text
  edit to `Probe EDITED first/second` (active v2, `revision_notice`) → GET
  each export route → 200 with machine text, e.g. TXT body
  `'...Probe machine first\n\nProbe machine second'`, SRT
  `'1\n00:00:00,000 --> 00:00:04,000\nProbe machine first...'`, VTT
  equivalent, DOCX `word/document.xml` containing `<w:t>Probe machine
  first</w:t>`. Full probe output is preserved in the executor session log;
  the committed gate test carries a documented skip referencing F-001 so the
  FAIL stays visible without breaking the suite.
- Owning scope: unowned gap — no P6 task implemented revision-aware exports
  (P6-002 explicitly excluded "translation/revision export changes beyond
  reading the active revision"; P6-003..P6-008 scopes contain no export work).
  Not a regression of any DONE task (Phase 4 export behaviour preserved).
- Blocks Phase 6 closure recommendation: yes (mandatory AC fails).
- Remediation requires separate HPO authorization (bounded revision-aware
  export task) or a superseding HPO scope decision narrowing AC6. The gate
  was not repaired, redefined, or retro-wired. Gate re-run required after
  resolution.

## 5. Fresh quality-gate results (exact)

- Gate file: `vendor/bin/pest tests/Feature/Editing/P6009FinalGateTest.php`
  → 10 tests, 9 passed, 103 assertions, 1 skipped (AC6 F-001), 0 failures.
- Full suite: `php artisan test --compact` → 892 tests, 890 passed,
  3443 assertions, 2 skipped (pre-existing 2FA skip + AC6 F-001 skip),
  2 warnings (pre-existing baseline), 0 failures.
- `composer lint:check` (Pint dry-run) → passed.
- `composer types:check` (PHPStan level 7) → 0 errors.
- Browser: `playwright.cmd test -c verification/playwright.p6-009.config.js`
  → 12/12 passed (`verification/p6-009/p6-009-browser-results.json`).
  During execution two spec expectations were corrected as harness bugs (not
  product findings): P6-009-09 (a pure split preserves text, so no mismatch
  note renders — truthful; spec now asserts alignment-unavailable markers),
  P6-009-11 (export links live in a Flux dropdown — attached, hidden until
  opened; spec asserts attachment + 200 downloads); P6-009-05 was made
  order-agnostic for reruns against the shared fixture DB.

## 6. Browser/runtime summary

Product-path flows on the seeded fixtures (`plain`, `linear`, `branched`,
`branched2`, `stale`): active-revision presentation + history +
comparison hooks; machine-source authority; undo/redo via toolbar;
sibling-branch activation beyond undo reach; reload durability; stale-base
conflict with no pointer move; staleness-cause marker equality with the P6-005
record; structural-alignment truthfulness; no-translation state with no
chronology claim; export entry points + 200 downloads in all four formats
(content-level AC6 covered by the PHP probe, §4); intruder 403.

## 7. Authorization / isolation evidence — PASS

See AC8 row. No Phase 6 path weaker than `TranscriptionPolicy`
(owner-or-admin for `view`/`update`) was found.

## 8. CAS / concurrency evidence — PASS

Stale `expected_base` rejected on edit/activate with session conflicts and no
new rows; active-pointer and version uniqueness intact; sequential
double-submit on a consumed base fails closed. Genuine two-process race
coverage reused historically (`RevisionAppendRaceTest`).

## 9. Export evidence

Routes `transcriptions.export.{txt,srt,vtt,docx}` return 200 with valid
payloads (TXT body, SRT `HH:MM:SS,mmm`, VTT `WEBVTT`, DOCX valid zip with
`word/document.xml`); completed-gating and `view` authorization enforced
(AC8). Content reflects the machine source — see F-001 (§4).

## 10. Unicode / language evidence — PASS

`ms`/`en`/`zh`/`ta`/`und` persisted, rendered, and exported without
corruption (AC9 test; browser fixtures carry en/ms/zh rows).

## 11. Deferred-scope check — PASS

D6-08/D6-09 remain deferred and unimplemented (AC10 test). No remediation
performed.

## 12. Files changed by gate execution

- `tasks/P6-009-phase6-integration-verification.md` (READY → IN_PROGRESS,
  executor record; Verification/Files Changed updated at close of execution).
- `tests/Feature/Editing/P6009FinalGateTest.php` (new; 9 pass + 1 documented
  F-001 skip).
- `verification/p6-009-seed.php`, `verification/p6-009-auth.setup.js`,
  `verification/playwright.p6-009.config.js`,
  `verification/p6-009/final-gate.spec.js`, `verification/p6-009/README.md`,
  `verification/p6-009-fixtures.json`,
  `verification/p6-009/p6-009-browser-results.json` (new harness + results).
- `verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` (this artifact).
- `database/p6-009-verification.sqlite` (local verification DB; untracked
  environment artifact).

No application, migration, route, or existing-test change was made. The
throwaway AC6 probe was deleted after capturing the failure output above.

## 13. Execution verdict

FAIL — AC6 fails (F-001, MAJOR). AC1–AC5 and AC7–AC11 pass with fresh
evidence. Phase 6 cannot be recommended for closure until F-001 is resolved
through a separately authorized path and the gate is re-run.
`PHASE 6 REMAINS OPEN`.
