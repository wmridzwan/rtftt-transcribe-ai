import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// P6-006 browser verification (DC-01): advanced navigation + language filter in
// the existing transcript workspace. This is a real Chromium run against the
// real Laravel workspace page; it does not replace the Pest feature tests.

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p4-006-fixtures.json'), 'utf8'));

async function open(page) {
    await page.goto(`/transcriptions/${fixtures.audio.transcriptionId}`);
    await page.waitForSelector('[data-transcript-region]');
}

test('P6-006: keyboard navigation moves and updates the active segment', async ({ page }) => {
    await open(page);

    const region = page.locator('[data-transcript-region]');
    await region.focus();

    await page.keyboard.press('ArrowDown');
    await expect(page.locator('[data-segment-row][aria-current="true"]')).toHaveAttribute('data-segment-index', '0');

    await page.keyboard.press('ArrowDown');
    await expect(page.locator('[data-segment-row][aria-current="true"]')).toHaveAttribute('data-segment-index', '1');

    await page.keyboard.press('ArrowUp');
    await expect(page.locator('[data-segment-row][aria-current="true"]')).toHaveAttribute('data-segment-index', '0');

    await page.keyboard.press('End');
    await expect(page.locator('[data-segment-row][aria-current="true"]')).toHaveAttribute('data-segment-index', '7');

    await page.keyboard.press('Home');
    await expect(page.locator('[data-segment-row][aria-current="true"]')).toHaveAttribute('data-segment-index', '0');
});

test('P6-006: language filter hides non-matching rows and re-syncs search counts', async ({ page }) => {
    await open(page);

    await page.selectOption('[data-transcript-language-filter]', 'zh');

    const visible = page.locator('[data-segment-row]:not([hidden])');
    await expect(visible).toHaveCount(2);
    await expect(page.locator('[data-segment-row][data-segment-language="zh"]:not([hidden])')).toHaveCount(2);

    await page.fill('input[type="search"]', '第八');
    await page.waitForTimeout(250);
    await expect(page.locator('mark')).toHaveCount(1);

    await page.selectOption('[data-transcript-language-filter]', '');
    await expect(page.locator('[data-segment-row]:not([hidden])')).toHaveCount(8);
});
