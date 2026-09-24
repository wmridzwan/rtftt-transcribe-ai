import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-005 browser verification (DC-01): user-visible structural split / merge and
// persisted translation invalidation in the existing transcript workspace. Real
// Chromium against the real Laravel workspace page (dedicated P6-005
// verification DB). Verifies rendered, visible behaviour — including
// validation/rejection, reload durability, stale/conflict behaviour,
// comparison truthfulness, navigation, and the surfaced stale marker — not
// merely backend state.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-005-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-005-intruder-state.json');

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

async function enterStructural(page) {
    await page.click('[data-struct-enter]');
    await expect(page.locator('[data-struct-split-submit]')).toBeVisible();
}

async function splitRow(page, rowIndex, boundary, offset) {
    await page.locator('[data-struct-split]').nth(rowIndex).click();
    await page.locator('[data-struct-boundary-input]').fill(String(boundary));
    await page.locator('[data-struct-offset-input]').fill(String(offset));
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('[data-struct-split-submit]'),
    ]);
}

async function mergeRows(page, indices) {
    for (const index of indices) {
        await page.locator('[data-struct-select]').nth(index).check();
    }
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('[data-struct-merge-submit]'),
    ]);
}

function row(page, index) {
    return page.locator('[data-segment-row]').nth(index);
}

test('P6-005: a valid interior split persists, survives reload, invalidates the translation, and keeps navigation position-based', async ({ page }) => {
    await open(page, fixtures.main.transcriptionId);

    // No structural marker and no stale translation before any edit.
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');
    await expect(page.locator('[data-translation-staleness]')).toHaveCount(0);

    await enterStructural(page);
    await splitRow(page, 0, 2.0, 8);

    await expect(page.locator('[data-struct-notice]')).toBeVisible();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'revision');
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);

    // Resulting text/timing: children inherit the (en) language.
    await expect(row(page, 0)).toContainText('Original');
    await expect(row(page, 1)).toContainText('first');
    await expect(row(page, 0).locator('[data-segment-language]')).toHaveText('en');
    await expect(page.locator('[data-seek-seconds]').nth(0)).toHaveAttribute('data-seek-seconds', '0.5');
    await expect(page.locator('[data-seek-seconds]').nth(1)).toHaveAttribute('data-seek-seconds', '2');

    // Persisted invalidation marker is surfaced.
    await expect(page.locator('[data-translation-staleness]')).toBeVisible();
    await expect(page.locator('[data-translation-stale="zh"]')).toBeVisible();
    await expect(page.locator('[data-translation-stale="zh"]')).toHaveAttribute('data-translation-stale-reason', 'SEGMENT_STRUCTURE_CHANGED');

    // Reload durability.
    await open(page, fixtures.main.transcriptionId);
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);
    await expect(page.locator('[data-translation-stale="zh"]')).toBeVisible();

    // P6-006 navigation remains position-based over the four revision positions.
    await page.locator('[data-transcript-region]').focus();
    await page.keyboard.press('Home');
    await expect(row(page, 0)).toHaveAttribute('data-nav-active', 'true');
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 1 of 4 selected');
    await page.keyboard.press('ArrowDown');
    await expect(row(page, 1)).toHaveAttribute('data-nav-active', 'true');
    await expect(page.locator('[data-transcript-nav-status]')).toHaveText('Segment 2 of 4 selected');
});

test('P6-005: split at the beginning/end is rejected with an accessible error and no write', async ({ page }) => {
    await open(page, fixtures.boundary.transcriptionId);

    // Boundary equal to the segment start is rejected.
    await enterStructural(page);
    await splitRow(page, 0, 0.5, 8);

    await expect(page.locator('[data-struct-error]')).toBeVisible();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');

    // Text offset equal to the text length is rejected.
    await enterStructural(page);
    await splitRow(page, 0, 2.0, 14);
    await expect(page.locator('[data-struct-error]')).toBeVisible();

    await open(page, fixtures.boundary.transcriptionId);
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');
});

test('P6-005: a valid adjacent merge persists a plain-space join and mixed-language und provenance', async ({ page }) => {
    await open(page, fixtures.merge.transcriptionId);

    await enterStructural(page);
    await mergeRows(page, [0, 1]);

    await expect(page.locator('[data-struct-notice]')).toBeVisible();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);
    await expect(page.locator('[data-segment-row]')).toHaveCount(2);
    await expect(row(page, 0)).toContainText('Original first Original kedua');
    await expect(row(page, 0).locator('[data-segment-language]')).toHaveText('und');

    // Reload durability.
    await open(page, fixtures.merge.transcriptionId);
    await expect(page.locator('[data-segment-row]')).toHaveCount(2);
    await expect(row(page, 0)).toContainText('Original first Original kedua');
});

test('P6-005: a non-adjacent merge is rejected with no write', async ({ page }) => {
    await open(page, fixtures.nonadjacent.transcriptionId);

    await enterStructural(page);
    await mergeRows(page, [0, 2]);

    await expect(page.locator('[data-struct-error]')).toBeVisible();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');

    await open(page, fixtures.nonadjacent.transcriptionId);
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');
});

test('P6-005: a stale structural save is rejected with a visible conflict and no silent merge', async ({ page, context }) => {
    await open(page, fixtures.conflict.transcriptionId);
    await enterStructural(page);

    const second = await context.newPage();
    await open(second, fixtures.conflict.transcriptionId);
    await enterStructural(second);
    await splitRow(second, 0, 2.0, 8);
    await expect(second.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);
    await second.close();

    await splitRow(page, 0, 3.0, 8);

    await expect(page.locator('[data-struct-conflict]')).toBeVisible();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);
});

test('P6-005: cancel discards the structural selection and persists nothing', async ({ page }) => {
    await open(page, fixtures.cancel.transcriptionId);

    await enterStructural(page);
    await page.locator('[data-struct-split]').nth(0).click();
    await page.click('[data-struct-cancel]');

    await expect(page.locator('[data-struct-split-submit]')).toBeHidden();
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');

    await open(page, fixtures.cancel.transcriptionId);
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveAttribute('data-struct-revision-state', 'machine');
});

test('P6-005: a non-owner cannot view the transcript workspace', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.main.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});

test('P6-005: the immutable machine source is unchanged and a structurally changed revision is not aligned in the comparison', async ({ page }) => {
    await open(page, fixtures.immutable.transcriptionId);

    await enterStructural(page);
    await splitRow(page, 0, 2.0, 8);
    await expect(page.locator('[data-struct-revision-indicator]')).toHaveText(/Revision v2/);

    // P6-007 comparison: structurally created segments are not silently aligned.
    await page.click('[data-compare-view="compare"]');
    await expect(page.locator('[data-comparison-revision-state="alignment-unavailable"]').first()).toBeVisible();

    // Return to the transcript view (the undo control lives there).
    await page.click('[data-compare-view="normal"]');
    await expect(page.locator('[data-segment-row]').first()).toBeVisible();

    // Undo reaches the durable initial revision, the verbatim machine copy.
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('[data-edit-undo]'),
    ]);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');
    await expect(page.locator('[data-seek-seconds]').nth(0)).toHaveAttribute('data-seek-seconds', '0.5');
    await expect(row(page, 0)).toContainText('Original first');
    await expect(row(page, 0).locator('[data-segment-language]')).toHaveText('en');
});