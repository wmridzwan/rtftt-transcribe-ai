# P6-009 Final Gate RERUN — Execution Evidence (P6-009-RERUN-01)

Full final-gate rerun after P6-010 DONE. This is a complete re-evaluation of
AC1–AC11 with fresh integrated evidence — not a remediation test. Historical
PASS results from the first execution were not treated as sufficient for any
rerun verdict.

## 1. Baseline

- Rerun identity: `P6-009-RERUN-01` (2026-09-25, executor OpenCode,
  Builder-equivalent gate executor; no self-review, no self-close).
- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d` (same baseline as the first
  execution; no commit was made by this rerun).
- Branch: `main`.
- Working tree at rerun start: pre-existing uncommitted residue unrelated to
  the gate plus P6-010 Builder-owned changes (now DONE/closed). Recorded
  residue: modified `AGENTS.md`, `BLOCKERS.md`, `CURRENT_STATE.md`,
  `DECISIONS.md`, `DECISION_QUEUE.md`, `app/Editing/RevisionService.php`,
  `app/Http/Controllers/TranscriptRevisionController.php`,
  `app/Http/Controllers/TranscriptionController.php`,
  `app/Http/Controllers/TranscriptionExportController.php` (P6-010
  remediation, independently VERIFIED), `plan.md`,
  `resources/views/transcriptions/show.blade.php`, `routes/web.php`,
  `tasks/P6-005-split-merge-translation-invalidation.md`; untracked `docs/`,
  P6-008/P6-009/P6-010 review/task/test/verification artifacts. No residue
  was reset, cleaned, normalized, or overwritten.
- Lifecycle at rerun start: `IN_PROGRESS` (first execution verdict FAIL,
  finding F-001). The rerun is recorded under this legal existing lifecycle;
  no new lifecycle transition was invented for the rerun.
- Preconditions verified before rerun: P6-001..P6-008 DONE (HPO closures),
  P6-010 DONE (`DECISION-P6-010-CLOSURE-001`, independently reviewed
  VERIFIED), P6-009 READY authority intact (`DECISION-P6-009-READY-001`),
  `BLOCKERS.md` open blockers: none.

## 2. Historical FAIL preservation

The first execution evidence is preserved byte-identical under
`verification/p6-009/` (untouched by this rerun):

- `verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` §13 verdict remains
  `FAIL` (AC6, F-001, MAJOR) — old AC6 was not flipped, old F-001 was not
  rewritten, no previous browser/test artifact was overwritten or deleted.
- Historical F-001 reference: 4/4 formats exported the machine source
  despite an active edited revision (throwaway probe, since deleted; output
  quoted in the historical artifact §4).

## 3. Environment

- Executor: OpenCode (Builder-equivalent gate executor).
- PHP 8.4.24 CLI. PHP tests: sqlite `:memory:`, `RefreshDatabase`
  (`phpunit.xml` hermetic env).
- Browser: real Chromium headless (`--no-sandbox --mute-audio`), Playwright
  `@playwright/test` from `node_modules`, app served via
  `php -S 127.0.0.1:8130 -t public verification/p4-003-server-router.php`
  (rerun port; the original run used 8129) against dedicated
  `database/p6-009-rerun-01.sqlite` (seeded by
  `verification/p6-009-rerun-01-seed.php`; never production data; the
  original `database/p6-009-verification.sqlite` was never opened).
- Front-end assets: prebuilt (`public/build` present; no build step run).

## 4. AC matrix (all freshly executed for this rerun)

| AC | Requirement | Result | Fresh rerun evidence |
|---|---|---|---|
| AC1 | Integrated revision workflow round-trips through persistence and reload | PASS | Gate test AC1 (HTTP text edit v2 → timing edit v3 → split v4 → merge v5; 5 durable versions, active v5, machine snapshot byte-identical, workspace `Revision v5` + history + `data-translation-stale="ms"`) |
| AC2 | Undo/redo/branch/historical activation/CAS preserve frozen semantics | PASS | Gate test AC2 (undo v2→v1, redo v1→v2, undo + branch v3, `redoTarget()` null, sibling activation, stale-base → `history_conflict`, count unchanged, pointer unmoved) |
| AC3 | Machine-source/original recoverable from any valid revision state | PASS | Gate test AC3 (split + undo + branch + historical activation leave machine rows identical; revision-free transcript renders `Machine transcript`) |
| AC4 | P6-005 staleness semantics; no forbidden Phase 5 mutation; HPO-008-C preserved | PASS | Gate test AC4 (split → `stale_at` + `SEGMENT_STRUCTURE_CHANGED` + caused-by split revision; `translation_segments` byte-identical; text/timing edits persist no staleness; precedence asserted) |
| AC5 | Workspace/comparison reflects the active revision truthfully | PASS | Gate test AC5 (`Revision v2`, `data-comparison-state="revision"`, mismatch note with translation, no-translation state with zero chronology claims) + browser RERUN-01/09/10 |
| AC6 | TXT/SRT/VTT/DOCX reflect the active revision | PASS | Gate test AC6 rewritten (41 assertions, §5); P6-010 export suites 37/37; browser RERUN-11 content check (edited text in TXT/SRT/VTT, machine fallback on plain fixture) |
| AC7 | Stale/concurrent writes fail closed | PASS | Gate test AC7 (stale base → `revision_conflict`; double-submit on consumed base → second rejected; count +1 only; winner active v3; no partial rows) |
| AC8 | Authorization/isolation intact | PASS | Gate test AC8 (intruder show/edit/activate/export → 403; cross-transcription activation → `history_error`, pointer unmoved; unknown UUID → `revision_error`; admin view + activate per owner-or-admin policy) + browser RERUN-12 (intruder 403) |
| AC9 | `ms/en/zh/ta/und` round-trip incl. export payloads | PASS | Gate test AC9 strengthened (✓-marked strings in all five languages through edit → active segments → workspace render → TXT/SRT/VTT/DOCX payload bytes) |
| AC10 | No D6-08/D6-09 surface | PASS | Gate test AC10 (no speaker/diarization/waveform/annotation/chapter/bookmark routes, tables, or workspace markers) |
| AC11 | Regression/quality gates | PASS | §6: full suite 900/899/1 skipped/0 failures; Pint clean; PHPStan L7 0 errors; gate file 10/10; editing 127/127; export 37/37; translation/comparison/transcript-experience 165/165; browser 12/12 |

## 5. AC6 export verification (fresh, post-P6-010)

Rewritten gate test `AC6` (replacing the committed F-001 skip, as reserved
for the rerun by the P6-010 contract) proves on the product path:

- Text edit via HTTP → TXT contains all five edited unicode strings and
  none of the machine strings; SRT first block
  `1\n00:00:00,000 --> 00:00:04,000\nRerun edited pertama ✓`;
  VTT starts `WEBVTT` with active rows; DOCX `word/document.xml` contains
  the edited unicode strings and none of the machine strings.
- Timing edit via HTTP (position 2 end 12.0→12.5) → SRT
  `00:00:08,000 --> 00:00:12,500`, VTT `00:00:08.000 --> 00:00:12.500`.
- Structural split of the active revision → SRT shows 6 entries with the
  split interior boundaries `00:00:00,000 --> 00:00:02,000` /
  `00:00:02,000 --> 00:00:04,000`.
- Historical activation (sibling v2 over active v4) changes TXT output with
  zero new revision rows.
- Machine fallback: revision-free transcript exports machine rows in TXT/SRT
  with no edited text.
- Machine snapshot byte-identical after all editing + exporting.

| Format | Active revision | Fallback | Result |
|---|---|---|---|
| TXT | ordered active text (unicode intact) | machine text iff no valid active revision | PASS |
| SRT | active text + position order + active timing (`HH:MM:SS,mmm`) | machine rows iff no valid active revision | PASS |
| VTT | `WEBVTT` + active text/order/timing (`HH:MM:SS.mmm`) | machine rows iff no valid active revision | PASS |
| DOCX | title + ordered active paragraphs, valid document | machine rows iff no valid active revision | PASS |

## 6. F-001 comparison (historical vs rerun)

| | Historical first run | Rerun P6-009-RERUN-01 |
|---|---|---|
| TXT | machine source (FAIL) | active revision (PASS) |
| SRT | machine source (FAIL) | active revision incl. active timing (PASS) |
| VTT | machine source (FAIL) | active revision incl. active timing (PASS) |
| DOCX | machine source (FAIL) | active revision (PASS) |
| Verdict | FAIL (F-001 MAJOR) | 4/4 correct; F-001 remediation RESOLVED |

The historical FAIL is preserved (§2); the rerun proves the fixed
integration behavior itself and does not rely on P6-010 review evidence.

## 7. Fresh quality-gate results (exact)

- Gate file `vendor/bin/pest tests/Feature/Editing/P6009FinalGateTest.php`
  → 10 tests, 10 passed, 157 assertions, 0 skipped, 0 failures
  (1 empty-detail reporter warning; pre-existing environment pattern, §9).
- Editing suite `tests/Feature/Editing/` → 127 passed, 707 assertions,
  0 failures.
- Export suites (`TranscriptRevisionAwareExportTest` +
  `TranscriptExportTest` + `TranscriptExportHardeningTest` +
  `Translation/TranslationExportTest`) → 37 passed, 181 assertions,
  0 failures.
- Translation/workspace suites (`tests/Feature/Translation/` +
  `tests/Feature/Comparison/` + `tests/Feature/TranscriptExperience/`) →
  165 passed, 717 assertions, 0 failures.
- Full suite `php artisan test --compact` → 900 tests, 899 passed,
  3542 assertions, 1 skipped (pre-existing 2FA skip), 4 empty-detail
  reporter warnings (pre-existing pattern, §9), 0 failures.
- `composer lint:check` (Pint) → passed.
- `composer types:check` (PHPStan level 7) → 0 errors.
- Browser `playwright.cmd test -c
  verification/playwright.p6-009-rerun-01.config.js` → 12/12 passed
  (`verification/p6-009-rerun-01/p6-009-rerun-01-browser-results.json`:
  12 specs, 12 ok).

## 8. Browser/runtime summary (fresh rerun set)

Product-path flows on rerun fixtures (`plain`, `linear`, `branched`,
`branched2`, `stale`): workspace load, revision editing presentation,
reload durability, undo/redo via toolbar, branch-after-undo sibling
activation, historical activation + active marker, staleness-cause marker
equality with the P6-005 record, comparison truthfulness (mismatch and
no-translation states), export TXT/SRT/VTT/DOCX 200 downloads with
active-revision content (RERUN-11), machine fallback on the plain fixture,
authorization fencing (intruder 403), stale/CAS conflict presentation with
no pointer move.

## 9. Observations (non-findings, preserved)

- Empty-detail reporter warnings (gate file 1; full suite 4) match the
  documented pre-existing pattern (P6-010 Builder report + independent
  review): identical warnings reproduce on untouched files/tests in this
  environment. No failure, no skip, no assertion loss is associated with
  them. Not classified as a finding; carried as a preserved observation.
- Non-blocking carryforwards from owning artifacts remain preserved and
  were not re-litigated: Phase 5 LOW/INFO, P6-004 INFO, P6-005
  MINOR-1/OPTIONAL-1, P6-008 OPTIONAL-1.

## 10. Authorization / isolation — PASS

See AC8 row + browser RERUN-12. No Phase 6 path weaker than
`TranscriptionPolicy` (owner-or-admin for `view`/`update`) was found;
Completed-only export gating enforced (P6-010 AC9 suite green, unchanged
code path re-exercised by the rerun's export tests).

## 11. CAS / concurrency — PASS

Stale `expected_base` rejected on edit/activate with session conflicts and
no new rows; active-pointer and version uniqueness intact; sequential
double-submit on a consumed base fails closed (AC2/AC7 fresh). Genuine
two-process race coverage reused historically (`RevisionAppendRaceTest`,
green in the full suite).

## 12. Unicode / language — PASS

`ms`/`en`/`zh`/`ta`/`und` persisted, rendered, and exported without
corruption: AC9 (active-revision ✓ strings in TXT/SRT/VTT/DOCX payload
bytes) + AC6 (rerun edited unicode strings across all four formats) +
browser fixtures (en/ms/zh rows) + fallback plain-fixture check.

## 13. Deferred-scope check — PASS

D6-08/D6-09 remain deferred and unimplemented (AC10 fresh). No
reactivation occurred; no remediation performed.

## 14. Findings

None. No BLOCKER/HIGH/MEDIUM/LOW finding survives fresh verification.
F-001 (historical, MAJOR) is RESOLVED by P6-010 DONE and verified fixed by
this rerun's fresh AC6 proof; the historical FAIL record is preserved in
`verification/p6-009/` and remains the durable record of the first
execution.

## 15. Files changed by rerun execution (rerun-owned only)

- `tests/Feature/Editing/P6009FinalGateTest.php` (committed F-001 skip
  replaced with fresh integrated AC6 test, 41 assertions; AC9 export
  assertions strengthened to active-revision unicode payloads; header +
  `p6009DocxXml()` helper).
- `verification/p6-009-rerun-01-seed.php` (new; rerun fixture seeder).
- `verification/p6-009-rerun-01-auth.setup.js` (new; rerun auth setup).
- `verification/playwright.p6-009-rerun-01.config.js` (new; rerun config).
- `verification/p6-009-rerun-01/final-gate-rerun.spec.js` (new; 12 rerun
  scenarios incl. strengthened RERUN-11 export-content check).
- `verification/p6-009-rerun-01/p6-009-rerun-01-browser-results.json`
  (new; 12/12 results).
- `verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`
  (this artifact).
- `verification/p6-009-rerun-01-fixtures.json` (generated fixture map).
- `database/p6-009-rerun-01.sqlite` (local verification DB; untracked
  environment artifact).
- `tasks/P6-009-phase6-integration-verification.md` (rerun record +
  IN_PROGRESS → REVIEW transition).

No application, migration, route, or existing-test change was made. The
original `verification/p6-009/` set is byte-identical to the failed-run
state.

## 16. Execution verdict

PASS — AC1–AC11 all pass with fresh integrated evidence. F-001 remediation
RESOLVED and proven at the gate level. Phase 6 closure may now be evaluated
by the HPO through the independent-review path; this verdict does not
itself close Phase 6.
`PHASE 6 REMAINS OPEN` (closure requires independent VERIFIED review + HPO
closure of P6-009, then a separate HPO Phase 6 closure decision).
