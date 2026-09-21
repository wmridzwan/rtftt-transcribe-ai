import { test, expect, chromium } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dbPath, laravelEnv, phpBin, root } from '../p5-006-env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const fx = JSON.parse(fs.readFileSync(path.join(here, '..', 'p5-006-fixtures.json'), 'utf8'));
const artifactsDir = path.join(here, '..', 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

const results = { startedAt: new Date().toISOString(), checks: {}, consoleErrors: [], pageErrors: [] };
const note = (key, value) => { results.checks[key] = value; };

test.describe.configure({ mode: 'serial' });

test.afterAll(async () => {
    results.finishedAt = new Date().toISOString();
    fs.writeFileSync(path.join(artifactsDir, 'p5-006-browser-results.json'), JSON.stringify(results, null, 2));
});

function watch(page) {
    page.on('console', (msg) => { if (msg.type() === 'error') { results.consoleErrors.push({ url: page.url(), text: msg.text() }); } });
    page.on('pageerror', (error) => { results.pageErrors.push({ url: page.url(), error: String(error) }); });
}

const artisanArgs = ['artisan', 'queue:work', 'database', '--queue=translation', '--stop-when-empty', '--tries=1', '--timeout=120'];

function runQueueWorker() {
    return execFileSync(phpBin, artisanArgs, { cwd: root, env: laravelEnv, timeout: 120000 }).toString();
}

function runQueueWorkerAsync() {
    return spawn(phpBin, artisanArgs, { cwd: root, env: laravelEnv, stdio: 'ignore' });
}

function sql(query) {
    // Read-only verification query against the dedicated verification database.
    const code = "$pdo = new PDO('sqlite:' . $argv[1]); echo json_encode($pdo->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));";
    const out = execFileSync(phpBin, ['-r', code, dbPath, query], { cwd: root }).toString();
    return JSON.parse(out);
}

const workspace = (id, target) => `/transcriptions/${id}/translations${target ? `?target=${target}` : ''}`;
const state = (page) => page.locator('[data-translation-workspace]');

async function csrfToken(page, transcriptionId) {
    // The "not started" workspace for an untouched target always renders a start form.
    await page.goto(workspace(transcriptionId, 'ta'));
    return page.locator('[data-translation-start-form] input[name="_token"]').inputValue();
}

test.describe('P5-006 translation workspace — owner flows', () => {
    test('1. entry point: the transcript page links to the translation workspace', async ({ page }) => {
        watch(page);
        await page.goto(`/transcriptions/${fx.main}`);
        const link = page.locator('[data-translate-link]');
        await expect(link).toBeVisible();
        await link.click();
        await page.waitForURL(`**/transcriptions/${fx.main}/translations`);
        await expect(state(page)).toHaveAttribute('data-translation-state', 'none');
        note('entryPoint', 'transcript page Translate link opens the workspace');
    });

    test('2. start a translation from a completed transcript -> queued', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main));

        await expect(page.locator('[data-target-select]')).toBeVisible();
        const options = await page.locator('[data-target-select] option').evaluateAll((els) => els.map((e) => e.value));
        expect(options).toEqual(['ms', 'en', 'zh', 'ta']);

        await page.locator('[data-target-select]').selectOption('zh');
        await page.locator('[data-translation-start]').click();

        await page.waitForURL(`**/transcriptions/${fx.main}/translations?target=zh`);
        await expect(page.locator('[data-translation-notice]')).toContainText('Translation started');
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');
        await expect(page.locator('[data-translation-progress]')).toHaveAttribute('role', 'status');
        await expect(page.locator('[data-target-tab="zh"]')).toHaveAttribute('data-target-state', 'Queued');
        note('start', { target: 'zh', state: 'queued', options });
    });

    test('3. reload while queued preserves the queued state (no duplicate row)', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main, 'zh'));
        await page.reload();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');
        const rows = sql(`select count(*) as n from translations where transcription_id = ${fx.main} and target_language = 'zh'`);
        expect(Number(rows[0].n)).toBe(1);
        note('reloadWhileQueued', { rows: Number(rows[0].n) });
    });

    test('4. completion through the real queue worker; page shows Unicode translation', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main, 'zh'));
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');

        // Run the real job (queue:work) against the deterministic worker double; the
        // open page polls the status endpoint and reloads itself when the state changes.
        runQueueWorker();

        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed', { timeout: 20000 });
        const texts = await page.locator('[data-translated-text]').allTextContents();
        expect(texts.map((t) => t.trim())).toEqual(['大家早上好', '欢迎参加每周会议', '感谢您的参与']);
        await expect(page.locator('[data-target-tab="zh"]')).toHaveAttribute('data-target-state', 'Completed');
        note('completion', { viaPolling: true, texts });
    });

    test('5. source <-> translation toggle does not mutate the source', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main, 'zh'));

        const before = sql(`select segment_index, start_seconds, end_seconds, text from transcription_segments where transcription_id = ${fx.main} order by segment_index`);

        const translated = page.locator('[data-translated-text]');
        const source = page.locator('[data-source-text]');
        await expect(translated.first()).toBeVisible();
        await expect(source.first()).toBeHidden();

        await page.locator('[data-view-toggle="source"]').click();
        await expect(source.first()).toBeVisible();
        await expect(translated.first()).toBeHidden();
        expect((await source.allTextContents()).map((t) => t.trim())).toEqual(fx.standardSentences);
        await expect(page.locator('[data-view-toggle="source"]')).toHaveAttribute('aria-pressed', 'true');

        await page.locator('[data-view-toggle="translation"]').click();
        await expect(translated.first()).toBeVisible();
        await expect(source.first()).toBeHidden();

        const after = sql(`select segment_index, start_seconds, end_seconds, text from transcription_segments where transcription_id = ${fx.main} order by segment_index`);
        expect(after).toEqual(before);
        note('toggle', { sourceTexts: fx.standardSentences, sourceUnchanged: true });
    });

    test('6. copy: full translation and a single segment (Unicode-safe)', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main, 'zh'));

        await page.locator('[data-copy-full]').click();
        await expect(page.locator('[data-copy-status]')).toContainText('Translation copied');
        // The Windows system clipboard normalizes LF to CRLF; compare text, not OS newlines.
        const full = (await page.evaluate(() => navigator.clipboard.readText())).split('\r\n').join('\n');
        expect(full).toBe('大家早上好\n欢迎参加每周会议\n感谢您的参与');

        await page.locator('[data-copy-segment="1"]').click();
        await expect(page.locator('[data-copy-status]')).toContainText('Segment 2 copied');
        const segment = await page.evaluate(() => navigator.clipboard.readText());
        expect(segment).toBe('欢迎参加每周会议');
        note('copy', { full, segment });
    });

    test('7. all four translated exports download with distinguishable names', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main, 'zh'));
        const downloads = {};

        for (const format of ['txt', 'srt', 'vtt', 'docx']) {
            const [download] = await Promise.all([
                page.waitForEvent('download'),
                page.locator(`[data-export="${format}"]`).click(),
            ]);
            const target = path.join(artifactsDir, `p5-006-${format}-${download.suggestedFilename()}`);
            await download.saveAs(target);
            downloads[format] = { filename: download.suggestedFilename(), bytes: fs.statSync(target).size };
            expect(download.suggestedFilename()).toBe(`p5-006-main-transcript-zh.${format}`);
        }

        const txt = fs.readFileSync(path.join(artifactsDir, 'p5-006-txt-p5-006-main-transcript-zh.txt'), 'utf8');
        expect(txt).toContain('大家早上好');
        const srt = fs.readFileSync(path.join(artifactsDir, 'p5-006-srt-p5-006-main-transcript-zh.srt'), 'utf8');
        expect(srt).toContain('00:00:00,500 --> 00:00:05,000');
        expect(srt).toContain('欢迎参加每周会议');
        const vtt = fs.readFileSync(path.join(artifactsDir, 'p5-006-vtt-p5-006-main-transcript-zh.vtt'), 'utf8');
        expect(vtt.startsWith('WEBVTT')).toBe(true);
        const docx = fs.readFileSync(path.join(artifactsDir, 'p5-006-docx-p5-006-main-transcript-zh.docx'));
        expect(docx.subarray(0, 2).toString()).toBe('PK');
        note('exports', downloads);
    });

    test('8. reload preserves the completed translation; multi-target tabs', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.main));
        // No ?target: defaults to the completed translation.
        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed');
        await page.reload();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed');
        await expect(page.locator('[data-translated-text]').first()).toHaveText('大家早上好');
        await expect(page.locator('[data-target-tab="ms"]')).toHaveAttribute('data-target-state', 'Not started');
        note('reloadCompleted', true);
    });

    test('9. Tamil and Malay round-trip verbatim through the queue', async ({ page }) => {
        watch(page);
        for (const [target, first] of [['ta', 'அனைவருக்கும் காலை வணக்கம்'], ['ms', 'Selamat pagi semua orang']]) {
            await page.goto(workspace(fx.main, target));
            await page.locator('[data-translation-start]').click();
            await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');
            runQueueWorker();
            await expect(state(page)).toHaveAttribute('data-translation-state', 'completed', { timeout: 20000 });
            await expect(page.locator('[data-translated-text]').first()).toHaveText(first);
        }
        note('unicode', 'ta and ms verbatim');
    });
});

