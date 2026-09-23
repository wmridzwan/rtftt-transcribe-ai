import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p4-006-auth-state.json');

// P6-006 browser verification config. Reuses the P4-006 verification fixtures and
// auth setup (dedicated local verification DB, never production data).

export default defineConfig({
    testDir: './p6-006',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p4-006-auth.setup.js'),
    reporter: [['list'], ['json', { outputFile: 'artifacts/p6-006-browser-results.json' }]],
    use: {
        baseURL: 'http://127.0.0.1:8123',
        headless: true,
        storageState: authFile,
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
        launchOptions: {
            args: ['--autoplay-policy=no-user-gesture-required', '--mute-audio', '--no-sandbox'],
        },
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
