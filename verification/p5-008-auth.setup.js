import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { appPort, laravelEnv, phpBin, root } from './p5-008-env.mjs';

const dir = path.dirname(fileURLToPath(import.meta.url));

export default async function globalSetup(config) {
    const baseURL = config.projects[0]?.use?.baseURL ?? `http://127.0.0.1:${appPort}`;

    // Fresh, deterministic browser verification database + fixtures.
    execFileSync(phpBin, [path.join(dir, 'p5-008-seed.php')], { cwd: root, env: laravelEnv, stdio: 'inherit' });

    const fixtures = JSON.parse(fs.readFileSync(path.join(dir, 'p5-008-fixtures.json'), 'utf8'));
    const artifacts = path.join(dir, 'artifacts');
    fs.mkdirSync(artifacts, { recursive: true });

    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    const page = await browser.newPage();

    await page.goto(baseURL + '/login');
    await page.fill('input[name="email"]', fixtures.email);
    await page.fill('input[name="password"]', fixtures.password);
    await page.click('[data-test="login-button"]');
    await page.waitForURL('**/dashboard', { timeout: 30000 });

    await page.context().storageState({ path: path.join(artifacts, 'p5-008-auth-state.json') });
    await browser.close();
}