test.describe('P5-006 translation workspace — progress, failure, retry', () => {
    test('10. queued -> translating -> completed is observed live without a manual reload', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.slow));
        await page.locator('[data-target-select]').selectOption('ms');
        await page.locator('[data-translation-start]').click();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');

        const worker = runQueueWorkerAsync();
        const seen = ['queued'];

        // The page polls and reloads itself; wait for the intermediate state.
        await expect(state(page)).toHaveAttribute('data-translation-state', 'translating', { timeout: 20000 });
        await expect(page.locator('[data-translation-progress]')).toContainText('Translating');
        seen.push('translating');

        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed', { timeout: 40000 });
        seen.push('completed');
        worker.kill();
        note('liveProgress', seen);
    });

    test('11. retryable failure shows Retry; retry re-runs to completion', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.retryable.transcription, 'ms'));

        await expect(state(page)).toHaveAttribute('data-translation-state', 'failed-retryable');
        const failure = page.locator('[data-translation-failure]');
        await expect(failure).toHaveAttribute('data-failure-code', 'PROVIDER_TIMEOUT');
        await expect(failure).toHaveAttribute('data-retryable', 'true');
        await expect(page.locator('[data-failure-message]')).toHaveText('The translation took too long and was stopped.');
        await expect(page.locator('[data-translation-retry]')).toBeVisible();

        await page.locator('[data-translation-retry]').click();
        await expect(page.locator('[data-translation-notice]')).toContainText('retry started');
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');
        await expect(page.locator('[data-translation-retry]')).toHaveCount(0);

        runQueueWorker();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed', { timeout: 20000 });
        const rows = sql(`select count(*) as n from translations where transcription_id = ${fx.retryable.transcription}`);
        expect(Number(rows[0].n)).toBe(1);
        note('retryable', { code: 'PROVIDER_TIMEOUT', retryShown: true, completedAfterRetry: true, rows: Number(rows[0].n) });
    });

    test('12. non-retryable failure has NO Retry action and a safe message', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.final.transcription, 'ms'));

        await expect(state(page)).toHaveAttribute('data-translation-state', 'failed-final');
        const failure = page.locator('[data-translation-failure]');
        await expect(failure).toHaveAttribute('data-failure-code', 'CONFIGURATION_ERROR');
        await expect(failure).toHaveAttribute('data-retryable', 'false');
        await expect(page.locator('[data-translation-retry]')).toHaveCount(0);
        await expect(page.locator('[data-translation-retry-form]')).toHaveCount(0);
        await expect(page.locator('[data-translation-not-retryable]')).toBeVisible();
        await expect(page.locator('[data-failure-message]')).toContainText('service configuration problem');

        const body = await page.locator('body').innerText();
        for (const secret of ['verification-token', '127.0.0.1:8124', 'Bearer', 'Exception', 'Stack trace']) {
            expect(body).not.toContain(secret);
        }
        expect(body).toContain('Reference: CONFIGURATION_ERROR');

        // Even a hand-crafted retry POST is refused and changes nothing.
        const token = await csrfToken(page, fx.final.transcription);
        const response = await page.request.post(`/translations/${fx.final.translation}/retry`, { form: { _token: token } });
        expect(response.ok()).toBe(true);
        const rows = sql(`select status, failure_code from translations where id = ${fx.final.translation}`);
        expect(rows[0]).toEqual({ status: 'failed', failure_code: 'CONFIGURATION_ERROR' });
        await page.goto(workspace(fx.final.transcription, 'ms'));
        await expect(state(page)).toHaveAttribute('data-translation-state', 'failed-final');
        note('nonRetryable', { code: 'CONFIGURATION_ERROR', retryButtons: 0, forgedPostChangedState: false });
    });

    test('13. live non-retryable failure (worker 401) through the real job shows no Retry', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.liveFinal));
        await page.locator('[data-target-select]').selectOption('ms');
        await page.locator('[data-translation-start]').click();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');

        runQueueWorker();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'failed-final', { timeout: 20000 });
        await expect(page.locator('[data-translation-failure]')).toHaveAttribute('data-failure-code', 'CONFIGURATION_ERROR');
        await expect(page.locator('[data-translation-retry]')).toHaveCount(0);
        const body = await page.locator('body').innerText();
        expect(body).not.toContain('verification-token');
        expect(body).not.toContain('Unauthorized');
        note('liveNonRetryable', { code: 'CONFIGURATION_ERROR', viaRealJob: true });
    });
});

