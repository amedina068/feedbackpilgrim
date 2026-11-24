import { test as base, expect, type Locator, type Page } from '@playwright/test';

/**
 * The hosts of the test server: the dashboard on localhost and the public
 * sites of the projects on <slug>.localhost.
 */
const LOCAL_HOST = /^(localhost|127\.0\.0\.1|[a-z0-9-]+\.localhost)$/;

/**
 * The Playwright test with one change: the browser stops each request to a
 * host that is not the test server. The layouts load fonts and a stylesheet
 * from CDNs, and the tests must not call third-party services.
 */
export const test = base.extend({
    context: async ({ context }, use) => {
        await context.route('**/*', (route) => {
            const { hostname } = new URL(route.request().url());

            return LOCAL_HOST.test(hostname) ? route.continue() : route.abort('blockedbyclient');
        });

        await use(context);
    },
});

export { expect };
export type { Page };

/** The owner that database/seeders/E2eSeeder.php creates. */
export const owner = { email: 'owner@example.com', password: 'password' };

/** A URL on the public site of the seeded "demo" project. */
export function publicSite(path = '/'): string {
    return `http://demo.localhost:${process.env.E2E_PORT}${path}`;
}

/**
 * Clicks a link or a submit button and waits for the load event of the next
 * page. Vue mounts on #app and renders the page again, and that clears the
 * values in the fields. Thus wait for the load event before you type.
 */
export async function clickAndWaitForLoad(page: Page, target: Locator): Promise<void> {
    const loaded = page.waitForEvent('load');

    await target.click();
    await loaded;
}

export async function logInAsOwner(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(owner.email);
    await page.getByLabel('Password').fill(owner.password);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
}
