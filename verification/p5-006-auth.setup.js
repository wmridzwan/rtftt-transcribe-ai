import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { laravelEnv, phpBin, root } from './p5-006-env.mjs';

const dir = path.dirname(fileURLToPath(import.meta.url));

export default async function globalSetup(config) {
    const baseURL = config.projects[0]?.use?.baseURL ?? 'http://127.0.0.1:8123';

    // Fresh, deterministic verification database + fixtures.
    execFileSync(phpBin, [path.join(dir, 'p5-006-seed.php')], { cwd: root, env: laravelEnv, stdio: 'inherit' });

    const fixtures = JSON.parse(fs.readFileSync(path.join(dir, 'p5-006-fixtures.json'), 'utf8'));
    fs.mkdirSync(path.join(dir, 'artifacts'), { recursive: true });

    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    const page = await browser.newPage();

    await page.goto(baseURL + '/login');
    await page.fill('input[name="email"]', fixtures.email);
    await page.fill('input[name="password"]', fixtures.password);
    await page.click('[data-test="login-button"]');
    await page.waitForURL('**/dashboard', { timeout: 30000 });

    await page.context().storageState({ path: path.join(dir, 'artifacts', 'p5-006-auth-state.json') });
    await browser.close();
}
