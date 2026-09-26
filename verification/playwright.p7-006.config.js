import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p7-006-auth-state.json');

export default defineConfig({
    testDir: './p7-006',
    timeout: 120000,
    expect: { timeout: 20000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p7-006-auth.setup.js'),
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8128',
        headless: true,
        viewport: { width: 1000, height: 420 },
        storageState: authFile,
        launchOptions: {
            args: ['--autoplay-policy=no-user-gesture-required', '--mute-audio', '--no-sandbox'],
        },
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1000, height: 420 } },
        },
    ],
});
