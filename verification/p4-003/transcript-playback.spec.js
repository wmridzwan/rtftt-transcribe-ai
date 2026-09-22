import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p4-003-fixtures.json'), 'utf8'));
const artifactsDir = path.join(here, '..', 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

const results = {
    startedAt: new Date().toISOString(),
    audio: {},
    video: {},
    parity: [],
    boundaries: [],
    manualSeek: {},
    autoScroll: {},
    keyboard: {},
    searchCoexistence: {},
    noSpeech: {},
    consoleErrors: [],
    pageErrors: [],
};

test.describe.configure({ mode: 'serial' });

test.afterAll(async () => {
    results.finishedAt = new Date().toISOString();
    fs.writeFileSync(
        path.join(artifactsDir, 'p4-003-browser-results.json'),
        JSON.stringify(results, null, 2),
    );
});

function watch(page) {
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            results.consoleErrors.push(msg.text());
        }
    });
    page.on('pageerror', (error) => {
        results.pageErrors.push(String(error));
    });
}

async function login(page) {
    // Authentication is provided by the shared storageState created in
    // p4-003-auth.setup.js (globalSetup); no per-test login to avoid throttling.
    void page;
}

async function openWorkspace(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-media-player]', { timeout: 30000 });
    await page.waitForFunction(() => {
        const p = document.querySelector('[data-media-player]');
        return p && p.readyState >= 1 && Number.isFinite(p.duration) && p.duration > 0;
    }, null, { timeout: 30000 });
}

async function activeIndex(page) {
    return page.evaluate(() => {
        const row = document.querySelector('[data-segment-row][aria-current="true"]');
        return row ? Number(row.dataset.segmentIndex) : null;
    });
}

async function setTime(page, time) {
    await page.evaluate((t) => {
        const p = document.querySelector('[data-media-player]');
        p.pause();
        p.currentTime = t;
    }, time);
    await page.waitForTimeout(200);
}

async function waitForTimeAtLeast(page, time) {
    await page.waitForFunction((t) => {
        const p = document.querySelector('[data-media-player]');
        return p && p.currentTime >= t;
    }, time, { timeout: 30000 });
}

test('audio: element, authorized stream src, metadata, playback', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const player = page.locator('[data-media-player]');
    await expect(player).toHaveCount(1);
    results.audio.tag = await player.evaluate((el) => el.tagName.toLowerCase());
    results.audio.src = await player.getAttribute('src');
    results.audio.duration = await player.evaluate((el) => el.duration);

    expect(results.audio.tag).toBe('audio');
    expect(results.audio.src).toContain('/stream');
    expect(results.audio.src).not.toContain('storage');
    expect(results.audio.duration).toBeGreaterThan(20);

    await player.evaluate((el) => el.play());
    await page.waitForTimeout(1200);
    results.audio.currentTimeAfterPlay = await player.evaluate((el) => el.currentTime);
    expect(results.audio.currentTimeAfterPlay).toBeGreaterThan(0);
    await player.evaluate((el) => el.pause());

    await page.screenshot({ path: path.join(artifactsDir, 'p4-003-audio-workspace.png') });
});

test('video: element, authorized stream src, metadata, playback', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.video.transcriptionId);

    const player = page.locator('[data-media-player]');
    await expect(player).toHaveCount(1);
    results.video.tag = await player.evaluate((el) => el.tagName.toLowerCase());
    results.video.src = await player.getAttribute('src');
    results.video.duration = await player.evaluate((el) => el.duration);

    expect(results.video.tag).toBe('video');
    expect(results.video.src).toContain('/stream');
    expect(results.video.duration).toBeGreaterThan(20);

    await player.evaluate((el) => el.play());
    await page.waitForTimeout(1200);
    results.video.currentTimeAfterPlay = await player.evaluate((el) => el.currentTime);
    expect(results.video.currentTimeAfterPlay).toBeGreaterThan(0);
    await player.evaluate((el) => el.pause());

    await page.screenshot({ path: path.join(artifactsDir, 'p4-003-video-workspace.png') });
});

test('audio: timestamp button seek targets exact persisted milliseconds', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const button = page.locator('[data-seek-seconds="12.345"]');
    await expect(button).toHaveCount(1);
    await button.click();
    await page.waitForTimeout(400);

    const observed = await page.evaluate(() => document.querySelector('[data-media-player]').currentTime);
    results.audio.expectedSeek = 12.345;
    results.audio.observedSeek = observed;
    results.audio.seekDifference = Math.abs(observed - 12.345);
    results.audio.activeAfterSeek = await activeIndex(page);

    expect(results.audio.seekDifference).toBeLessThan(0.05);
    expect(results.audio.activeAfterSeek).toBe(2);
});

test('resolver parity: window.p4ResolveActive matches canonical expected values', async ({ page }) => {
    watch(page);
    await login(page);
    await page.goto(`/transcriptions/${fixtures.audio.transcriptionId}`);
    await page.waitForFunction(() => typeof window.p4ResolveActive === 'function', null, { timeout: 15000 });

    const segments = fixtures.audio.segments.map((s) => ({ index: s.index, start: s.start, end: s.end }));

    for (const sample of fixtures.samples) {
        const observed = await page.evaluate(
            ({ segs, t }) => window.p4ResolveActive(segs, t),
            { segs: segments, t: sample.time },
        );
        results.parity.push({ time: sample.time, expected: sample.expected, observed });
        expect(observed, `parity at t=${sample.time}`).toBe(sample.expected);
    }
});

