import { test, expect } from '@playwright/test';

test('web activation and deactivation complete without PHP diagnostics', async ({ page }) => {
  test.setTimeout(90000);
  // Authenticate through WordPress; its delayed login autofocus can interrupt
  // synthetic typing. The browser context shares this request's real cookies.
  const loginPage = await page.request.get('/wp-login.php');
  const login = await page.request.post('/wp-login.php', {
    form: {
      log: process.env.WP_ADMIN_USER || 'uop_test_admin',
      pwd: process.env.WP_ADMIN_PASSWORD,
      testcookie: '1',
      redirect_to: new URL('/wp-admin/', loginPage.url()).href,
    },
  });
  expect(login.ok()).toBeTruthy();
  expect(login.url()).toMatch(/\/wp-admin\//);

  // The plugin table is rendered server-side. Do not wait for every optional
  // admin asset to reach the 'load' state: under MariaDB/PHP CI load a
  // nonessential resource can stall while the required plugin row is ready.
  await page.goto('/wp-admin/plugins.php', {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });
  const plugin = page.locator('tr[data-slug="uop-core"]');
  await expect(plugin.getByRole('link', { name: 'Activate UOP Core', exact: true })).toBeVisible();

  // Each link already performs a navigation to the refreshed WordPress table.
  // A second unconditional page.goto() after activation/deactivation was
  // redundant and triggered the observed 90-second 'load' timeout in CI.
  await plugin.getByRole('link', { name: 'Activate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true })).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);

  await plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true }).click();
  await expect(plugin.getByRole('link', { name: 'Activate UOP Core', exact: true })).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);
});
