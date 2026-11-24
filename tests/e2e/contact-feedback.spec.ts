import { clickAndWaitForLoad, expect, logInAsOwner, test, type Page } from './support/test';

/**
 * Opens the public site as the seeded contact Ada Lovelace. The dashboard
 * link "View site as contact" opens a signed sign-in link in a new tab.
 */
async function openPublicSiteAsAda(page: Page): Promise<Page> {
    await logInAsOwner(page);

    await page.goto('/contacts');
    await page.getByRole('link', { name: 'Ada Lovelace' }).click();

    const popupPromise = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'View site as contact' }).click();
    const site = await popupPromise;

    await expect(site).toHaveURL(/^http:\/\/demo\.localhost:\d+\/posts$/);
    await site.waitForLoadState();

    return site;
}

test.describe('a contact on the public site', () => {
    test('votes for a post and removes the vote', async ({ page }) => {
        const site = await openPublicSiteAsAda(page);
        const row = site.getByRole('listitem').filter({
            has: site.getByRole('link', { name: 'Export the posts to CSV' }),
        });

        await clickAndWaitForLoad(site, row.getByRole('button', { name: 'Upvote' }));

        await expect(row.getByRole('button', { name: 'Remove your vote' })).toHaveAttribute('aria-pressed', 'true');

        await clickAndWaitForLoad(site, row.getByRole('button', { name: 'Remove your vote' }));

        await expect(row.getByRole('button', { name: 'Upvote' })).toHaveAttribute('aria-pressed', 'false');
    });

    test('creates a new post', async ({ page }) => {
        const title = `Single sign-on with SAML ${Date.now()}`;
        const site = await openPublicSiteAsAda(page);

        await clickAndWaitForLoad(site, site.getByRole('complementary').getByRole('link', { name: 'Demo' }));
        await clickAndWaitForLoad(site, site.getByRole('link', { name: 'New post' }));

        await site.getByLabel('Title').fill(title);
        await site.getByLabel('Content').fill('Our company needs SAML for all tools.');
        await site.getByRole('button', { name: 'Create post' }).click();

        await expect(site.getByRole('status')).toHaveText('Post created successfully');
        await expect(site.getByRole('heading', { level: 1, name: title })).toBeVisible();
        await expect(site.getByRole('button', { name: 'Remove your vote' })).toBeVisible();
    });
});
