import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-003 browser verification (DC-01): user-visible text editing + undo/redo in
// the existing transcript workspace. Real Chromium against the real Laravel
// workspace page (dedicated P6-003 verification DB). Verifies rendered,
// visible behaviour and real navigation, not merely backend state.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-003-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-003-intruder-state.json');

async function open(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-transcript-region]');
    await page.waitForFunction(() => {
        const el = document.querySelector('[data-transcript-filter-label]');
        return el && /\d+ segment/.test(el.textContent || '');
    }, null, { timeout: 15000 });
}

async function save(page) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click('[data-edit-save]'),
    ]);
}

async function submitForm(page, selector) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.click(selector),
    ]);
}

function segmentTexts(page) {
    return page.locator('[data-segment-text]');
}

test('P6-003: entering edit mode is explicit, focuses the first field, and persists nothing', async ({ page }) => {
    await open(page, fixtures.cancel.transcriptionId);

    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');
    await expect(page.locator('[data-revision-indicator]')).toHaveAttribute('data-revision-state', 'machine');
    await expect(page.locator('[data-edit-enter]')).toBeVisible();

    await page.click('[data-edit-enter]');

    const firstField = page.locator('[data-edit-text]').first();
    await expect(firstField).toBeVisible();
    await expect(firstField).toBeFocused();
    await expect(page.locator('[data-edit-save]')).toBeVisible();
    await expect(page.locator('[data-edit-cancel]')).toBeVisible();
    await expect(page.locator('[data-segment-text]').first()).toBeHidden();

    // Entering edit mode is presentation only: still the machine source.
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');
});

test('P6-003: save persists the edit as a new revision and survives reload', async ({ page }) => {
    await open(page, fixtures.main.transcriptionId);

    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('Browser edited first');
    await save(page);

    await expect(page.locator('[data-revision-notice]')).toBeVisible();
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(page.locator('[data-revision-indicator]')).toHaveAttribute('data-revision-state', 'revision');
    await expect(segmentTexts(page).first()).toHaveText('Browser edited first');

    // Reload durability.
    await open(page, fixtures.main.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(segmentTexts(page).first()).toHaveText('Browser edited first');
});

test('P6-003: cancel discards local edits and persists nothing', async ({ page }) => {
    await open(page, fixtures.cancel.transcriptionId);
    const original = await segmentTexts(page).first().textContent();

    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('Draft that should be discarded');
    await page.click('[data-edit-cancel]');

    await expect(page.locator('[data-edit-text]').first()).toBeHidden();
    await expect(page.locator('[data-segment-text]').first()).toBeVisible();
    await expect(segmentTexts(page).first()).toHaveText(original);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');

    await open(page, fixtures.cancel.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');
    await expect(segmentTexts(page).first()).toHaveText(original);
});

test('P6-003: a stale save is rejected with a visible conflict and no silent merge', async ({ page, context }) => {
    await open(page, fixtures.conflict.transcriptionId);

    // Page 1 opens the editor against the machine source.
    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('First writer wins');

    // Page 2 commits first and moves the active pointer.
    const second = await context.newPage();
    await open(second, fixtures.conflict.transcriptionId);
    await second.click('[data-edit-enter]');
    await second.locator('[data-edit-text]').first().fill('Second writer');
    await save(second);
    await expect(second.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await second.close();

    // Page 1's stale save is rejected: no merge, conflict shown, reload path.
    await save(page);
    await expect(page.locator('[data-revision-conflict]')).toBeVisible();
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(segmentTexts(page).first()).toHaveText('Second writer');
});

test('P6-003: undo and redo move the active pointer and render the expected text', async ({ page }) => {
    await open(page, fixtures.history.transcriptionId);
    const original = await segmentTexts(page).first().textContent();

    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('History v2');
    await save(page);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(segmentTexts(page).first()).toHaveText('History v2');

    await expect(page.locator('[data-edit-undo]')).toBeEnabled();
    await submitForm(page, '[data-edit-undo]');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');
    await expect(segmentTexts(page).first()).toHaveText(original);
    await expect(page.locator('[data-revision-notice]')).toBeVisible();

    await expect(page.locator('[data-edit-redo]')).toBeEnabled();
    await submitForm(page, '[data-edit-redo]');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(segmentTexts(page).first()).toHaveText('History v2');
});

test('P6-003: a new edit after undo branches and disables automatic redo', async ({ page }) => {
    await open(page, fixtures.branch.transcriptionId);

    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('Branch v2');
    await save(page);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');

    await submitForm(page, '[data-edit-undo]');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');

    // Edit after undo branches from v1, giving the branch point two children.
    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('Branch v3 from v1');
    await save(page);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v3');
    await expect(segmentTexts(page).first()).toHaveText('Branch v3 from v1');

    // Automatic redo must not choose among siblings: the control is disabled.
    await expect(page.locator('[data-edit-redo]')).toBeDisabled();
});

test('P6-003: a non-owner cannot view or edit the transcript workspace', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.main.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});

test('P6-003: the immutable machine source is unchanged after an edit', async ({ page }) => {
    await open(page, fixtures.immutable.transcriptionId);
    const original = await segmentTexts(page).first().textContent();

    await page.click('[data-edit-enter]');
    await page.locator('[data-edit-text]').first().fill('Mutating edit');
    await save(page);
    await expect(segmentTexts(page).first()).toHaveText('Mutating edit');

    // Undo reaches the durable initial revision, which is the verbatim machine
    // copy: the original machine text is still intact.
    await submitForm(page, '[data-edit-undo]');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');
    await expect(segmentTexts(page).first()).toHaveText(original);

    await open(page, fixtures.immutable.transcriptionId);
    await expect(segmentTexts(page).first()).toHaveText(original);
});
