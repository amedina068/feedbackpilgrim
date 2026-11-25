import { clickAndWaitForLoad, expect, logInAsOwner, test } from './support/test';

test('the owner removes the first of two new custom fields and saves the second', async ({ page }) => {
    await logInAsOwner(page);

    await page.goto('/contacts');
    await clickAndWaitForLoad(page, page.getByRole('link', { name: 'Ada Lovelace' }));
    await clickAndWaitForLoad(page, page.getByRole('link', { name: 'Edit', exact: true }));

    await page.getByRole('button', { name: 'Add Custom Field' }).click();
    await page.getByRole('button', { name: 'Add Custom Field' }).click();

    await page.getByLabel('Field name').nth(0).fill('region');
    await page.getByLabel('Value of region').fill('EMEA');
    await page.getByLabel('Field name').nth(1).fill('billing_plan');
    await page.getByLabel('Value of billing_plan').fill('Enterprise');

    await page.getByRole('button', { name: 'Remove field' }).first().click();

    await expect(page.getByLabel('Field name')).toHaveCount(1);
    await expect(page.getByLabel('Field name')).toHaveValue('billing_plan');
    await expect(page.getByLabel('Value of billing_plan')).toHaveValue('Enterprise');

    await clickAndWaitForLoad(page, page.getByRole('button', { name: 'Save' }));

    await expect(page.getByRole('link', { name: 'Edit', exact: true })).toBeVisible();
    await expect(page.getByText('Billing Plan', { exact: true })).toBeVisible();
    await expect(page.getByText('Enterprise', { exact: true })).toBeVisible();
    await expect(page.getByText('Region', { exact: true })).toHaveCount(0);
});
