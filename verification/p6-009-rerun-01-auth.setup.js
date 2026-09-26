import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));

// P6-009-RERUN-01 global setup: distinct auth states so the rerun never
// touches the original run's verification/artifacts/p6-009-*.json files.
export default async function globalSetup(config) {
    const baseURL = config.projects[0]?.use?.baseURL ?? 'http://127.0.0.1:8130';
    const fixtures = JSON.parse(fs.readFileSync(path.join(dir, 'p6-009-rerun-01-fixtures.json'), 'utf8'));

    fs.mkdirSync(path.join(dir, 'artifacts'), { recursive: true });

    const browser = await chromium.launch({ args: ['--no-sandbox'] });

    async function login(email, storagePath) {
        const page = await browser.newPage();
        await page.goto(baseURL + '/login');
        await page.fill('input[name="email"]', email);
        await page.fill('input[name="password"]', fixtures.password);
        await page.click('[data-test="login-button"]');
        await page.waitForURL('**/dashboard', { timeout: 30000 });
        await page.context().storageState({ path: storagePath });
        await page.close();
    }

    await login(fixtures.email, path.join(dir, 'artifacts', 'p6-009-rerun-01-auth-state.json'));
    await login(fixtures.intruderEmail, path.join(dir, 'artifacts', 'p6-009-rerun-01-intruder-state.json'));

    await browser.close();
}
