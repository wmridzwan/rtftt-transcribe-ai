import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-004 browser verification (DC-01): user-visible timing editing + validation
// in the existing transcript workspace. Real Chromium against the real Laravel
// workspace page (dedicated P6-004 verification DB). Verifies rendered, visible
// behaviour (including playback/active-segment resolution and P6-006 navigation),
// not merely backend state.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-004-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-004-intruder-state.json');

async function open(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-transcript-region]');
    await waitForHydration(page);
}

async function waitForHydration(page) {
    await page.waitForFunction(() => {
        const el = document.querySelector('[data-transcript-filter-label]');
        return el && /\d+ segment/.test(el.textContent || '');
    }, null, { timeout: 15000 });
}

async function enterTiming(page) {
    await page.click('[data-timing-enter]');
    await expect(page.locator('[data-timing-start-input]').first()).toBeVisible();
}

async function fillTiming(page, position, start, end) {
    await page.locator('[data-timing-start-input]').nth(position).fill(String(start));
    await page.locator('[data-timing-end-input]').nth(position).fill(String(end));
}

async function saveTiming(page) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('[data-timing-save]'),
    ]);
}

async function submitForm(page, selector) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click(selector),
    ]);
}

function row(page, index) {
    return page.locator('[data-segment-row]').nth(index);
}

async function seekTo(page, time) {
    await page.waitForFunction(() => {
        const player = document.querySelector('[data-media-player]');
        return player !== null && player.readyState >= 1;
    }, null, { timeout: 15000 });
    await page.evaluate((seconds) => {
        const player = document.querySelector('[data-media-player]');
        if (player) {
            player.currentTime = seconds;
            player.dispatchEvent(new Event('seeked'));
        }
    }, time);
}

test('P6-004: a valid timing edit persists, survives reload, and drives playback and active-segment resolution', async ({ page }) => {
    await open(page, fixtures.main.transcriptionId);

    // Machine timing: at t=5.0 only segment 2 (4.0-8.0) is active.
    await seekTo(page, 5.0);
    await expect(row(page, 0)).not.toHaveAttribute('aria-current', 'true');
    await expect(row(page, 1)).toHaveAttribute('aria-current', 'true');

    await enterTiming(page);
    await fillTiming(page, 0, 5.0, 9.0);
    await fillTiming(page, 1, 4.0, 8.0);
    await fillTiming(page, 2, 8.0, 12.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-notice]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'revision');
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveText('Revision v2');

    // Reload durability.
    await open(page, fixtures.main.transcriptionId);
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'revision');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', '5');

    // Playback seek controls use active-revision timing.
    await expect(page.locator('[data-seek-seconds]').first()).toHaveAttribute('data-seek-seconds', '5');
    await page.locator('[data-seek-seconds]').first().click();
    await expect(row(page, 0)).toHaveAttribute('aria-current', 'true');

    // Active-segment resolution uses active-revision timing: at t=5.0 the
    // lowest-position overlapping segment (position 0) now wins, whereas the
    // machine source resolved position 1.
    await seekTo(page, 5.0);
    await expect(row(page, 0)).toHaveAttribute('aria-current', 'true');
});

test('P6-004: a negative timestamp is rejected with an accessible error and no write', async ({ page }) => {
    await open(page, fixtures.negative.transcriptionId);

    await enterTiming(page);
    await fillTiming(page, 0, -1.0, 4.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-error]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'machine');

    await open(page, fixtures.negative.transcriptionId);
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'machine');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', '0.5');
});

test('P6-004: start greater than end is rejected with no write', async ({ page }) => {
    await open(page, fixtures.inverted.transcriptionId);

    await enterTiming(page);
    await fillTiming(page, 0, 8.0, 2.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-error]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'machine');
});

test('P6-004: a zero-length segment is accepted but never active', async ({ page }) => {
    await open(page, fixtures.zero.transcriptionId);

    await enterTiming(page);
    await fillTiming(page, 0, 5.0, 5.0);
    await fillTiming(page, 1, 5.0, 9.0);
    await fillTiming(page, 2, 8.0, 12.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-notice]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'revision');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', '5');
    await expect(page.locator('[data-timing-current-end]').first()).toHaveAttribute('data-timing-current-end', '5');

    // At t=5.0 the zero-length segment is never active; segment position 1 wins.
    await seekTo(page, 5.0);
    await expect(row(page, 0)).not.toHaveAttribute('aria-current', 'true');
    await expect(row(page, 1)).toHaveAttribute('aria-current', 'true');
});