test.describe('P5-006 translation workspace — safety', () => {
    test('14. double submit / concurrent requests converge without a raw error', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.double));
        const token = await page.locator('[data-translation-start-form] input[name="_token"]').inputValue();

        // (a) Two truly concurrent POSTs from the browser.
        const statuses = await page.evaluate(async ({ url, token }) => {
            const post = () => fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'text/html' },
                body: new URLSearchParams({ _token: token, target_language: 'ms' }),
            }).then((r) => ({ status: r.status, url: r.url }));
            return Promise.all([post(), post(), post()]);
        }, { url: `/transcriptions/${fx.double}/translations`, token });

        for (const s of statuses) {
            expect(s.status).toBe(200);
        }

        // (b) A real double-click on the Start button (UI guards the second submit).
        await page.goto(workspace(fx.double, 'en'));
        await page.locator('[data-translation-start]').dblclick();
        await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');

        const ms = sql(`select count(*) as n from translations where transcription_id = ${fx.double} and target_language = 'ms'`);
        const en = sql(`select count(*) as n from translations where transcription_id = ${fx.double} and target_language = 'en'`);
        expect(Number(ms[0].n)).toBe(1);
        expect(Number(en[0].n)).toBe(1);
        note('doubleSubmit', { concurrentStatuses: statuses.map((s) => s.status), rowsMs: Number(ms[0].n), rowsEnDblClick: Number(en[0].n) });
    });

    test('15. invalid / unsupported targets are rejected safely', async ({ page }) => {
        watch(page);

        // (a) Tampered <option> submitted through the real form.
        await page.goto(workspace(fx.invalid));
        await page.locator('[data-target-select]').evaluate((select) => {
            const option = document.createElement('option');
            option.value = 'fr';
            option.textContent = 'French';
            select.appendChild(option);
            select.value = 'fr';
        });
        await page.locator('[data-translation-start]').click();
        await expect(page.locator('[data-translation-field-error]')).toContainText('That target language is not supported');

        // (b) `und` is a valid source marker but never a valid target.
        await page.locator('[data-target-select]').evaluate((select) => {
            const option = document.createElement('option');
            option.value = 'und';
            option.textContent = 'Undetermined';
            select.appendChild(option);
            select.value = 'und';
        });
        await page.locator('[data-translation-start]').click();
        await expect(page.locator('[data-translation-field-error]')).toContainText('That target language is not supported');

        // (c) Unsupported target in the URL.
        await page.goto(workspace(fx.invalid, 'fr'));
        await page.waitForURL(`**/transcriptions/${fx.invalid}/translations`);
        await expect(page.locator('[data-translation-error]')).toContainText('That target language is not supported');

        const rows = sql(`select count(*) as n from translations where transcription_id = ${fx.invalid}`);
        expect(Number(rows[0].n)).toBe(0);
        note('invalidTarget', { attempted: ['fr', 'und', 'url fr'], rowsCreated: Number(rows[0].n) });
    });

    test('16. a transcript that is still processing offers no translation', async ({ page }) => {
        watch(page);
        await page.goto(workspace(fx.processing));
        await expect(page.locator('[data-translation-unavailable]')).toBeVisible();
        await expect(page.locator('[data-translation-start]')).toHaveCount(0);
        note('notCompleted', 'unavailable state, no start action');
    });
});

