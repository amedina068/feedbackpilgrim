// Writes .env.e2e from .env.example and makes an empty SQLite database.
// The Playwright webServer runs this script before the migrations, so the
// end-to-end tests work from a clean checkout with no manual setup.
//
// The file gets a new random APP_KEY on each run. Git ignores .env.e2e and
// database/e2e.sqlite, so no generated secret goes into the repository.

import { randomBytes } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const port = process.env.E2E_PORT;

if (!port) {
    console.error('E2E_PORT is not set. Run the tests with "npm run test:e2e".');
    process.exit(1);
}

// A cached config ignores .env.e2e. Then the migrations run on the database
// of the cached config, so stop before that can happen.
if (existsSync(resolve(root, 'bootstrap/cache/config.php'))) {
    console.error('bootstrap/cache/config.php exists. Run "php artisan config:clear" first.');
    process.exit(1);
}

const database = resolve(root, 'database/e2e.sqlite');

const overrides = {
    APP_ENV: 'e2e',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_DEBUG: 'true',
    // The debug bar covers the bottom of the page and blocks clicks there.
    DEBUGBAR_ENABLED: 'false',
    APP_DOMAIN: 'localhost',
    APP_URL: `http://localhost:${port}`,
    TRUSTED_PROXIES: '',
    REGISTRATION_ENABLED: 'false',
    SESSION_DOMAIN: 'null',
    SESSION_DRIVER: 'file',
    SESSION_SECURE_COOKIE: 'false',
    SANCTUM_STATEFUL_DOMAINS: `localhost:${port},*.localhost:${port}`,
    LOG_STACK: 'single',
    LOG_LEVEL: 'debug',
    ROLLBAR_TOKEN: '',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: JSON.stringify(database),
    CACHE_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    BROADCAST_DRIVER: 'null',
    FILESYSTEM_DISK: 'local',
    MAIL_MAILER: 'log',
    MAILGUN_DOMAIN: '',
    MAILGUN_SECRET: '',
    AWS_ACCESS_KEY_ID: '',
    AWS_SECRET_ACCESS_KEY: '',
    AWS_SES_ACCESS_KEY_ID: '',
    AWS_SES_SECRET_ACCESS_KEY: '',
};

const pending = new Set(Object.keys(overrides));

const lines = readFileSync(resolve(root, '.env.example'), 'utf8')
    .split('\n')
    .map((line) => {
        const match = line.match(/^([A-Z0-9_]+)=/);

        if (!match || !(match[1] in overrides)) {
            return line;
        }

        pending.delete(match[1]);

        return `${match[1]}=${overrides[match[1]]}`;
    });

for (const key of pending) {
    lines.push(`${key}=${overrides[key]}`);
}

writeFileSync(resolve(root, '.env.e2e'), lines.join('\n'));
writeFileSync(database, '');

console.log(`Wrote .env.e2e and an empty ${database}.`);
