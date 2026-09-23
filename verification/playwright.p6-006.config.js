import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(dir, 'artifacts', 'p6-006-auth-state.json');

// P6-006 browser verification config. Uses the dedicated P6-006 verification
// database and fixtures (verification/p6-006-seed.php); never production data.
// Results are written to a tracked path under verification/p6-006/ so the
// evidence is reproducible from the repository, not only gitignored JSON.

export default defineConfig({
    testDir: './p6-006',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    globalSetup: path.join(dir, 'p6-006-auth.setup.js'),
    reporter: [['list'], ['json', { outputFile: path.join(dir, 'p6-006', 'p6-006-browser-results.json') }]],
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
