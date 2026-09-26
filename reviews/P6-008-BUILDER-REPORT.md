# P6-008 Builder Report — Revision History / Audit Surface

Builder: OpenCode. Authority: HPO Builder execution authorization under
`DECISION-P6-008-READY-001`. READY contract:
`tasks/P6-008-revision-history-audit-surface.md`. This report is a Builder
artifact only; it is not an independent review and does not mark anything
VERIFIED or DONE.

## 1. Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d`
- Branch: `main`
- Initial working tree: pre-existing uncommitted residue from Step A/B/C/C2,
  P6-005 closure, and P6-008 governance work (modified `AGENTS.md`,
  `BLOCKERS.md`, `CURRENT_STATE.md`, `DECISIONS.md`, `DECISION_QUEUE.md`,
  `plan.md`, `tasks/P6-005-split-merge-translation-invalidation.md`;
  untracked `docs/`, `reviews/P6-005-INDEPENDENT-REVIEW.md`,
  `tasks/P6-008-revision-history-audit-surface.md`,
  `verification/LARGE-V3-FULL-CHAIN-E2E.md`,
  `verification/REAL-MODEL-FULL-CHAIN-E2E.md`). None of it was reset,
  cleaned, overwritten, or normalized; it is not Builder-owned.

## 2. Lifecycle

`READY` → `IN_PROGRESS` (recorded in the task file on Builder start;
`IN_BUILD` per the authorization is the same build state under
repository-native orchestration terminology) → `IMPLEMENTED_PENDING_REVIEW`
(this report; awaiting independent review, which moves it to `REVIEW`).

## 3. Implementation summary

- `RevisionService::activateHistorical()` (new): `update`-authorized,
  CAS-fenced explicit activation of any eligible persisted same-transcription
  revision. Unknown/cross-transcription targets → `InvalidArgumentException`;
  stale expected base → `RevisionConflictException`; activating the
  already-active revision is a no-op success (`false`, no write). Delegates
  the pointer move to the verified `RevisionRepository::activate()`
  primitive; no ancestry restriction added (HPO-008-A); no new revision is
  created, appended, or rewritten.
- `TranscriptRevisionController::activate()` (new) + route `POST
  /transcriptions/{transcription}/revisions/activate`
  (`transcriptions.revisions.activate`): thin boundary mirroring the existing
  undo/redo pattern (`update` gate, `target` + `expected_base` validation,
  `history_conflict` / `history_error` / `history_notice` flashes).
- `TranscriptionController::show()`: passes `view`-fenced
  `RevisionService::history()` (version order, read-only), persisted author
  names (`historyAuthors()`, users table read, id fallback), and persisted
  staleness-cause links (`historyStalenessCauses()` from
  `stale_caused_by_revision_id`; no inference).
- `transcriptions/partials/revision-history.blade.php` (new, `data-history-*`
  hooks): version-ordered list, Active badge, per-row Activate form (editors
  only), version/author/created/parent-linkage/segment-count metadata,
  machine-source authoritative state, persisted staleness-cause marker,
  history flash blocks. Included in the transcript workspace after the
  revision toolbar.

## 4. Files changed (Builder-owned only)

- `app/Editing/RevisionService.php` (+56)
- `app/Http/Controllers/TranscriptRevisionController.php` (+57)
- `app/Http/Controllers/TranscriptionController.php` (+65, Pint-normalized imports)
- `app/Http/Controllers` route: `routes/web.php` (+2)
- `resources/views/transcriptions/show.blade.php` (+1 include)
- `resources/views/transcriptions/partials/revision-history.blade.php` (new)
- `tests/Feature/Editing/RevisionHistoryActivationTest.php` (new, 12 tests)
- `verification/p6-008-seed.php`, `verification/p6-008-fixtures.json`,
  `verification/playwright.p6-008.config.js`,
  `verification/p6-008-auth.setup.js`,
  `verification/p6-008/revision-history.spec.js` (9 browser tests),
  `verification/p6-008/README.md`,
  `verification/p6-008/P6-008-BROWSER-VERIFICATION-EVIDENCE.md`,
  `verification/p6-008/p6-008-browser-results.json`
