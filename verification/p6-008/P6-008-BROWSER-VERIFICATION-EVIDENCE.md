# P6-008 Browser Verification Evidence (DC-01)

Real Chromium against the real Laravel transcript workspace, dedicated
`database/p6-008-verification.sqlite` database (seeded by
`verification/p6-008-seed.php`; owner `p6-008@example.test`, non-owner
`p6-008-intruder@example.test`). Server: `php -S 127.0.0.1:8128 -t public
verification/p4-003-server-router.php`. Suite:
`node_modules/.bin/playwright.cmd test -c
verification/playwright.p6-008.config.js`.

## Result

9/9 passed (10.1s, Chromium, single worker, no retries). Raw results:
`verification/p6-008/p6-008-browser-results.json`.

| # | Browser check | Result |
|---|---|---|
| 1 | History list renders every durable revision in version order with persisted metadata (versions, parent linkage, author, segment count, created time) | PASS |
| 2 | Active revision unambiguously identified (workspace indicator + row badge; no badge on other rows) | PASS |
| 3 | Machine source authoritative before any revision exists (note visible, no entries) | PASS |
| 4 | Historical revision selected and activated through the product path (notice, marker moves, workspace reflects v1 text) | PASS |
| 5 | Activation survives reload (durable active pointer) | PASS |
| 6 | Arbitrary sibling-branch revision activatable beyond undo reach (v2 while v3 active; workspace reflects v2 text) | PASS |
| 7 | Stale activation base fails closed as a visible conflict with no pointer move | PASS |
| 8 | Persisted staleness-cause markers match the P6-005 record exactly (`data-history-stale-cause="ms"` agrees with `data-translation-stale="ms"`) | PASS |
| 9 | Non-owner cannot view the history surface (403, nothing leaked) | PASS |

## Notes

- Fixtures are deterministic and local: `plain` (ids 1), `linear` (2),
  `branched` (3), `stale` (4); see `verification/p6-008-fixtures.json`.
- The conflict path (7) submits the real activation form with a tampered
  `expected_base` through the normal product route; the server rejects the
  stale base and the pointer does not move.
- No browser action created, appended, or rewrote a revision; activation is a
  pointer move only (row counts asserted by
  `tests/Feature/Editing/RevisionHistoryActivationTest.php`).
- An initial 7/9 run failed two tests due to shared-fixture pointer state
  between sequential tests (the product correctly hides the Activate control
  for the already-active revision); the specs were made order-agnostic and
  the clean re-run passed 9/9. No application change resulted.
