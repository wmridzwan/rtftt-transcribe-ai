import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { appPort, laravelEnv, phpBin, root } from './p5-008-env.mjs';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p5-008-auth-state.json');

// P5-008 canonical browser-to-real-model end-to-end proof (ADR-024).
//
// ONE Laravel server backed by a real Redis translation queue and the real
// authenticated Python worker. No translation-worker double is used here. The
// queue worker itself is managed by verification/p5-008-real-gate.mjs (or run
// manually per the runbook) because Playwright's webServer cannot supervise a
// long-lived queue process.
export default defineConfig({
    testDir: './p5-008',
    timeout: 900000,
    expect: { timeout: 60000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p5-008-auth.setup.js'),
    reporter: [['list']],
    webServer: [
        {
            command: `"${phpBin}" -S 127.0.0.1:${appPort} -t public "${path.join(dir, 'p5-006-server-router.php')}"`,
            cwd: root,
            url: `http://127.0.0.1:${appPort}/up`,
            env: { ...laravelEnv, PHP_CLI_SERVER_WORKERS: '4' },
            reuseExistingServer: false,
            timeout: 60000,
        },
    ],
    use: {
        baseURL: `http://127.0.0.1:${appPort}`,
        headless: true,
        viewport: { width: 1280, height: 900 },
        storageState: authFile,
        acceptDownloads: true,
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
        launchOptions: { args: ['--no-sandbox'] },
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
    ],
});