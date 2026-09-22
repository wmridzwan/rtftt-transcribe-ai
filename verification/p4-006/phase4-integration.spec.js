import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p4-006-fixtures.json'), 'utf8'));
const artifactsDir = path.join(here, '..', 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

const results = {
    startedAt: new Date().toISOString(),
    workspace: {},
    audio: {},
    video: {},
    seek: {},
    active: {},
    gap: {},
    multilingual: {},
    search: {},
    copy: {},
    noSpeech: {},
    coexistence: {},
    consoleErrors: [],
    pageErrors: [],
};

test.describe.configure({ mode: 'default' });

test.afterAll(async () => {
    results.finishedAt = new Date().toISOString();
    fs.writeFileSync(path.join(artifactsDir, 'p4-006-browser-results.json'), JSON.stringify(results, null, 2));
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

async function openWorkspace(page, id, { requirePlayer = true } = {}) {
    await page.goto(`/transcriptions/${id}`);
    if (requirePlayer) {
        await page.waitForSelector('[data-media-player]', { timeout: 30000 });
        await page.waitForFunction(() => {
            const p = document.querySelector('[data-media-player]');
            return p && p.readyState >= 1 && Number.isFinite(p.duration) && p.duration > 0;
        }, null, { timeout: 30000 });
    }
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
    }, time, { timeout: 40000 });
}

async function countLabel(page) {
    return page.locator('input[type="search"] + span').innerText();
}

test('V4-01 completed workspace exposes all Phase 4 controls', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    results.workspace.player = await page.locator('[data-media-player]').count();
    results.workspace.timestamps = await page.locator('[data-seek-seconds]').count();
    results.workspace.rows = await page.locator('[data-segment-row]').count();
    results.workspace.languageLabels = await page.locator('[data-segment-language]').count();
    results.workspace.search = await page.locator('input[type="search"]').count();
    results.workspace.copyTranscript = await page.getByRole('button', { name: 'Copy transcript' }).count();
    results.workspace.exportTxt = await page.locator('a[href$="/export/txt"]').count();

    expect(results.workspace.player).toBe(1);
    expect(results.workspace.timestamps).toBe(8);
    expect(results.workspace.rows).toBe(8);
    expect(results.workspace.languageLabels).toBe(8);
    expect(results.workspace.search).toBe(1);
    expect(results.workspace.copyTranscript).toBe(1);
    expect(results.workspace.exportTxt).toBeGreaterThan(0);
});

test('V4-08 real audio playback via authorized stream', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const player = page.locator('[data-media-player]');
    results.audio.tag = await player.evaluate((el) => el.tagName.toLowerCase());
    results.audio.src = await player.getAttribute('src');
    results.audio.duration = await player.evaluate((el) => el.duration);

    expect(results.audio.tag).toBe('audio');
    expect(results.audio.src).toContain('/stream');
    expect(results.audio.src).not.toContain('storage');
    expect(results.audio.duration).toBeGreaterThan(20);

    await player.evaluate((el) => el.play());
    await page.waitForTimeout(1500);
    results.audio.currentTimeAfterPlay = await player.evaluate((el) => el.currentTime);
    expect(results.audio.currentTimeAfterPlay).toBeGreaterThan(0);
    await player.evaluate((el) => el.pause());
});

test('V4-09 real video playback and interactive video seek/active', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.video.transcriptionId);

    const player = page.locator('[data-media-player]');
    results.video.tag = await player.evaluate((el) => el.tagName.toLowerCase());
    results.video.src = await player.getAttribute('src');
    results.video.duration = await player.evaluate((el) => el.duration);

    expect(results.video.tag).toBe('video');
    expect(results.video.src).toContain('/stream');
    expect(results.video.duration).toBeGreaterThan(20);

    await player.evaluate((el) => el.play());
    await page.waitForTimeout(1500);
    results.video.currentTimeAfterPlay = await player.evaluate((el) => el.currentTime);
    expect(results.video.currentTimeAfterPlay).toBeGreaterThan(0);
    await player.evaluate((el) => el.pause());

    // Interactive video seek/active (addresses P4-003 LOW-1).
    await page.locator('[data-seek-seconds="12.345"]').click();
    await page.waitForTimeout(400);
    results.video.seekObserved = await player.evaluate((el) => el.currentTime);
    results.video.activeAfterSeek = await activeIndex(page);
    expect(Math.abs(results.video.seekObserved - 12.345)).toBeLessThan(0.05);
    expect(results.video.activeAfterSeek).toBe(2);
});

