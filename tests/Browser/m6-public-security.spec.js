import { test, expect } from '@playwright/test';

test('guest verification and waitlist offer deny insecure browser and REST actions', async ({ page }) => {
  test.setTimeout(90000);
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
  await page.goto('/wp-admin/plugins.php');
  const plugin = page.locator('tr[data-slug="uop-core"]');
  const activate = plugin.getByRole('link', { name: 'Activate UOP Core', exact: true });
  if (await activate.isVisible()) await activate.click();

  // The disposable GitHub CI server is HTTP only. Both bearer-action pages
  // must fail closed before rendering any confirmation UI or JavaScript.
  for (const [path, message] of [
    ['/?uop-verify=1', 'HTTPS is required for email verification.'],
    ['/?uop-offer=1', 'HTTPS is required to accept a waitlist offer.'],
  ]) {
    const response = await page.request.get(path, { failOnStatusCode: false });
    expect(response.status()).toBe(403);
    const html = await response.text();
    expect(html).toContain(message);
    expect(html).not.toContain('data-endpoint=');
    expect(html).not.toContain('data-received=');
  }

  for (const path of [
    '/wp-json/uop/v1/registrations/guest',
    '/wp-json/uop/v1/registration-verifications',
    '/wp-json/uop/v1/waitlist-offers/guest-accept',
  ]) {
    const response = await page.request.post(path, {
      data: {},
      failOnStatusCode: false,
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBe(503);
    const envelope = await response.json();
    expect(envelope.code).toBe('uop_unavailable');
    expect(JSON.stringify(envelope)).not.toContain('token');
  }
  await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Notice:/);
});
