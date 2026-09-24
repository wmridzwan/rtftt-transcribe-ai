import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-007 browser verification (DC-01): presentation-only source / active
// revision / persisted-translation comparison in the existing transcript
// workspace. Real Chromium against the real Laravel workspace page (dedicated
// P6-007 verification DB). Read-only: no comparison action mutates state.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-007-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-007-intruder-state.json');

async function open(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-transcript-region]');
    await page.waitForFunction(() => {
        const el = document.querySelector('[data-transcript-filter-label]');
        return el && /\d+ segment/.test(el.textContent || '');
    }, null, { timeout: 15000 });
}

async function showComparison(page) {
    await page.click('[data-compare-view="compare"]');
    await expect(page.locator('[data-comparison-panel]')).toBeVisible();
}

test('P6-007: the comparison toggle switches the transcript region without navigating away', async ({ page }) => {
    await open(page, fixtures.plain.transcriptionId);

    await expect(page.locator('[data-transcript-region]')).toBeVisible();
    await expect(page.locator('[data-comparison-panel]')).toBeHidden();
    await expect(page.locator('[data-compare-view="compare"]')).toHaveAttribute('aria-pressed', 'false');

    await showComparison(page);

    await expect(page.locator('[data-comparison-panel]')).toBeVisible();
    await expect(page.locator('[data-transcript-region]')).toBeHidden();
    await expect(page.locator('[data-compare-view="compare"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('[data-transcript-comparison]')).toContainText('Comparison is read-only');

    await page.click('[data-compare-view="normal"]');
    await expect(page.locator('[data-transcript-region]')).toBeVisible();
    await expect(page.locator('[data-comparison-panel]')).toBeHidden();
});

test('P6-007: machine source ↔ translation is aligned by segment_index', async ({ page }) => {
    await open(page, fixtures.plain.transcriptionId);
    await showComparison(page);

    await expect(page.locator('[data-comparison-state]')).toHaveAttribute('data-comparison-state', 'machine');
    await expect(page.locator('[data-comparison-translation-state]')).toContainText('ZH');

    const rows = page.locator('[data-comparison-row]');
    await expect(rows).toHaveCount(3);

    for (let i = 0; i < 3; i += 1) {
        await expect(rows.nth(i).locator('[data-comparison-machine]')).toHaveText(fixtures.machineTexts[i]);
        await expect(rows.nth(i).locator('[data-comparison-translation]')).toHaveText(fixtures.translatedTexts[i]);
    }
});

test('P6-007: machine source is authoritative and no-translation state is factual when there is no revision', async ({ page }) => {
    await open(page, fixtures.plain.transcriptionId);
    await showComparison(page);

    await expect(page.locator('[data-comparison-no-translation]')).toBeHidden();
    await expect(page.locator('[data-comparison-revision-state="machine-authoritative"]').first()).toBeVisible();

    const nonePage = await page.context().newPage();
    await open(nonePage, fixtures.none.transcriptionId);
    await showComparison(nonePage);

    await expect(nonePage.locator('[data-comparison-no-translation]')).toBeVisible();
    // Machine source vs active revision is still available without a translation.
    await expect(nonePage.locator('[data-comparison-state]')).toHaveAttribute('data-comparison-state', 'revision');
    await expect(nonePage.locator('[data-comparison-revision-text]').first()).toHaveText(fixtures.editedTexts[0]);
    // No translation was ever persisted: no chronology/provenance note may be
    // shown at any level (the P6-007 MEDIUM truthfulness corrective).
    await expect(nonePage.locator('[data-comparison-revision-edited-note]')).toHaveCount(0);
    await expect(nonePage.locator('[data-comparison-mismatch-note]')).toHaveCount(0);
    await expect(nonePage.getByText('Edited after the translation was produced')).toHaveCount(0);
    await nonePage.close();
});

test('P6-007: an edited revision shows the translation as belonging to the machine source, with an explicit mismatch', async ({ page }) => {
    await open(page, fixtures.edited.transcriptionId);
    await showComparison(page);

    await expect(page.locator('[data-comparison-state]')).toHaveAttribute('data-comparison-state', 'revision');
    await expect(page.locator('[data-comparison-source-state]')).toContainText('Active revision v2');
    await expect(page.locator('[data-comparison-mismatch-note]')).toBeVisible();
    await expect(page.locator('[data-comparison-revision-edited-note]').first()).toBeVisible();

    const rows = page.locator('[data-comparison-row]');
    // The translation column still shows the machine-source translation.
    await expect(rows.nth(0).locator('[data-comparison-translation]')).toHaveText(fixtures.translatedTexts[0]);
    // The revision column shows the edited revision text.
    await expect(rows.nth(0).locator('[data-comparison-revision-text]')).toHaveText(fixtures.editedTexts[0]);
});

test('P6-007: a textually identical active revision aligns to the translation with no mismatch note', async ({ page }) => {
    await open(page, fixtures.identical.transcriptionId);
    await showComparison(page);

    await expect(page.locator('[data-comparison-state]')).toHaveAttribute('data-comparison-state', 'revision');
    await expect(page.locator('[data-comparison-mismatch-note]')).toHaveCount(0);
    await expect(page.locator('[data-comparison-revision-text]').first()).toHaveText(fixtures.machineTexts[0]);
});

test('P6-007: a structurally changed revision presents alignment as unavailable and does not remap the translation by index', async ({ page }) => {
    await open(page, fixtures.structural.transcriptionId);
    await showComparison(page);

    await expect(page.locator('[data-comparison-revision-state="alignment-unavailable"]').first()).toBeVisible();

    const rows = page.locator('[data-comparison-row]');
    // Machine-aligned rows still show the machine translation; unaligned revision
    // rows never show translation content in the revision column.
    await expect(page.locator('[data-comparison-translation]').filter({ hasText: fixtures.translatedTexts[0] }).first()).toBeVisible();
    await expect(rows.filter({ hasText: 'Restructured A' }).locator('[data-comparison-translation]')).toHaveText('—');
});

test('P6-007: comparison actions perform no mutation of persisted state', async ({ page }) => {
    await open(page, fixtures.edited.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');

    await showComparison(page);
    await page.click('[data-compare-view="normal"]');
    await showComparison(page);

    // No write feedback of any kind appears.
    await expect(page.locator('[data-revision-notice]')).toHaveCount(0);
    await expect(page.locator('[data-revision-error]')).toHaveCount(0);

    // A reload still shows the same active revision and text.
    await open(page, fixtures.edited.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await showComparison(page);
    await expect(page.locator('[data-comparison-revision-text]').first()).toHaveText(fixtures.editedTexts[0]);
});

test('P6-007: a non-owner cannot view the comparison', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.plain.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});
