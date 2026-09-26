import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-009-RERUN-01 browser verification (DC-01): full final-gate rerun after
// P6-010 DONE. Same integrated product-path coverage as the original run plus
// a strengthened revision-aware export content check (F-001 regression):
// downloads must carry the active revision text, not the machine source.
// Original verification/p6-009/ artifacts are never read or written here.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p6-009-rerun-01-fixtures.json'), 'utf8'));
const intruderState = path.join(here, '..', 'artifacts', 'p6-009-rerun-01-intruder-state.json');

async function open(page, id) {
    await page.goto(`/transcriptions/${id}`);
    await page.waitForSelector('[data-revision-history]');
    await page.waitForSelector('[data-transcript-region]');
}

test('P6-009-RERUN-01: the workspace presents the active revision with history and comparison hooks', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    await expect(page.locator('[data-revision-indicator]')).toHaveText('Revision v2');
    await expect(page.locator('[data-history-entry]')).toHaveCount(2);
    await expect(page.locator('[data-history-entry][data-history-version="2"] [data-history-active]')).toBeVisible();
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.editedTexts[0]);
    await expect(page.locator('[data-compare-view="normal"]')).toBeVisible();
    await expect(page.locator('[data-compare-view="compare"]')).toBeVisible();
});

test('P6-009-RERUN-02: machine source is authoritative before any revision exists', async ({ page }) => {
    await open(page, fixtures.plain.transcriptionId);

    await expect(page.locator('[data-revision-indicator]')).toHaveText('Machine transcript');
    await expect(page.locator('[data-history-machine-source]')).toBeVisible();
    await expect(page.locator('[data-history-entry]')).toHaveCount(0);
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.machineTexts[0]);
});

test('P6-009-RERUN-03: undo moves the active pointer to the strict ancestor through the product path', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    // Order-agnostic: linear may already sit on v1 if a previous run moved it.
    const indicator = page.locator('[data-revision-indicator]');
    const before = await indicator.textContent();

    if (before === 'Revision v2') {
        await page.locator('[data-edit-undo]').click();
        await expect(page.locator('[data-revision-notice]')).toBeVisible();
        await expect(indicator).toHaveText('Revision v1');
        await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.machineTexts[0]);
    } else {
        await expect(indicator).toHaveText('Revision v1');
    }
});

test('P6-009-RERUN-04: redo follows the unique child through the product path', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    const indicator = page.locator('[data-revision-indicator]');
    const before = await indicator.textContent();

    if (before === 'Revision v1') {
        await page.locator('[data-edit-redo]').click();
        await expect(page.locator('[data-revision-notice]')).toBeVisible();
        await expect(indicator).toHaveText('Revision v2');
        await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.editedTexts[0]);
    } else {
        await expect(indicator).toHaveText('Revision v2');
    }
});

test('P6-009-RERUN-05: a sibling-branch revision is activatable beyond undo reach', async ({ page }) => {
    await open(page, fixtures.branched.transcriptionId);

    // v2 is a sibling branch child, unreachable by undo from v3.
    // Order-agnostic: the shared fixture may sit on any pointer after
    // earlier runs, so first move off v2 if it is already active.
    const indicator = page.locator('[data-revision-indicator]');
    if ((await indicator.textContent()) === 'Revision v2') {
        await page.locator('[data-history-entry][data-history-version="3"] [data-history-activate]').click();
        await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');
    }

    await page.locator('[data-history-entry][data-history-version="2"] [data-history-activate]').click();

    await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');
    await expect(indicator).toHaveText('Revision v2');
    await expect(page.locator('[data-segment-text]').first()).toHaveText(fixtures.editedTexts[0]);
});

test('P6-009-RERUN-06: activation survives a reload (durable active pointer)', async ({ page }) => {
    await open(page, fixtures.branched.transcriptionId);

    const entry = page.locator('[data-history-entry]:has([data-history-activate])').first();
    const version = await entry.getAttribute('data-history-version');

    await entry.locator('[data-history-activate]').click();
    await expect(page.locator('[data-history-notice]')).toContainText('Historical revision activated');
    await expect(page.locator('[data-revision-indicator]')).toHaveText(`Revision v${version}`);

    await open(page, fixtures.branched.transcriptionId);
    await expect(page.locator('[data-revision-indicator]')).toHaveText(`Revision v${version}`);
    await expect(page.locator(`[data-history-entry][data-history-version="${version}"] [data-history-active]`)).toBeVisible();
});

