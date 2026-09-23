import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-006 browser verification (DC-01): advanced navigation + language filter in
// the existing transcript workspace. Real Chromium against the real Laravel
// workspace page (dedicated P6-006 verification DB). This verifies rendered,
// visible behaviour (computed outline, visibility, live-region text), not merely
// the `hidden` attribute.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-006-fixtures.json'), 'utf8'));

async function open(page, id, { requirePlayer = true } = {}) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-transcript-region]');
    // Wait until the transcriptSearch component has hydrated (filter label set).
    await page.waitForFunction(() => {
        const el = document.querySelector('[data-transcript-filter-label]');
        return el && /\d+ segment/.test(el.textContent || '');
    }, null, { timeout: 15000 });
    if (requirePlayer) {
        await page.waitForSelector('[data-media-player]', { timeout: 30000 });
    }
}

async function navIndex(page) {
    return page.evaluate(() => {
        const row = document.querySelector('[data-segment-row][data-nav-active="true"]');
        return row ? Number(row.dataset.segmentIndex) : null;
    });
}

async function ariaCurrentIndex(page) {
    return page.evaluate(() => {
        const row = document.querySelector('[data-segment-row][aria-current="true"]');
        return row ? Number(row.dataset.segmentIndex) : null;
    });
}

function filterLabel(page) {
    return page.locator('[data-transcript-filter-label]');
}

test('P6-006: keyboard navigation moves the nav selection and visibly outlines the row', async ({ page }) => {
    await open(page, fixtures.filter.transcriptionId);
    const region = page.locator('[data-transcript-region]');
    await region.focus();

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(0);
    expect(await ariaCurrentIndex(page)).toBe(0);
    await expect(page.locator('[data-segment-row][data-nav-active="true"]')).toHaveCSS('outline-style', 'solid');
    await expect(page.locator('[data-segment-row][data-nav-active="true"]')).toHaveCSS('outline-width', '2px');

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(1);

    await page.keyboard.press('ArrowUp');
    expect(await navIndex(page)).toBe(0);

    await page.keyboard.press('End');
    expect(await navIndex(page)).toBe(7);

    await page.keyboard.press('Home');
    expect(await navIndex(page)).toBe(0);

    await page.keyboard.press('j');
    expect(await navIndex(page)).toBe(1);
    await page.keyboard.press('k');
    expect(await navIndex(page)).toBe(0);
});

test('P6-006: overlapping and zero-length timings never trap or skip navigation', async ({ page }) => {
    await open(page, fixtures.overlap.transcriptionId);
    const region = page.locator('[data-transcript-region]');
    await region.focus();

    await page.keyboard.press('Home');
    expect(await navIndex(page)).toBe(0);

    // Segment 1 starts inside segment 0, so playback resolves to segment 0;
    // navigation must still follow the stable segment identity to segment 1.
    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(1);
    expect(await ariaCurrentIndex(page)).toBe(0);

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(2);

    await page.keyboard.press('ArrowUp');
    expect(await navIndex(page)).toBe(1);

    await page.keyboard.press('ArrowUp');
    expect(await navIndex(page)).toBe(0);

    // The zero-length final segment is still a valid, reachable stop.
    await page.keyboard.press('End');
    expect(await navIndex(page)).toBe(4);

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(0);
});

test('P6-006: without playable media navigation still shows a distinguishable selection', async ({ page }) => {
    await open(page, fixtures.noMedia.transcriptionId, { requirePlayer: false });

    await expect(page.locator('[data-media-player]')).toHaveCount(0);
    const region = page.locator('[data-transcript-region]');
    await region.focus();

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(0);
    await expect(page.locator('[data-segment-row][data-nav-active="true"]')).toHaveCSS('outline-style', 'solid');
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 1 of 3 selected');

    await page.keyboard.press('ArrowDown');
    expect(await navIndex(page)).toBe(1);
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 2 of 3 selected');

    await page.keyboard.press('End');
    expect(await navIndex(page)).toBe(2);
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 3 of 3 selected');

    // Media playback is not faked: no playback-owned active row exists.
    expect(await ariaCurrentIndex(page)).toBeNull();
});

