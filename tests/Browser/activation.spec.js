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
  const activate = plugin.getByRole('link', { name: 'Activate UOP Core', exact: true });
  const deactivate = plugin.getByRole('link', { name: 'Deactivate UOP Core', exact: true });

  // Another browser security check may deliberately leave UOP active.
  // Normalize the initial state through the REAL web UI, not a database
  // shortcut, so running this regression repeatedly remains independent.
  if (await deactivate.isVisible()) {
    await deactivate.click();
    await expect(activate).toBeVisible();
  } else {
    await expect(activate).toBeVisible();
  }
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);

  // Each link already performs a navigation to the refreshed WordPress table.
  // Additional unconditional page.goto() calls caused intermittent
  // 90-second waits for unrelated admin assets in the PHP CI server.
  await activate.click();
  await expect(deactivate).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);

  await deactivate.click();
  await expect(activate).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);
});