test('P6-004: overlapping segments are accepted', async ({ page }) => {
    await open(page, fixtures.overlap.transcriptionId);

    await enterTiming(page);
    await fillTiming(page, 0, 2.0, 6.0);
    await fillTiming(page, 1, 2.0, 6.0);
    await fillTiming(page, 2, 8.0, 12.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-notice]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'revision');
    await expect(page.locator('[data-timing-current-start]').nth(1)).toHaveAttribute('data-timing-current-start', '2');
});

test('P6-004: a stale timing save is rejected with a visible conflict and no silent merge', async ({ page, context }) => {
    await open(page, fixtures.conflict.transcriptionId);

    await page.click('[data-timing-enter]');
    await page.locator('[data-timing-start-input]').first().fill('1');

    const second = await context.newPage();
    await open(second, fixtures.conflict.transcriptionId);
    await second.click('[data-timing-enter]');
    await second.locator('[data-timing-start-input]').first().fill('2');
    await saveTiming(second);
    await expect(second.locator('[data-timing-revision-indicator]')).toHaveText('Revision v2');
    await second.close();

    await saveTiming(page);

    await expect(page.locator('[data-timing-conflict]')).toBeVisible();
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveText('Revision v2');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', '2');
});

test('P6-004: cancel discards local timing edits and persists nothing', async ({ page }) => {
    await open(page, fixtures.cancel.transcriptionId);
    const original = await page.locator('[data-timing-current-start]').first().getAttribute('data-timing-current-start');

    await enterTiming(page);
    await page.locator('[data-timing-start-input]').first().fill('9');
    await page.click('[data-timing-cancel]');

    await expect(page.locator('[data-timing-start-input]').first()).toBeHidden();
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', original);
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'machine');

    await open(page, fixtures.cancel.transcriptionId);
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveAttribute('data-timing-revision-state', 'machine');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', original);
});

test('P6-004: the immutable machine-source timing is unchanged after a timing edit', async ({ page }) => {
    await open(page, fixtures.immutable.transcriptionId);
    const original = await page.locator('[data-timing-current-start]').first().getAttribute('data-timing-current-start');

    await enterTiming(page);
    await fillTiming(page, 0, 10.0, 11.0);
    await saveTiming(page);
    await expect(page.locator('[data-timing-revision-indicator]')).toHaveText('Revision v2');

    // Undo reaches the durable initial revision, which is the verbatim machine
    // copy: the original machine timing is still intact.
    await submitForm(page, '[data-edit-undo]');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');
    await expect(page.locator('[data-timing-current-start]').first()).toHaveAttribute('data-timing-current-start', original);
});

test('P6-004: a non-owner cannot view or edit the transcript timing', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.main.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});

test('P6-004: P6-006 navigation remains ordered by revision position after timing edits', async ({ page }) => {
    await open(page, fixtures.nav.transcriptionId);

    // Make position 0 hold a later time than position 1 (out-of-time-order).
    await enterTiming(page);
    await fillTiming(page, 0, 10.0, 12.0);
    await fillTiming(page, 1, 0.0, 3.0);
    await fillTiming(page, 2, 8.0, 12.0);
    await saveTiming(page);

    await expect(page.locator('[data-timing-revision-indicator]')).toHaveText('Revision v2');
    await expect(page.locator('[data-nav-seconds]').first()).toHaveAttribute('data-nav-seconds', '10');
    await waitForHydration(page);

    // Out-of-time-order: position 0 (time 10-12) is first by position but last
    // by time. Navigation must follow position order regardless of timestamps.
    await page.locator('[data-transcript-region]').focus();
    await page.keyboard.press('Home');
    await expect(row(page, 0)).toHaveAttribute('data-nav-active', 'true');
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 1 of 3 selected');
    await page.keyboard.press('ArrowDown');
    await expect(row(page, 1)).toHaveAttribute('data-nav-active', 'true');
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 2 of 3 selected');
});