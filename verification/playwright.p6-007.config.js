import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p6-007-auth-state.json');

// P6-007 browser verification config (DC-01). Uses the dedicated P6-007
// verification database and fixtures (verification/p6-007-seed.php); never
// production data. Results are written to a tracked path under
// verification/p6-007/ so the evidence is reproducible from the repository.

export default defineConfig({
    testDir: './p6-007',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p6-007-auth.setup.js'),
    reporter: [['list'], ['json', { outputFile: path.join(dir, 'p6-007', 'p6-007-browser-results.json') }]],
    use: {
        baseURL: 'http://127.0.0.1:8125',
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
