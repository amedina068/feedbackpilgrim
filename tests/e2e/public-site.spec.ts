import { expect, publicSite, test } from './support/test';

test.describe('public site of a project', () => {
    test('a guest sees the published posts and release notes', async ({ page }) => {
        await page.goto(publicSite('/'));

        await expect(page.getByRole('heading', { level: 1, name: 'Home' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Version 2.0 is out' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Dark mode for the dashboard' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Export the posts to CSV' })).toBeVisible();
        await expect(page.getByText('Unpublished internal idea')).toHaveCount(0);
    });

    test('a guest opens a post but cannot vote', async ({ page }) => {
        await page.goto(publicSite('/posts'));

        await page.getByRole('link', { name: 'Dark mode for the dashboard' }).click();

        await expect(page.getByRole('heading', { level: 1, name: 'Dark mode for the dashboard' })).toBeVisible();
        await expect(page.getByText('The dashboard is too bright at night.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Upvote' })).toHaveCount(0);
    });
});
