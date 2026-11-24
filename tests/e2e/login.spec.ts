import { expect, logInAsOwner, owner, test } from './support/test';

test.describe('dashboard login', () => {
    test('a wrong password shows an error and keeps the user on the login page', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill(owner.email);
        await page.getByLabel('Password').fill('not-the-password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('the owner logs in and the home page opens the dashboard', async ({ page }) => {
        await logInAsOwner(page);

        await page.goto('/');

        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('registration is closed by default', async ({ page }) => {
        const response = await page.goto('/register');

        expect(response?.status()).toBe(404);
    });
});