test('V4-10 timestamp click seek targets exact persisted milliseconds', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.locator('[data-seek-seconds="12.345"]').click();
    await page.waitForTimeout(400);
    const observed = await page.evaluate(() => document.querySelector('[data-media-player]').currentTime);
    results.seek = { expected: 12.345, observed, difference: Math.abs(observed - 12.345), active: await activeIndex(page) };

    expect(results.seek.difference).toBeLessThan(0.05);
    expect(results.seek.active).toBe(2);
});

test('V4-11 active segment sync during playback, seek, and manual seek', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    // Timestamp seek.
    await page.locator('[data-seek-seconds="12.345"]').click();
    await page.waitForTimeout(300);
    results.active.afterTimestampSeek = await activeIndex(page);
    expect(results.active.afterTimestampSeek).toBe(2);

    // Manual seek.
    await setTime(page, 17.0);
    results.active.afterManualSeek = await activeIndex(page);
    expect(results.active.afterManualSeek).toBe(3);

    // Playback progression across a boundary.
    await page.locator('[data-seek-seconds="21"]').click();
    await page.evaluate(() => document.querySelector('[data-media-player]').play());
    await waitForTimeAtLeast(page, 22.6);
    results.active.afterProgression = await activeIndex(page);
    await page.evaluate(() => document.querySelector('[data-media-player]').pause());
    expect(results.active.afterProgression).toBe(7);
});

test('V4-12 gap produces no active segment', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await setTime(page, 10.0);
    results.gap.active = await activeIndex(page);
    results.gap.ariaCurrentCount = await page.locator('[data-segment-row][aria-current="true"]').count();
    expect(results.gap.active).toBeNull();
    expect(results.gap.ariaCurrentCount).toBe(0);
});

test('V4-13 multilingual display for ms/en/zh/ta/und', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    results.multilingual.labels = await page.locator('[data-segment-language]').evaluateAll((els) => els.map((el) => el.dataset.segmentLanguage));
    for (const language of ['ms', 'en', 'zh', 'ta', 'und']) {
        expect(results.multilingual.labels).toContain(language);
    }
    for (const text of ['Segmen kedua', '第三段', 'நான்காம்', 'Undetermined text']) {
        await expect(page.getByText(text, { exact: false }).first()).toBeVisible();
    }
});

test('V4-14 Latin search (case-insensitive, count, highlights, navigation)', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.fill('input[type="search"]', fixtures.search.latin.query);
    await page.waitForTimeout(300);
    results.search.latin = { query: fixtures.search.latin.query, expected: fixtures.search.latin.expected };
    results.search.latin.marks = await page.locator('mark').count();
    results.search.latin.countLabel = await countLabel(page);
    expect(results.search.latin.marks).toBe(fixtures.search.latin.expected);
    expect(results.search.latin.countLabel).toBe(`1 of ${fixtures.search.latin.expected}`);

    await page.getByRole('button', { name: 'Next' }).click();
    await page.waitForTimeout(200);
    results.search.latin.countAfterNext = await countLabel(page);
    results.search.latin.currentHighlightBefore = await page.evaluate(() => Array.from(document.querySelectorAll('mark[data-match-index]')).map((m) => m.className.includes('bg-orange-400')));
    results.search.latin.currentHighlightAfter = await page.evaluate(() => Array.from(document.querySelectorAll('mark[data-match-index]')).map((m) => m.className.includes('bg-orange-400')));
    expect(results.search.latin.countAfterNext).toBe(`2 of ${fixtures.search.latin.expected}`);
    // Contract requires navigation to move between matches; the current-match
    // indicator must move from match 0 to match 1 after Next.
    expect(results.search.latin.currentHighlightAfter[1]).toBe(true);
});

test('V4-15 Chinese search', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.fill('input[type="search"]', fixtures.search.chinese.query);
    await page.waitForTimeout(300);
    results.search.chinese = {
        query: fixtures.search.chinese.query,
        marks: await page.locator('mark').count(),
        countLabel: await countLabel(page),
    };
    expect(results.search.chinese.marks).toBe(fixtures.search.chinese.expected);
});

