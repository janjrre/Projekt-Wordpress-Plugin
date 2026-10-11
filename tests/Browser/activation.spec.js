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
  // Follow WordPress's own nonce-protected UI action href without waiting
  // for the entire admin asset tree and unrelated background connections to
  // reach the 'load' event. The real WordPress action is still executed.
  async function followAction(link) {
    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();
    const destination = new URL(href, page.url());
    expect(destination.origin).toBe(new URL(page.url()).origin);
    expect(destination.pathname).toBe('/wp-admin/plugins.php');
    expect(destination.searchParams.has('_wpnonce')).toBeTruthy();
    await page.goto(destination.href, { waitUntil: 'domcontentloaded', timeout: 30000 });
  }

  // Another browser security check may deliberately leave UOP active.
  // Normalize the initial state through the REAL web UI, not a database
  // shortcut, so running this regression repeatedly remains independent.
  if (await deactivate.isVisible()) {
    await followAction(deactivate);
    await expect(activate).toBeVisible();
  } else {
    await expect(activate).toBeVisible();
  }
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);

  // Each link already performs a navigation to the refreshed WordPress table.
  // Additional unconditional page.goto() calls caused intermittent
  // 90-second waits for unrelated admin assets in the PHP CI server.
  await followAction(activate);
  await expect(deactivate).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);

  await followAction(deactivate);
  await expect(activate).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:|Failed checks:/);
});
