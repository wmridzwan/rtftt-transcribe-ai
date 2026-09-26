# P7-011 Builder Report — Retention, Derived-Artifact + Orphan Cleanup

Task: `tasks/P7-011-retention-cleanup.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-P7-011-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Baseline

- HEAD: `4d90d5b`; branch `main`; heavily dirty tree at start (prior-wave residue preserved; no reset/clean/stash).
- Governance recorded first: execution authorization (`DECISION-P7-011-EXECUTION-AUTHORIZATION-001`), lifecycle transition, CURRENT_STATE.md + plan.md clerical BACKLOG reconciliations.

## Files changed (new unless noted)

- `database/migrations/2026_09_26_140000_add_purged_at_to_media_files_table.php` (new) — nullable tombstone marker; additive, backward-compatible.
- `database/migrations/2026_09_26_140001_create_retention_purge_audits_table.php` (new) — audit ledger (run_id/type/id/path/outcome/reason + index); never purge-eligible.
- `app/Models/RetentionPurgeAudit.php` (new) — outcome constants (purged/absent/failed/skipped).
- `app/Retention/RetentionPurge.php` (new) — the reconciler: 30-day `completed_at` eligibility (null → never), ADR-009 staging via guarded `held_by=cleanup` claims (ingestion retry protocol respected: concurrent holder wins), orphan-24h gate as a pure function, quarantine/backup/history exclusions, verify-before-tombstone, per-object failure accounting, structured logging.
- `app/Retention/StalePurgeCandidate.php` (new) — back-off signal for concurrent user deletion.
- `app/Console/Commands/RetentionPurgeCommand.php` (new) — `retention:purge [--dry-run]`, exit codes honest.
- `app/Models/MediaFile.php` (edit, additive) — `purged_at` fillable + datetime cast.
- `app/Http/Controllers/MediaActionController.php` (edit, narrow) — purged sources abort 410 with retention message on download + stream; nothing else changed.
- `routes/console.php` (edit, additive) — daily `retention:purge` with `withoutOverlapping` (P7-008 conventions).
- `app/Console/Commands/DeploymentVerify.php` (edit, additive) — `checkRetention()` (schedule + ledger-failure signal; P7-008 conventions).
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive) — retention schedule/ledger lines; no P7-005 format altered.
- `deploy/migrations-inventory.json` (edit, append-only) — both migrations hash-pinned + note; no recorded entry changed.
- `docs/RETENTION-POLICY.md` (new) — user-facing disclosure (30-day rule, post-purge behavior, export/delete guidance, 410 notice).
- `docs/DEPLOYMENT-RUNBOOK.md` (edit, append §16 by reference; P7-008 owns file).
- Tests: `tests/Feature/Retention/` 4 files, 23 tests — eligibility matrix (5), adversarial protection incl. quarantine/dry-run/held-staging/expired-claim/orphan-age (7), idempotency incl. absent/FS-failure/resume/concurrent-deletion/run-scope (5), audit/disclosure/410/history/export/generation/verify (6).
- `tests/Feature/Editing/RevisionMigrationRollbackTest.php` (edit) — `--step` 7→9 (two newest migrations are P7-011's; both `down()`s sqlite-clean) + note; P6/P7-006/P7-002 assertions unchanged.

No P7-009, drill, P7-012, object-storage, or unrelated TD content.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 eligibility matrix | PASS | 31d purged, 29d skipped, boundary inclusive, null-completed_at + non-completed excluded |
| AC2 adversarial protection | PASS | Active (queued/transcribing/draft, 90d old) untouched, zero audits; quarantine skipped with bytes intact; dry-run deletes nothing |
| AC3 P6 invariants post-purge | PASS | Transcription + audit rows intact; graph reconciler unaffected; full Phase 6 suites green |
| AC4 idempotency + resume | PASS | 2nd run skips tombstoned (no double purge/count); absent recorded + tombstoned; dir-as-file failure → failed without tombstone, exit≠0, follow-up run completes as absent |
| AC5 audit completeness | PASS | Every decision outcome in per-run ledger; outcome sums reconcile |
| AC6 ADR-005 interaction | PASS | Concurrent user deletion backs off cleanly; user path untouched |
| AC7 disclosure | PASS | `docs/RETENTION-POLICY.md` (30-day, never-auto-delete, 410); abort message names retention |
| AC8 Option D outcome | PASS | Expired unheld claims purged (file+row) via guarded claims; fresh/held skipped; orphan 24h gate unit-pinned |
| AC9 generation compatibility | PASS | Real isolated backup run before/after purge: manifest byte-identical; target untouched |
| AC10 standard gate | PASS | Full suite 1073/1072+1 pre-existing skip (×2 runs); Pint clean; PHPStan 0 |
| AC11 no G-09 claim | PASS | No gate verdict in diff/docs |

Disclosure/purged-state extras: download + stream 410 (status + message asserted); media history row + txt export still served post-purge.

## Test / verification matrix

- New P7-011 suites: 23/23.
- Full suite ×2: 1073 tests, 1072 passed, 1 pre-existing skip (FFprobe-unavailable guard), 0 failures, 4045 assertions; 4 pre-existing warnings. TD-008: zero flakes observed — stays OPEN/MEDIUM/pre-P7-012 regardless.
- Pint clean; PHPStan level 7: 0 errors (three genuine findings fixed properly: HasFactory dropped where no factory exists, FilesystemAdapter typing, re-verification probe extraction).
- Builder-corrected defects (in-scope, reported not hidden): (1) `$absent` computed post-delete misclassified successes — fixed by capturing presence before acting; (2) nested-factory ownership in tests (403s) — fixed by shared-ownership seeding; (3) `Artisan::output()` buffering — switched to the established `expectsOutputToContain` pattern.
- Live evidence: `deployment:verify` (Migrations current 33; Retention scheduled, ledger clean), `observability:diagnostics` (retention lines), `retention:purge --dry-run` (2 skipped, nothing deleted) — retained in `verification/p7-011-*.txt`. Dev DB migrated forward-only (gitignored) to produce honest evidence; pg parity migration exercised as sqlite no-op by design.

## TD mapping

- TD-007: implementation delivered; stays OPEN (closure needs independent review + P7-012 G-09). Nothing marked closed.
- TD-008: no flakes in 2 runs; unchanged. TD-011/002/003/004: untouched.
- Nothing marked closed.

## Known limitations (for reviewer)

1. Kill-mid-run rehearsal is simulated via deterministic FS-failure + follow-up resume (no SIGKILL of a live run); resume semantics are identical (idempotent re-run completes).
2. Tombstone column is `purged_at` on media_files only (bytes owner); no transcription-level marker — history renders from retained rows.
3. Staging purge reuses the P2-004A2 claim vocabulary (`held_by=cleanup`); the claim rows it deletes are expired + unheld only.

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed. TD-007 NOT closed.

---

## Corrective Cycle 1 (2026-09-26; CHANGES_REQUESTED → corrected)

Independent review (`reviews/P7-011-INDEPENDENT-REVIEW.md` —
preserved unchanged) returned two MEDIUM
findings. Both corrected narrowly; no redesign, no scope change.

### Finding 1 — purged-state disclosure (root cause + fix)

Root cause: `MediaFile::hasPhysicalFile()` tested only path
existence, ignoring `purged_at` — so a tombstoned row whose bytes were
gone rendered no player but ALSO no "source purged" notice anywhere,
while the docs promised a marked state; worse, a tombstoned row with a
present object would render a player against endpoints that always
410 for purged sources.

Changes:
- `app/Models/MediaFile.php` — `hasPhysicalFile()` now returns false
  for tombstoned rows (documented; all five consumers want
  "bytes available?" semantics: probe, job, workspace flag,
  controller stream URL, media views).
- `app/Http/Controllers/TranscriptionController.php` — passes
  `$mediaPurged` (sole renderer verified; no other `show` renderer).
- `resources/views/transcriptions/show.blade.php` — explicit
  `data-purged-source` notice (permanent-removal wording matching
  `docs/RETENTION-POLICY.md`, "not a temporary media failure");
  player branch untouched; transcript/history/exports untouched.
- Tests: `tests/Feature/Retention/RetentionPurgedDisclosureTest.php`
  (3) — notice present + permanent wording + no `<audio>`/`<video>`
  element for purged; transcript surface + TXT export still served;
  available media still renders a player with no notice. (Lesson
  recorded: `data-media-player` also appears in page JS, so element
  tags are asserted, not the hook string.)

### Finding 2 — staging claim discarded on failed delete (root cause + fix)

Root cause: two compounding defects — (a) `$claim->delete()` ran
unconditionally after the delete attempt, and (b) the first fix
attempt (`forceFill`+`save` on the in-memory model) was a silent
no-op because the query-builder claim UPDATE bypassed the stale
model (identical values → nothing dirty → nothing persisted),
proven by the failing retry test.

Changes (`app/Retention/RetentionPurge.php`):
- `deleteStagingObject()` now RETURNS the recorded outcome.
- Claim row deleted ONLY on purged/absent (resolved disposition).
- On failure the claim is released via query-builder UPDATE to
  `held_by=upload` + `cleanup_claimed_at=null`, so the next run
  deterministically re-claims through the intact P2-004A2 guarded
  UPDATE (no competing protocol; race protections unchanged).
- Tests: `tests/Feature/Retention/RetentionStagingRetryTest.php` —
  the exact 6-step sequence (expired object → forced failure → file
  remains + failure reported + claim retained/released → fixed →
  retry completes as absent + row removed, no false success).

### Corrective verification (fresh, not reused)

- Retention suites: 27/27 (23 original + 4 new).
- Related: MediaStreaming + Deployment + Backup + Security: 130/130.
- Full suite ×2: 1077 tests, 1076 passed, 1 pre-existing skip,
  0 failures, 4066 assertions; 4 pre-existing warnings. TD-008: zero
  flakes observed — stays OPEN/MEDIUM/pre-P7-012 regardless.
- Pint clean; PHPStan level 7: 0 errors.
- Live: `deployment:verify`, `retention:purge --dry-run`,
  `storage:validate-topology` outputs re-retained
  (`verification/p7-011-*`).

### Corrective state

`P7-011 = REVIEW — corrective cycle 1 complete; independent re-review required.` No VERIFIED/DONE claimed. TD-007 NOT closed.
