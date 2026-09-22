import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(fs.readFileSync(path.join(here, '..', 'p4-006-fixtures.json'), 'utf8'));

async function open(page) {
    await page.goto(`/transcriptions/${fixtures.audio.transcriptionId}`);
    await page.waitForSelector('[data-media-player]');
}

async function currentIndex(page) {
    return page.evaluate(() => {
        const marks = Array.from(document.querySelectorAll('mark[data-match-index]'));
        return marks.findIndex((m) => m.className.includes('bg-orange-400'));
    });
}

async function countLabel(page) {
    return page.locator('input[type="search"] + span').innerText();
}

test('P4-004 regression: search navigation advances, reverses, and wraps', async ({ page }) => {
    await open(page);
    await page.fill('input[type="search"]', 'segmen');
    await page.waitForTimeout(300);

    expect(await page.locator('mark').count()).toBe(4);
    expect(await currentIndex(page)).toBe(0);

    await page.getByRole('button', { name: 'Next' }).click();
    await page.waitForTimeout(150);
    expect(await currentIndex(page)).toBe(1);

    await page.getByRole('button', { name: 'Next' }).click();
    await page.waitForTimeout(150);
    expect(await currentIndex(page)).toBe(2);

    await page.getByRole('button', { name: 'Previous' }).click();
    await page.waitForTimeout(150);
    expect(await currentIndex(page)).toBe(1);

    await page.getByRole('button', { name: 'Previous' }).click();
    await page.waitForTimeout(150);
    expect(await currentIndex(page)).toBe(0);

    // Wrap backwards from the first match to the last.
    await page.getByRole('button', { name: 'Previous' }).click();
    await page.waitForTimeout(150);
    expect(await currentIndex(page)).toBe(3);
    expect(await countLabel(page)).toBe('4 of 4');
});

test('P4-004 regression: segment copy copies exact text (Latin, Chinese, Tamil)', async ({ page }) => {
    await open(page);

    const cases = [
        { index: 0, text: 'First segment' },
        { index: 2, text: '第三段' },
        { index: 3, text: 'நான்காம்' },
    ];

    for (const item of cases) {
        await page.locator(`[data-segment-row][data-segment-index="${item.index}"] button[aria-label="Copy segment"]`).click();
        await page.waitForTimeout(400);
        const clipboard = await page.evaluate(() => navigator.clipboard.readText());
        expect(clipboard, `segment ${item.index}`).toBe(item.text);
    }
});

test('P4-004 regression: full transcript copy still matches ordered text', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Copy transcript' }).click();
    await page.waitForTimeout(400);
    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    expect(clipboard.replace(/\r\n/g, '\n')).toBe(fixtures.copyFullText);
});
