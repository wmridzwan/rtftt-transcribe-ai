# P5-006 — Browser Verification Evidence (ADR-021)

Task: P5-006 — Translation Workspace UI
Date: 2026-09-21
Tooling: Playwright (Chromium, headless), `verification/playwright.p5-006.config.js`
Run: 18 tests, 18 passed, ~1.1 min, 0 retries. Command:
`npx playwright test -c verification/playwright.p5-006.config.js`

## What is real and what is a test double

| Layer | Real or double |
|---|---|
| Browser, page rendering, Alpine, clipboard, downloads | real (Chromium) |
| Laravel app (PHP built-in server), auth, sessions, CSRF, routes, controllers | real |
| Queue: `database` driver, `php artisan queue:work --queue=translation` running the real `ProcessTranslation` job | real |
| `HttpTranslationProvider`, response validator, writer, attempt fencing | real |
| Translation **model** (the worker's NLLB inference) | **test double** (`verification/p5-006-fake-translation-worker.mjs`) |

The double speaks the real `/translate` contract, so the provider boundary is exercised for
real, but **no real translation model ran and this does not satisfy the real provider/model
gate (P5-008, D5-09).** Database: dedicated SQLite `database/p5-006-verification.sqlite`,
freshly migrated and seeded per run (`verification/p5-006-seed.php`). No production data.

## Checks (required item → result)

| # | Required check | Result | Evidence |
|---|---|---|---|
| 1 | Start translation from a completed transcript | PASS | tests 1–2: transcript-page Translate link opens the workspace; target selector offers `ms, en, zh, ta`; Start → notice + `queued` |
| 2 | Queued / translating state | PASS | tests 2–3, 10: queued panel (`role=status`); a slow request is observed live as `queued → translating → completed` with no manual reload (status polling) |
| 3 | Successful completion | PASS | test 4: the real queue worker completes the job; the open page updates itself; zh text `大家早上好 / 欢迎参加每周会议 / 感谢您的参与` |
| 4 | Source ↔ translation toggle | PASS | test 5: toggle shows the original sentences and back; `transcription_segments` rows identical before/after |
| 5 | Copy translated text | PASS | test 6: full translation and single segment read back from the real clipboard (LF↔CRLF normalized: Windows clipboard) |
| 6 | All translated exports | PASS | test 7: TXT, SRT, VTT, DOCX downloaded via the UI; names `p5-006-main-transcript-zh.<ext>`; SRT has inherited `00:00:00,500 --> 00:00:05,000`; DOCX is a valid zip |
| 7 | Retryable failure → Retry available | PASS | test 11: seeded `PROVIDER_TIMEOUT` shows Retry; clicking it requeues, the real worker completes it, exactly one row |
| 8 | Non-retryable failure → Retry unavailable | PASS | test 12 (seeded `CONFIGURATION_ERROR`): no Retry button/form, safe message, `Reference: CONFIGURATION_ERROR`, no token/URL/`Bearer`/stack text; a hand-crafted retry POST is refused and the row is unchanged. Test 13: the same outcome produced live through the real job (worker 401) |
| 9 | Refresh/reload preserves state | PASS | tests 3, 8: queued and completed states survive reload; default tab is the completed translation |
| 10 | Double-submit / concurrent requests converge | PASS | test 14: three concurrent browser POSTs → three 200s, one row; a real double-click → one row. *Caveat:* the PHP built-in server on Windows is single-threaded, so this shows browser-level convergence, not parallel server execution. Genuine parallelism is proven by the two-process Pest test `TranslationRequestConcurrencyTest` |
| 11 | Cross-user access denied | PASS | test 17 (see below) |
| 12 | Invalid / unsupported target rejected safely | PASS | test 15: tampered `fr`, `und`, and `?target=fr` are rejected with a friendly message; 0 rows created |
| — | Ta / ms Unicode round trip | PASS | test 9 |
| — | Transcript still processing | PASS | test 16: unavailable state, no Start action |
| — | No workspace page/console errors | PASS | test 18 |

## Cross-user isolation (test 17)

A second browser context signed in as `p5-006-other@example.test` (asserted by page content:
"P5-006 Other", not "P5-006 Owner") received **403** for: the owner's workspace page, the
owner's status endpoint, TXT export, DOCX export, and forged start and retry POSTs (with a
valid CSRF token). No translation row was created. The other user's own completed translation
rendered normally.

**Harness defect found and fixed during this run:** Playwright applies the runner's
`storageState` (the owner's login) to every context created inside a test, so the first version
of this test was silently signed in as the owner and hung on `/login`. The context now starts
from an explicitly empty state, and the test asserts whose session it is. Without that
assertion the check would have proved nothing.

## Findings recorded from the run

- **Pre-existing, outside P5-006:** the transcript page (`/transcriptions/{id}`) raises
  `ReferenceError: showRenameModal is not defined` in the browser: the rename `flux:modal` sits
  outside the `x-data` scope that defines `showRenameModal`. Present at HEAD
  (`resources/views/transcriptions/show.blade.php`); not touched by P5-006. Test 18 attributes
  errors by page URL and records this one separately.
- Windows clipboard newline normalization (above) is an environment property, not an app defect.

## Residual flake / limits

No retries were used and no flake was observed across the runs made while developing the
harness (the failures seen were harness defects, fixed above). The run is Windows + Chromium
only. Mobile/other browsers are not covered.