test('P6-009-RERUN-07: a stale activation base fails closed as a visible conflict with no pointer move', async ({ page }) => {
    await open(page, fixtures.branched2.transcriptionId);

    const indicator = page.locator('[data-revision-indicator]');
    const before = await indicator.textContent();

    await page.evaluate(() => {
        const form = document.querySelector('[data-history-activate-form]');
        form.querySelector('input[name="expected_base"]').value = 'stale-base-revision-id';
    });
    await page.locator('[data-history-activate]').first().click();

    await expect(page.locator('[data-history-conflict]')).toBeVisible();
    await expect(page.locator('[data-history-conflict]')).toContainText('was not changed');
    await expect(indicator).toHaveText(before);
});

test('P6-009-RERUN-08: persisted staleness-cause markers match the P6-005 record exactly', async ({ page }) => {
    await open(page, fixtures.stale.transcriptionId);

    const marker = page.locator('[data-history-stale-cause]');
    await expect(marker).toHaveCount(1);
    await expect(marker).toHaveAttribute('data-history-stale-cause', 'ms');
    await expect(page.locator('[data-translation-stale="ms"]')).toBeVisible();
});

test('P6-009-RERUN-09: comparison represents structural incompatibility truthfully without false claims', async ({ page }) => {
    await open(page, fixtures.stale.transcriptionId);

    await page.locator('[data-compare-view="compare"]').click();

    await expect(page.locator('[data-comparison-state="revision"]')).toBeVisible();
    await expect(page.locator('[data-comparison-source-state]')).toContainText('Active revision');
    // A pure split preserves text, so hasEditedActiveRevision() is correctly
    // false and no mismatch note renders (no false claim). The struct
    // children instead render explicit alignment-unavailable markers.
    await expect(page.locator('[data-comparison-mismatch-note]')).toHaveCount(0);
    await expect(page.locator('[data-comparison-revision-state="alignment-unavailable"]')).not.toHaveCount(0);
    await expect(page.locator('[data-comparison-table]')).toBeVisible();
});

test('P6-009-RERUN-10: no-translation comparison state makes no chronology claim', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    await page.locator('[data-compare-view="compare"]').click();

    await expect(page.locator('[data-comparison-no-translation]')).toBeVisible();
    await expect(page.locator('[data-comparison-no-translation]')).toContainText('No translation available');
    await expect(page.locator('[data-comparison-revision-edited-note]')).toHaveCount(0);
});

test('P6-009-RERUN-11: completed-transcript exports carry the active revision, not the machine source', async ({ page }) => {
    await open(page, fixtures.linear.transcriptionId);

    // linear fixture: v2 text edit active — exports must reflect it (F-001).
    for (const format of ['txt', 'srt', 'vtt', 'docx']) {
        await expect(page.locator(`a[href$="/export/${format}"]`).first()).toBeAttached();
        const response = await page.request.get(`/transcriptions/${fixtures.linear.transcriptionId}/export/${format}`);
        expect(response.status()).toBe(200);
    }

    const txt = await page.request.get(`/transcriptions/${fixtures.linear.transcriptionId}/export/txt`);
    const txtBody = await txt.text();
    expect(txtBody).toContain(fixtures.editedTexts[0]);
    expect(txtBody).not.toContain(fixtures.machineTexts[0]);

    const srt = await page.request.get(`/transcriptions/${fixtures.linear.transcriptionId}/export/srt`);
    const srtBody = await srt.text();
    expect(srtBody).toContain(fixtures.editedTexts[0]);
    expect(srtBody).toContain('-->');

    const vtt = await page.request.get(`/transcriptions/${fixtures.linear.transcriptionId}/export/vtt`);
    const vttBody = await vtt.text();
    expect(vttBody).toContain('WEBVTT');
    expect(vttBody).toContain(fixtures.editedTexts[1]);

    // Machine fallback: the revision-free plain fixture still exports machine rows.
    const plainTxt = await page.request.get(`/transcriptions/${fixtures.plain.transcriptionId}/export/txt`);
    const plainBody = await plainTxt.text();
    expect(plainBody).toContain(fixtures.machineTexts[0]);
    expect(plainBody).not.toContain(fixtures.editedTexts[0]);
});

test('P6-009-RERUN-12: a non-owner cannot view the integrated workspace', async ({ browser }) => {
    const intruder = await browser.newContext({ storageState: intruderState });
    const response = await intruder.request.get(`/transcriptions/${fixtures.linear.transcriptionId}`);
    expect(response.status()).toBe(403);
    await intruder.close();
});