test('P6-006: language filter count reflects the visible set immediately', async ({ page }) => {
    await open(page, fixtures.filter.transcriptionId);

    await expect(filterLabel(page)).toHaveAttribute('aria-live', 'polite');
    await expect(filterLabel(page)).toHaveText('8 segments');
    await expect(page.locator('[data-segment-row]:not([hidden])')).toHaveCount(8);

    await page.selectOption('[data-transcript-language-filter]', 'zh');
    await expect(filterLabel(page)).toHaveText('2 segments');
    await expect(page.locator('[data-segment-row]:not([hidden])')).toHaveCount(2);
    await expect(page.locator('[data-segment-row][data-segment-index="0"]')).toBeHidden();
    await expect(page.locator('[data-segment-row][data-segment-index="2"]')).toBeVisible();

    await page.selectOption('[data-transcript-language-filter]', 'ta');
    await expect(filterLabel(page)).toHaveText('1 segment');
    await expect(page.locator('[data-segment-row]:not([hidden])')).toHaveCount(1);
    await expect(page.locator('[data-segment-row][data-segment-index="3"]')).toBeVisible();

    await page.selectOption('[data-transcript-language-filter]', '');
    await expect(filterLabel(page)).toHaveText('8 segments');
    await expect(page.locator('[data-segment-row]:not([hidden])')).toHaveCount(8);

    // Repeated switching stays in sync (regression for the one-step-stale label).
    for (const [language, expected] of [['zh', '2 segments'], ['ta', '1 segment'], ['', '8 segments'], ['zh', '2 segments'], ['', '8 segments']]) {
        await page.selectOption('[data-transcript-language-filter]', language);
        await expect(filterLabel(page)).toHaveText(expected);
    }
});

test('P6-006: filtering re-syncs the search match set and counts', async ({ page }) => {
    await open(page, fixtures.filter.transcriptionId);

    await page.fill('input[type="search"]', 'segmen');
    await expect(page.locator('mark')).toHaveCount(4);

    await page.selectOption('[data-transcript-language-filter]', 'zh');
    await expect(page.locator('mark')).toHaveCount(0);

    await page.selectOption('[data-transcript-language-filter]', '');
    await expect(page.locator('mark')).toHaveCount(4);
});

test('P6-006: modifier combinations and typing do not trigger navigation', async ({ page }) => {
    await open(page, fixtures.filter.transcriptionId);
    const region = page.locator('[data-transcript-region]');
    await region.focus();

    for (const combo of ['Control+ArrowDown', 'Alt+ArrowDown', 'Meta+ArrowDown', 'Shift+ArrowDown', 'Shift+J']) {
        await page.keyboard.press(combo);
    }
    expect(await navIndex(page)).toBeNull();

    await page.keyboard.press('j');
    expect(await navIndex(page)).toBe(0);

    await page.keyboard.press('Control+j');
    await page.keyboard.press('Alt+j');
    expect(await navIndex(page)).toBe(0);

    await page.fill('input[type="search"]', '');
    await page.locator('input[type="search"]').focus();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('j');
    expect(await navIndex(page)).toBe(0);
});

test('P6-006: navigation instructions are associated with the transcript region', async ({ page }) => {
    await open(page, fixtures.filter.transcriptionId);

    const region = page.locator('[data-transcript-region]');
    await expect(region).toHaveAttribute('aria-describedby', 'transcript-nav-hint');
    await expect(region).toHaveAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown Home End');
    await expect(page.locator('#transcript-nav-hint')).toHaveText(/Arrow Up\/Down/);
    await expect(page.locator('[data-transcript-nav-status]')).toHaveAttribute('role', 'status');
    await expect(page.locator('[data-transcript-copy-scope="full"]')).toHaveAttribute('title', /full transcript/);
});
