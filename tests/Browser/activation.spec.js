import { test, expect } from '@playwright/test';
test('web activation and deactivation complete without PHP diagnostics', async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.getByLabel('Username or Email Address').fill(process.env.WP_ADMIN_USER || 'uop_test_admin');
  await page.getByLabel('Password', { exact: true }).fill(process.env.WP_ADMIN_PASSWORD);
  await page.getByRole('button', { name: 'Log In', exact: true }).click();
  await expect(page).toHaveURL(/\/wp-admin\//);
  await page.goto('/wp-admin/plugins.php');
  const plugin = page.locator('tr[data-slug="uop-core"]');
  await plugin.getByRole('link', { name: 'Activate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true })).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);
  await plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Activate UOP Core', exact: true })).toBeVisible();
});
