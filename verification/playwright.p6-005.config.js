import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p6-005-auth-state.json');

// P6-005 browser verification config (DC-01). Uses the dedicated P6-005
// verification database and fixtures (verification/p6-005-seed.php); never
// production data. Results are written to a tracked path under
// verification/p6-005/ so the evidence is reproducible from the repository.

export default defineConfig({
    testDir: './p6-005',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p6-005-auth.setup.js'),
    reporter: [['list'], ['json', { outputFile: path.join(dir, 'p6-005', 'p6-005-browser-results.json') }]],
    use: {
        baseURL: 'http://127.0.0.1:8127',
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