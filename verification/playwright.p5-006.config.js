import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { appPort, laravelEnv, phpBin, root, workerPort, workerToken } from './p5-006-env.mjs';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p5-006-auth-state.json');

export default defineConfig({
    testDir: './p5-006',
    timeout: 180000,
    expect: { timeout: 30000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p5-006-auth.setup.js'),
    reporter: [['list']],
    // Two local servers: Laravel (PHP built-in server + router) and the
    // deterministic translation-worker test double.
    webServer: [
        {
            command: `"${phpBin}" -S 127.0.0.1:${appPort} -t public "${path.join(dir, 'p5-006-server-router.php')}"`,
            cwd: root,
            url: `http://127.0.0.1:${appPort}/up`,
            env: laravelEnv,
            reuseExistingServer: false,
            timeout: 60000,
        },
        {
            command: `node "${path.join(dir, 'p5-006-fake-translation-worker.mjs')}"`,
            cwd: root,
            url: `http://127.0.0.1:${workerPort}/health`,
            env: { ...process.env, P5_006_WORKER_PORT: String(workerPort), P5_006_WORKER_TOKEN: workerToken },
            reuseExistingServer: false,
            timeout: 30000,
        },
    ],
    use: {
        baseURL: `http://127.0.0.1:${appPort}`,
        headless: true,
        viewport: { width: 1280, height: 900 },
        storageState: authFile,
        acceptDownloads: true,
        permissions: ['clipboard-read', 'clipboard-write'],
        launchOptions: { args: ['--no-sandbox'] },
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
    ],
});