test.describe('P5-006 translation workspace — cross-user isolation', () => {
    test('17. another user is denied every read and mutation; own workspace works', async () => {
        const browser = await chromium.launch({ args: ['--no-sandbox'] });
        // Playwright applies the runner's storageState (the OWNER's login) to every context
        // created in a test, so the second user must start from an explicitly empty state.
        const context = await browser.newContext({
            baseURL: 'http://127.0.0.1:8123',
            acceptDownloads: true,
            storageState: { cookies: [], origins: [] },
        });
        const page = await context.newPage();
        watch(page);

        await page.goto('/login');
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await page.fill('input[name="email"]', fx.otherEmail);
        await page.fill('input[name="password"]', fx.password);
        await page.click('[data-test="login-button"]');
        await page.waitForURL('**/dashboard', { timeout: 30000 });

        // Prove the session really belongs to the OTHER user before asserting denials.
        await expect(page.locator('body')).toContainText('P5-006 Other');
        await expect(page.locator('body')).not.toContainText('P5-006 Owner');

        // The other user cannot open the OWNER's workspace, status, exports, or mutate it.
        const denied = {};
        const ownerWorkspace = await page.goto(workspace(fx.main, 'zh'));
        denied.workspace = ownerWorkspace.status();
        expect(ownerWorkspace.status()).toBe(403);

        const ownerTranslation = sql(`select id from translations where transcription_id = ${fx.main} and target_language = 'zh'`)[0].id;
        denied.status = (await page.request.get(`/translations/${ownerTranslation}/status`)).status();
        denied.exportTxt = (await page.request.get(`/translations/${ownerTranslation}/export/txt`)).status();
        denied.exportDocx = (await page.request.get(`/translations/${ownerTranslation}/export/docx`)).status();

        // The other user's own workspace page provides a valid CSRF token for their session.
        await page.goto(workspace(fx.foreign.transcription, 'en'));
        await expect(state(page)).toHaveAttribute('data-translation-state', 'none');
        const token = await page.locator('[data-translation-start-form] input[name="_token"]').inputValue();

        denied.start = (await page.request.post(`/transcriptions/${fx.main}/translations`, { form: { _token: token, target_language: 'en' }, maxRedirects: 0 })).status();
        denied.retry = (await page.request.post(`/translations/${fx.retryable.translation}/retry`, { form: { _token: token }, maxRedirects: 0 })).status();

        for (const [name, status] of Object.entries(denied)) {
            expect(status, `${name} must be forbidden`).toBe(403);
        }

        // No state was created or changed by the denied attempts.
        const created = sql(`select count(*) as n from translations where transcription_id = ${fx.main} and target_language = 'en'`);
        expect(Number(created[0].n)).toBe(0);

        // The other user's own translation is visible to them.
        await page.goto(workspace(fx.foreign.transcription, 'ms'));
        await expect(state(page)).toHaveAttribute('data-translation-state', 'completed');
        await expect(page.locator('[data-translated-text]').first()).toHaveText('Terjemahan 0');

        note('crossUser', denied);
        await browser.close();
    });

    test('18. no console or page errors were raised by any translation workspace page', async () => {
        const inWorkspace = (entry) => entry.url.includes('/translations');
        const workspacePageErrors = results.pageErrors.filter(inWorkspace);
        // The browser logs the deliberate 403 of the cross-user test (test 17); that is the
        // expected denial, not an application error.
        const workspaceConsoleErrors = results.consoleErrors.filter((e) => inWorkspace(e) && !/status of 403/.test(e.text));

        // Errors raised by pages OUTSIDE the P5-006 surface (for example the pre-existing
        // transcript page rename modal) are recorded, not attributed to this task.
        note('errors', {
            workspacePageErrors: workspacePageErrors.length,
            workspaceConsoleErrors: workspaceConsoleErrors.length,
            outsideWorkspace: [...results.pageErrors, ...results.consoleErrors].filter((e) => !inWorkspace(e)),
        });

        expect(workspacePageErrors).toEqual([]);
        expect(workspaceConsoleErrors).toEqual([]);
    });
});
