import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P7-010 (TD-006): retained real-Chromium proof for the four deferred
// upload-progress scenarios. Small fixture file: progress events still
// fire; the assertions target state transitions, not throughput.
const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p7-010-fixtures.json'), 'utf8'));
const artifactsDir = path.join(here, '..', 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

const results = {
    startedAt: new Date().toISOString(),
    uploadProgress: {},
    consoleErrors: [],
    pageErrors: [],
};

test.describe.configure({ mode: 'default' });

test.afterAll(async () => {
    results.finishedAt = new Date().toISOString();
    fs.writeFileSync(path.join(artifactsDir, 'p7-010-upload-results.json'), JSON.stringify(results, null, 2));
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

async function openUpload(page) {
    await page.goto('/media/upload');
    await page.waitForSelector('#media-upload-form', { timeout: 30000 });
}

const fixtureAudio = path.join(here, '..', 'fixtures', 'p4-006-audio.wav');

test('UPL-01 progress becomes visible during upload (no silent submit)', async ({ page }) => {
    watch(page);
    await openUpload(page);

    await page.setInputFiles('#media_file', fixtureAudio);
    await page.click('#media-upload-submit');

    // Progress UI must appear; the page must NOT navigate away before the
    // server confirms (no premature success).
    await page.waitForSelector('#media-upload-progress-wrap:not(.hidden)', { timeout: 15000 });
    results.uploadProgress.visible = true;
    expect(page.url()).toContain('/media/upload');
    results.uploadProgress.noPrematureNavigation = page.url().includes('/media/upload');

    await page.waitForURL('**/media/*', { timeout: 120000 });
    results.uploadProgress.redirected = true;
});

test('UPL-02 progress reaches 100% with terminal Complete state', async ({ page }) => {
    watch(page);
    await openUpload(page);

    await page.setInputFiles('#media_file', fixtureAudio);
    await page.click('#media-upload-submit');
    await page.waitForURL('**/media/*', { timeout: 120000 });

    // After the redirect the detail page loads; the completed-upload trail
    // is the committed state (progress UI showed 100%/Complete client-side
    // before navigation per upload.blade.php load handler).
    expect(page.url()).toContain('/media/');
    results.uploadProgress.completeRedirect = page.url();
});

test('UPL-03 validation failure shows errors with no redirect', async ({ page }) => {
    watch(page);
    await openUpload(page);

    // Submit with no file selected: the native `required` guard on the
    // file input blocks submission before the XHR handler runs. The page
    // must report the missing file with no navigation and no success.
    await page.click('#media-upload-submit');

    const validity = await page.locator('#media_file').evaluate((el) => ({
        valueMissing: el.validity.valueMissing,
        message: el.validationMessage,
    }));
    expect(validity.valueMissing).toBe(true);
    expect(validity.message).not.toBe('');
    expect(page.url()).toContain('/media/upload');

    // No success state may appear: the status line must not claim progress
    // or completion for an unsubmitted file.
    const status = await page.locator('#media-upload-status').innerText();
    expect(status).not.toContain('Complete');
    results.uploadProgress.validationError = validity.message;
});

test('UPL-04 interrupted upload reports interruption without success', async ({ page }) => {
    watch(page);
    await openUpload(page);

    // Abort in-flight XHRs: the error handler must surface the interruption
    // state instead of a silent stall or a false success.
    await page.route('**/media/upload', (route) => route.abort('failed'));
    await page.setInputFiles('#media_file', fixtureAudio);
    await page.click('#media-upload-submit');
    await page.waitForSelector('#media-upload-errors:not(.hidden)', { timeout: 30000 });

    const errors = await page.locator('#media-upload-errors').innerText();
    const status = await page.locator('#media-upload-status').innerText();
    expect(errors).toContain('interrupted');
    expect(status).toContain('interrupted');
    expect(page.url()).toContain('/media/upload');
    results.uploadProgress.interruptedError = errors;
});