test('active segment boundaries and gaps in the real DOM', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    for (const sample of fixtures.samples) {
        await setTime(page, sample.time);
        const observed = await activeIndex(page);
        results.boundaries.push({ time: sample.time, expected: sample.expected, observed });
        expect(observed, `active at t=${sample.time}`).toBe(sample.expected);
    }
});

test('manual native seek updates the active segment', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await setTime(page, 17.0);
    results.manualSeek.at17 = await activeIndex(page);
    expect(results.manualSeek.at17).toBe(3);

    await setTime(page, 13.0);
    results.manualSeek.at13 = await activeIndex(page);
    expect(results.manualSeek.at13).toBe(2);
});

test('auto-scroll: scroll into view, no jump when visible, suspend, resume', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const region = page.locator('[data-transcript-region]');
    results.autoScroll.scrollHeight = await region.evaluate((el) => el.scrollHeight);
    results.autoScroll.clientHeight = await region.evaluate((el) => el.clientHeight);
    expect(results.autoScroll.scrollHeight).toBeGreaterThan(results.autoScroll.clientHeight);

    // Far active segment scrolls into view.
    await page.locator('[data-seek-seconds="22.5"]').click();
    await page.waitForTimeout(300);
    results.autoScroll.topAfterFarActive = await region.evaluate((el) => el.scrollTop);
    expect(results.autoScroll.topAfterFarActive).toBeGreaterThan(0);

    // Already-visible active segment does not jump.
    const before = await region.evaluate((el) => el.scrollTop);
    await page.locator('[data-seek-seconds="21"]').click();
    await page.waitForTimeout(250);
    const after = await region.evaluate((el) => el.scrollTop);
    results.autoScroll.alreadyVisibleDelta = Math.abs(after - before);
    expect(results.autoScroll.alreadyVisibleDelta).toBeLessThan(2);

    // Suspend on manual transcript scroll, then let playback cross a boundary.
    await page.locator('[data-seek-seconds="21"]').click();
    await page.evaluate(() => document.querySelector('[data-media-player]').play());
    await page.waitForTimeout(200);
    await region.evaluate((el) => el.dispatchEvent(new WheelEvent('wheel', { deltaY: 300, bubbles: true })));
    await region.evaluate((el) => { el.scrollTop = 0; });
    await waitForTimeAtLeast(page, 22.6);
    results.autoScroll.suspendedTop = await region.evaluate((el) => el.scrollTop);
    results.autoScroll.suspendedActive = await activeIndex(page);
    await page.evaluate(() => document.querySelector('[data-media-player]').pause());
    expect(results.autoScroll.suspendedActive).toBe(7);
    expect(results.autoScroll.suspendedTop).toBeLessThan(2);

    // Resume on explicit seek interaction.
    await page.locator('[data-seek-seconds="22.5"]').click();
    await page.waitForTimeout(300);
    results.autoScroll.resumedTop = await region.evaluate((el) => el.scrollTop);
    expect(results.autoScroll.resumedTop).toBeGreaterThan(0);
});

test('keyboard: timestamp control focusable and activates on Enter', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const button = page.locator('[data-seek-seconds="12.345"]');
    await button.focus();
    results.keyboard.focused = await page.evaluate(() => document.activeElement?.dataset?.seekSeconds ?? null);
    expect(results.keyboard.focused).toBe('12.345');

    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);
    results.keyboard.currentTimeAfterEnter = await page.evaluate(() => document.querySelector('[data-media-player]').currentTime);
    results.keyboard.activeAfterEnter = await activeIndex(page);
    expect(Math.abs(results.keyboard.currentTimeAfterEnter - 12.345)).toBeLessThan(0.05);
    expect(results.keyboard.activeAfterEnter).toBe(2);
});

test('search highlight coexists with playback active state', async ({ page }) => {
    watch(page);
    await login(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.fill('input[type="search"]', 'Segmen');
    await page.waitForTimeout(300);
    results.searchCoexistence.marksWithSearch = await page.locator('mark').count();
    expect(results.searchCoexistence.marksWithSearch).toBeGreaterThan(0);

    await page.locator('[data-seek-seconds="12.345"]').click();
    await page.waitForTimeout(400);
    results.searchCoexistence.activeWithSearch = await activeIndex(page);
    expect(results.searchCoexistence.activeWithSearch).toBe(2);

    await page.screenshot({ path: path.join(artifactsDir, 'p4-003-search-coexistence.png') });

    await page.getByRole('button', { name: 'Clear' }).click();
    await page.waitForTimeout(300);
    results.searchCoexistence.marksAfterClear = await page.locator('mark').count();
    results.searchCoexistence.activeAfterClear = await activeIndex(page);
    expect(results.searchCoexistence.marksAfterClear).toBe(0);
    expect(results.searchCoexistence.activeAfterClear).toBe(2);
});

test('no-speech workspace renders without errors and without timestamp controls', async ({ page }) => {
    watch(page);
    await login(page);
    await page.goto(`/transcriptions/${fixtures.noSpeech.transcriptionId}`);
    await page.waitForTimeout(700);

    results.noSpeech.seekControls = await page.locator('[data-seek-seconds]').count();
    results.noSpeech.rows = await page.locator('[data-segment-row]').count();
    results.noSpeech.hasPlayer = await page.locator('[data-media-player]').count();
    results.noSpeech.emptyState = await page.getByText('No transcript segments').count();

    expect(results.noSpeech.seekControls).toBe(0);
    expect(results.noSpeech.rows).toBe(0);
    expect(results.noSpeech.hasPlayer).toBe(1);
    expect(results.noSpeech.emptyState).toBeGreaterThan(0);

    await page.screenshot({ path: path.join(artifactsDir, 'p4-003-no-speech.png') });
});
