import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P7-010 (TD-013): transcript workspace must be console-clean, and the
// rename modal must open/close without a ReferenceError. Regression spec
// for the `showRenameModal is not defined` defect: the modal owns its
// visibility state locally (show.blade.php), so teleporting can no longer
// break scope resolution.
const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p7-010-fixtures.json'), 'utf8'));
const artifactsDir = path.join(here, '..', 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

const results = {
    startedAt: new Date().toISOString(),
    consoleClean: {},
    consoleErrors: [],
    pageErrors: [],
};

test.describe.configure({ mode: 'default' });

test.afterAll(async () => {
    results.finishedAt = new Date().toISOString();
    fs.writeFileSync(path.join(artifactsDir, 'p7-010-console-results.json'), JSON.stringify(results, null, 2));
});

test('CON-01 workspace loads with zero console errors and zero page errors', async ({ page }) => {
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            results.consoleErrors.push(msg.text());
        }
    });
    page.on('pageerror', (error) => {
        results.pageErrors.push(String(error));
    });

    await page.goto(`/transcriptions/${fixtures.workspace.transcriptionId}`);
    await page.waitForSelector('[data-segment-row]', { timeout: 30000 });
    await page.waitForTimeout(2000);

    expect(results.consoleErrors, `console errors: ${JSON.stringify(results.consoleErrors)}`).toEqual([]);
    expect(results.pageErrors, `page errors: ${JSON.stringify(results.pageErrors)}`).toEqual([]);
    results.consoleClean.workspace = true;
});

test('CON-02 rename modal opens and closes without scope errors', async ({ page }) => {
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            results.consoleErrors.push(msg.text());
        }
    });
    page.on('pageerror', (error) => {
        results.pageErrors.push(String(error));
    });

    await page.goto(`/transcriptions/${fixtures.workspace.transcriptionId}`);
    await page.waitForSelector('[data-segment-row]', { timeout: 30000 });

    await page.getByRole('button', { name: 'Rename' }).click();
    await page.waitForSelector('input[name="title"]', { state: 'visible', timeout: 15000 });
    results.consoleClean.modalOpened = true;

    await page.getByRole('button', { name: 'Cancel' }).click();
    await page.waitForTimeout(500);

    expect(results.consoleErrors, `console errors: ${JSON.stringify(results.consoleErrors)}`).toEqual([]);
    expect(results.pageErrors, `page errors: ${JSON.stringify(results.pageErrors)}`).toEqual([]);
    results.consoleClean.modalClosedClean = true;
});

test('CON-03 ?rename=1 opens the rename modal on load without errors', async ({ page }) => {
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            results.consoleErrors.push(msg.text());
        }
    });
    page.on('pageerror', (error) => {
        results.pageErrors.push(String(error));
    });

    await page.goto(`/transcriptions/${fixtures.workspace.transcriptionId}?rename=1`);
    await page.waitForSelector('input[name="title"]', { state: 'visible', timeout: 15000 });
    results.consoleClean.renameParamOpened = true;

    expect(results.consoleErrors, `console errors: ${JSON.stringify(results.consoleErrors)}`).toEqual([]);
    expect(results.pageErrors, `page errors: ${JSON.stringify(results.pageErrors)}`).toEqual([]);
});
