import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p6-009-rerun-01-auth-state.json');

// P6-009-RERUN-01 browser verification config (DC-01): full final-gate rerun
// after P6-010 DONE. Dedicated rerun database, fixtures
// (verification/p6-009-rerun-01-seed.php), port 8130, and results under
// verification/p6-009-rerun-01/ — never production data, never the original
// verification/p6-009/ artifacts.

export default defineConfig({
    testDir: './p6-009-rerun-01',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p6-009-rerun-01-auth.setup.js'),
    reporter: [['list'], ['json', { outputFile: path.join(dir, 'p6-009-rerun-01', 'p6-009-rerun-01-browser-results.json') }]],
    use: {
        baseURL: 'http://127.0.0.1:8130',
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
