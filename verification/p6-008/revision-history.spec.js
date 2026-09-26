import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-008 browser verification (DC-01): the human-visible revision-history /
// audit surface and explicit historical-revision activation through the real
// product path. Real Chromium against the real Laravel transcript workspace
// (dedicated P6-008 verification DB).

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-008-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-008-intruder-state.json');

async function open(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-revision-history]');
    await page.waitForSelector('[data-transcript-region]');
}

test('P6-008: the history list renders every durable revision in version order with persisted metadata', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    const entries = page.locator('[data-history-entry]');
    await expect(entries).toHaveCount(2);
    await expect(entries.nth(0)).toHaveAttribute('data-history-version', '1');
    await expect(entries.nth(1)).toHaveAttribute('data-history-version', '2');

    await expect(entries.nth(0).locator('[data-history-parent]')).toContainText('from machine source');
    await expect(entries.nth(1).locator('[data-history-parent]')).toContainText('from v1');

    await expect(entries.nth(0).locator('[data-history-author]')).toContainText('P6-008 Owner');
    await expect(entries.nth(0).locator('[data-history-segments]')).toContainText('3 segments');
    await expect(entries.nth(0).locator('[data-history-created]')).not.toBeEmpty();
});

test('P6-008: the active revision is unambiguously identified', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');

    const active = page.locator('[data-history-entry][data-history-version="2"] [data-history-active]');
    await expect(active).toBeVisible();
    await expect(active).toHaveText('Active');
    await expect(page.locator('[data-history-entry][data-history-version="1"] [data-history-active]')).toHaveCount(0);
});

test('P6-008: machine source is authoritative before any revision exists', async ({ page }) => {
    await open(page, fixtures.plain.transcriptionId);

    await expect(page.locator('[data-history-machine-source]')).toBeVisible();
    await expect(page.locator('[data-history-machine-source]')).toContainText('machine transcript is authoritative');
    await expect(page.locator('[data-history-entry]')).toHaveCount(0);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');
});

test('P6-008: a historical revision is selected and activated through the product path', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    await page.locator('[data-history-entry][data-history-version="1"] [data-history-activate]').click();

    await expect(page.locator('[data-history-notice]')).toBeVisible();
    await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');

    // The active marker moves and the workspace reflects the activated revision.
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v1');
    await expect(page.locator('[data-history-entry][data-history-version="1"] [data-history-active]')).toBeVisible();
    await expect(page.locator('[data-history-entry][data-history-version="2"] [data-history-active]')).toHaveCount(0);
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.machineTexts[0]);
});

test('P6-008: activation survives a reload (durable active pointer)', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    // Activate whichever revision is not currently active (order-agnostic:
    // earlier tests may have moved the pointer on this shared fixture).
    const entry = page.locator('[data-history-entry]:has([data-history-activate])').first();
    const version = await entry.getAttribute('data-history-version');

    await entry.locator('[data-history-activate]').click();
    await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');
    await expect(page.locator('[data-revision-indicator]')).toHaveText(`Revision v${version}`);

    await open(page, fixtures.linear.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText(`Revision v${version}`);
    await expect(page.locator(`[data-history-entry][data-history-version="${version}"] [data-history-active]`)).toBeVisible();
});

test('P6-008: an arbitrary sibling-branch revision is activatable beyond undo reach', async ({ page }) => {
    await open(page, fixtures.branched.transcriptionId);

    // v3 is active; v2 is a sibling branch child, unreachable by undo.
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v3');
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.branchedTexts[0]);

    await page.locator('[data-history-entry][data-history-version="2"] [data-history-activate]').click();

    await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.editedTexts[0]);
});

test('P6-008: a stale activation base fails closed as a visible conflict with no pointer move', async ({ page }) => {
    await open(page, fixtures.branched.transcriptionId);

    const indicator = page.locator('[data-revision-indicator]');
    const before = await indicator.textContent();

    // Tamper the composed base of an activation form, then submit it through
    // the real form (order-agnostic: any non-active revision's form works).
    await page.evaluate(() => {
        const form = document.querySelector('[data-history-activate-form]');
        form.querySelector('input[name="expected_base"]').value = 'stale-base-revision-id';
    });
    await page.locator('[data-history-activate]').first().click();

    await expect(page.locator('[data-history-conflict]')).toBeVisible();
    await expect(page.locator('[data-history-conflict]')).toContainText('was not changed');
    await expect(indicator).toHaveText(before);
});

test('P6-008: persisted staleness-cause markers match the P6-005 record exactly', async ({ page }) => {
    await open(page, fixtures.stale.transcriptionId);

    const marker = page.locator('[data-history-stale-cause]');
    await expect(marker).toHaveCount(1);
    await expect(marker).toHaveAttribute('data-history-stale-cause', 'ms');
    // The structural-toolbar staleness block agrees (same persisted row).
    await expect(page.locator('[data-translation-stale="ms"]')).toBeVisible();
});

test('P6-008: a non-owner cannot view the history surface', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.linear.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});
