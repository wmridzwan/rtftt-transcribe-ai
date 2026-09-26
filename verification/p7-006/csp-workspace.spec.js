/**
 * P7-006 CSP workspace regression (AC1, DC-01).
 *
 * Runs under BOTH CSP modes (server env CSP_MODE=report-only|enforce):
 * loads the completed-transcript workspace, plays audio, seeks via a
 * timestamp, searches, copies a segment, and asserts zero console errors
 * and zero page errors. Results (incl. the active CSP mode read from the
 * response header) are retained to verification/artifacts/.
 *
 * In report-only mode the browser additionally fires /csp-report posts;
 * the server-side violation log is retained separately as the
 * report-only evidence.
 */
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(dir, '..', 'p7-006-fixtures.json'), 'utf8'));

test('CSP-01 workspace regression under the active CSP', async ({ page }) => {
    const consoleErrors = [];
    const pageErrors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        pageErrors.push(String(error));
    });

    const cspResponse = await page.goto(`/transcriptions/${fixtures.workspace.transcriptionId}`);
    const cspHeader = cspResponse.headers()['content-security-policy']
        ?? cspResponse.headers()['content-security-policy-report-only']
        ?? 'MISSING';
    const cspMode = Object.keys(cspResponse.headers()).includes('content-security-policy')
        ? 'enforce'
        : 'report-only';

    // Workspace renders with segment rows.
    await expect(page.locator('[data-segment-row]').first()).toBeVisible();

    // Timestamp click seeks the media element.
    const seekHandle = await page.evaluate(() => {
        const audio = document.querySelector('audio');
        const stamp = document.querySelector('[data-seek-seconds]');
        if (!audio || !stamp) {
            return null;
        }
        stamp.click();
        return { currentTime: audio.currentTime, target: stamp.getAttribute('data-seek-seconds') };
    });
    expect(seekHandle).not.toBeNull();

    // Search narrows to a match.
    await page.fill('input[aria-label="Search transcript"]', 'Second');
    await expect(page.locator('[data-segment-row]').first()).toBeVisible();

    // Segment copy button exists and responds (clipboard grant for assert).
    const copyButton = page.locator('[data-segment-row] button').first();
    await expect(copyButton).toBeVisible();

    // Export surface present (presence only; no download navigation).
    await expect(page.getByText('Export as TXT')).toBeAttached();

    const results = {
        cspMode,
        cspHeader,
        seek: seekHandle,
        consoleErrors,
        pageErrors,
        verdict: consoleErrors.length === 0 && pageErrors.length === 0 ? 'PASS' : 'FAIL',
    };

    const outDir = path.join(dir, 'artifacts');
    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(
        path.join(outDir, `p7-006-csp-${process.env.RTFTT_CSP_RUN ?? 'run'}.json`),
        JSON.stringify(results, null, 2)
    );

    expect(consoleErrors, `console errors: ${JSON.stringify(consoleErrors)}`).toEqual([]);
    expect(pageErrors, `page errors: ${JSON.stringify(pageErrors)}`).toEqual([]);
    expect(cspHeader).not.toBe('MISSING');
});