test('V4-16 Tamil search', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.fill('input[type="search"]', fixtures.search.tamil.query);
    await page.waitForTimeout(300);
    results.search.tamil = {
        query: fixtures.search.tamil.query,
        marks: await page.locator('mark').count(),
        countLabel: await countLabel(page),
    };
    expect(results.search.tamil.marks).toBe(fixtures.search.tamil.expected);
});

test('V4-17 full transcript copy matches ordered persisted text', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.getByRole('button', { name: 'Copy transcript' }).click();
    await page.waitForTimeout(400);
    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    const apiPath = await page.evaluate(() => Boolean(navigator.clipboard) && window.isSecureContext);
    const normalized = clipboard.replace(/\r\n/g, '\n');
    results.copy.full = {
        expected: fixtures.copyFullText,
        observed: clipboard,
        clipboardApiPath: apiPath,
        normalizedMatch: normalized === fixtures.copyFullText,
    };
    expect(normalized).toBe(fixtures.copyFullText);
});

test('V4-18 segment copy copies segment text only', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    const expected = fixtures.audio.segments.find((s) => s.index === 2).text;
    await page.locator('[data-segment-row][data-segment-index="2"] button[aria-label="Copy segment"]').click();
    await page.waitForTimeout(600);
    results.copy.segmentDiagnostics = await page.evaluate(() => {
        const textEl = document.querySelector('[data-segment-text][data-segment-index="2"]');
        const button = document.querySelector('[data-segment-row][data-segment-index="2"] button[aria-label="Copy segment"]');
        const root = button ? button.closest('[x-data]') : null;
        return {
            ariaLive: Array.from(document.querySelectorAll('[aria-live="polite"]')).map((el) => el.textContent),
            segmentTextNodes: document.querySelectorAll('[data-segment-text][data-segment-index="2"]').length,
            rawText: textEl ? textEl.dataset.rawText : 'NO_ELEMENT',
            textContent: textEl ? textEl.textContent : 'NO_ELEMENT',
            nearestXData: root ? root.getAttribute('x-data').slice(0, 60) : 'NO_ROOT',
        };
    });
    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    results.copy.segment = { expected, observed: clipboard, match: clipboard === expected };
    expect(clipboard).toBe(expected);
});

test('V4-23 no-speech workspace is valid without errors', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.noSpeech.transcriptionId);

    results.noSpeech.seekControls = await page.locator('[data-seek-seconds]').count();
    results.noSpeech.rows = await page.locator('[data-segment-row]').count();
    results.noSpeech.hasPlayer = await page.locator('[data-media-player]').count();
    results.noSpeech.emptyState = await page.getByText('No transcript segments').count();

    expect(results.noSpeech.seekControls).toBe(0);
    expect(results.noSpeech.rows).toBe(0);
    expect(results.noSpeech.hasPlayer).toBe(1);
    expect(results.noSpeech.emptyState).toBeGreaterThan(0);
});

test('V4-35 cross-feature coexistence on one workspace', async ({ page }) => {
    watch(page);
    await openWorkspace(page, fixtures.audio.transcriptionId);

    await page.fill('input[type="search"]', fixtures.search.latin.query);
    await page.waitForTimeout(300);
    await page.locator('[data-seek-seconds="12.345"]').click();
    await page.waitForTimeout(400);

    results.coexistence.marksWithPlayback = await page.locator('mark').count();
    results.coexistence.activeWithSearch = await activeIndex(page);
    results.coexistence.exportTxtPresent = await page.locator('a[href$="/export/txt"]').count();
    expect(results.coexistence.marksWithPlayback).toBeGreaterThan(0);
    expect(results.coexistence.activeWithSearch).toBe(2);
    expect(results.coexistence.exportTxtPresent).toBeGreaterThan(0);

    // Copy still works while search + playback state coexist.
    await page.getByRole('button', { name: 'Copy transcript' }).click();
    await page.waitForTimeout(400);
    results.coexistence.clipboardMatches = (await page.evaluate(() => navigator.clipboard.readText())).replace(/\r\n/g, '\n') === fixtures.copyFullText;
    expect(results.coexistence.clipboardMatches).toBe(true);

    // Clearing search preserves playback active state.
    await page.getByRole('button', { name: 'Clear' }).click();
    await page.waitForTimeout(300);
    results.coexistence.marksAfterClear = await page.locator('mark').count();
    results.coexistence.activeAfterClear = await activeIndex(page);
    expect(results.coexistence.marksAfterClear).toBe(0);
    expect(results.coexistence.activeAfterClear).toBe(2);
});