- `tasks/P6-008-revision-history-audit-surface.md` (lifecycle status only)

No migration, no config change, no Phase 5 write, no P6-001..P6-007 semantic
change, no P6-009 file touched.

## 5. Acceptance criteria mapping

| AC | Result | Evidence |
|---|---|---|
| AC1 history list in version order + active identification | PASS | PHP test (order assertion, Active badge); browser tests 1–2 |
| AC2 persisted metadata only | PASS | version/author/created/parent/count from persisted rows; browser test 1; no field lacks a source |
| AC3 arbitrary eligible activation, `update` + CAS; stale base = conflict, no merge, no rewrite | PASS | PHP tests (linear, sibling-branch, stale-base); browser tests 4–7 |
| AC4 cross-transcription/invalid/non-owner rejection | PASS | PHP tests (unknown, cross-transcription, 403s); browser test 9 |
| AC5 `view`-fenced read; viewing never mutates | PASS | service + HTTP 403 tests; no-mutation test (pointer, count, timestamp); admin view test |
| AC6 undo/redo unchanged | PASS | `tests/Feature/Editing` + `tests/Unit/Editing`: 207/207 green; full suite green |
| AC7 machine-source distinguishable | PASS | PHP machine-source test; browser test 3 |
| AC8 staleness matches persisted P6-005 markers | PASS | PHP stale-cause test; browser test 8 (history marker agrees with toolbar marker) |
| AC9 suite/Pint/PHPStan/browser evidence | PASS | full suite 882/881 + 1 pre-existing skip; Pint clean; PHPStan 0; 9/9 browser |
| AC10 no unauthorized change (diff audit) | PASS | §4 file list; `git diff --stat` shows only intended files |

## 6. Tests executed (fresh)

- `tests/Feature/Editing/RevisionHistoryActivationTest.php`: 12/12, 66 assertions.
- `tests/Feature/Editing` + `tests/Unit/Editing`: 207/207, 867 assertions.
- Full `php artisan test --compact`: 882 total, 881 passed, 1 skipped
  (pre-existing 2FA skip), 2 warnings (pre-existing baseline), 3340 assertions.
- `vendor/bin/pint --dirty --format agent`: clean (two files normalized once, then clean).
- `composer types:check` (PHPStan level 7): 0 errors.
- Playwright DC-01: 9/9 (10.1s); evidence JSON + MD retained under
  `verification/p6-008/`.

## 7. Browser evidence

See `verification/p6-008/P6-008-BROWSER-VERIFICATION-EVIDENCE.md`: real
Chromium, real workspace, dedicated DB; covers list rendering, active
identification, machine-source state, product-path activation, reload
durability, sibling-branch activation, tampered-base conflict with no pointer
move, staleness-cause agreement, non-owner 403.

## 8. CAS / authorization evidence

- CAS: stale-base HTTP test asserts `history_conflict` + unchanged pointer
  and row count; service defaults expected to the persisted pointer and the
  repository re-checks under `lockForUpdate`; genuine two-process race
  coverage remains in the untouched P6-002 suite.
- Authorization: HTTP 403 for non-owner POST and GET; service-level
  `AuthorizationException` for `activateHistorical` and `history`; admin
  allow-path tested; ownership fencing inherited from `TranscriptionPolicy`.

## 9. Deviations

NONE. One judgment call recorded (not a deviation): activating the
already-active revision returns a no-op success with an explicit notice
rather than an error, since the pointer is already in the requested state
and the contract specifies success semantics for eligible targets.

## 10. Known limitations

- Author display resolves `created_by` against the users table (persisted
  read); a missing user row renders as `user #<id>`.
- The machine source (`null` active) is displayable but not activatable: it
  is not a revision row and the repository primitive has no null target.
  Returning to the machine source remains what the frozen domain allows
  (unchanged by P6-008).

## 11. Builder status

`IMPLEMENTED_PENDING_REVIEW`. Not VERIFIED, not DONE. P6-009 untouched.

## 12. Exact next legal action

**Commission an independent review of P6-008.**
