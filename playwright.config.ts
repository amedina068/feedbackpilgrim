import { defineConfig, devices } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * The port of the test server. The first load of this file finds a free port
 * and puts it in E2E_PORT. The test workers load this file again and inherit
 * E2E_PORT, so all of them use the same port. Set E2E_PORT to choose a port.
 */
function e2ePort(): string {
    if (!process.env.E2E_PORT) {
        process.env.E2E_PORT = execFileSync(process.execPath, [
            '-e',
            "const s = require('node:net').createServer(); s.listen(0, '127.0.0.1', () => { console.log(s.address().port); s.close(); });",
        ])
            .toString()
            .trim();
    }

    return process.env.E2E_PORT;
}

const port = e2ePort();

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    // The specs share one SQLite database and one PHP server.
    workers: 1,
    // Vue compiles the server HTML in the browser on each page load. On a busy
    // machine that takes seconds, so the limits are higher than the defaults.
    timeout: 90_000,
    expect: { timeout: 20_000 },
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://localhost:${port}`,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // CI uses the bundled Chromium. If it does not start on a local
                // machine, set E2E_BROWSER_CHANNEL=chrome to use Google Chrome.
                channel: process.env.E2E_BROWSER_CHANNEL || undefined,
            },
        },
    ],
    webServer: {
        command: [
            'node tests/e2e/support/prepare-env.mjs',
            'php artisan migrate:fresh --env=e2e --seed --seeder="Database\\Seeders\\E2eSeeder" --no-interaction',
            `php artisan serve --env=e2e --host=127.0.0.1 --port=${port} --no-reload`,
        ].join(' && '),
        url: `http://127.0.0.1:${port}/up`,
        // The PHP server answers one request at a time by default. More
        // workers serve the page and its assets together.
        env: { E2E_PORT: port, PHP_CLI_SERVER_WORKERS: '4' },
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
