import { clickAndWaitForLoad, expect, logInAsOwner, test } from './support/test';

test('the owner creates a post for a contact in the dashboard', async ({ page }) => {
    const title = `Slack alerts for new posts ${Date.now()}`;

    await logInAsOwner(page);

    await page.goto('/posts');
    await clickAndWaitForLoad(page, page.getByRole('link', { name: 'Create Post' }));

    await page.getByLabel('Contact').selectOption({ label: 'ada@example.com' });
    await page.getByLabel('Title').fill(title);
    await page.getByLabel('Content').fill('Send a Slack message when a customer adds a post.');
    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.getByRole('status')).toHaveText('Post created successfully');
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    await page.goto('/posts');

    await expect(page.getByText(title)).toBeVisible();
});
